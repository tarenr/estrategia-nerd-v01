<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Services/Instagram/BlogCrosspostService.php
 * @project     Estrategia Nerd
 * @purpose     Cross-post Blog -> Instagram: previa (canvas + legenda) e
 *              gravacao idempotente do rascunho local (FEAT-010 #328)
 * @notes       Instagram e local-only: usa sempre o PDO local ($GLOBALS['pdo']).
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Services\Instagram;

use App\Repositories\InstagramPostRepository;
use App\Support\Session;
use PDO;
use PDOException;
use RuntimeException;

final class BlogCrosspostService
{
    public const STATUS_OK       = 'ok';
    public const STATUS_FAILED   = 'falhou';
    public const STATUS_REFUSED  = 'recusado';
    public const STATUS_SKIPPED  = 'ignorado';

    private const SESSION_KEY     = '_ig_crosspost_tokens';
    private const TOKEN_TTL       = 86400;
    private const MAX_TOKENS      = 20;
    private const PREVIEW_REL_DIR = 'uploads/instagram/preview';
    private const FINAL_REL_DIR   = 'uploads/instagram';
    private const EDITABLE        = ['rascunho', 'erro'];
    // Namespace fixo (RFC 4122 NAMESPACE_URL) para a chave idempotente UUID v5.
    private const UUID_NAMESPACE  = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';

    public function __construct(
        private readonly PDO $localPdo,
        private readonly SmartCanvasRenderer $renderer,
        private readonly GeminiCaptionService $captions,
        private readonly string $publicRoot,
    ) {
    }

    public static function fromGlobals(): self
    {
        /** @var PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        return new self($pdo, new SmartCanvasRenderer(), GeminiCaptionService::fromEnv(), base_path('public'));
    }

    public static function idempotencyKey(string $environment, int $blogPostId): string
    {
        $ns   = (string) hex2bin(str_replace('-', '', self::UUID_NAMESPACE));
        $hash = sha1($ns . 'blog:' . $environment . ':' . $blogPostId, true);
        $hash[6] = chr((ord($hash[6]) & 0x0f) | 0x50);
        $hash[8] = chr((ord($hash[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(substr($hash, 0, 16)), 4));
    }

    // ── Previa ────────────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $files
     * @return array<string,mixed>
     */
    public function preview(array $input, array $files, ?int $authorId, string $environment): array
    {
        $this->purgeExpiredTokens();
        $this->cleanupOldPreviews();

        $postId  = max(0, (int) ($input['post_id'] ?? 0));
        $formUid = $this->normalizeFormUid((string) ($input['form_uid'] ?? ''));
        $version = max(0, (int) ($input['version'] ?? 0));
        $title   = trim((string) ($input['titulo'] ?? ''));
        $summary = trim((string) ($input['resumo'] ?? ''));
        $category = trim((string) ($input['categoria'] ?? ''));

        if ($postId === 0 && $formUid === '') {
            return ['ok' => false, 'error' => 'Formulario sem identificador. Recarregue a pagina.'];
        }
        if ($title === '') {
            return ['ok' => false, 'error' => 'Preencha o titulo do post antes de gerar a previa.'];
        }

        if ((string) ($input['modo'] ?? '') === 'legenda') {
            $caption = $this->captions->generate($title, $summary, $category);

            return ['ok' => true, 'version' => $version] + $caption;
        }

        try {
            [$sourcePath, $kind] = $this->resolvePreviewSource($input, $files);
        } catch (RuntimeException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        try {
            $this->renderer->assertValidSource($sourcePath);
        } catch (RuntimeException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $sourceHash = (string) hash_file('sha256', $sourcePath);
        $token      = bin2hex(random_bytes(16));
        $relative   = self::PREVIEW_REL_DIR . '/' . $token . '.jpg';
        $absolute   = $this->absolute($relative);

        try {
            if ($kind === 'arte') {
                [$w, $h] = $this->renderer->reencode($sourcePath, $absolute);
                $ratio   = $w / max(1, $h);
                if ($ratio < 0.78 || $ratio > 1.02) {
                    @unlink($absolute);
                    return ['ok' => false, 'error' => 'A arte dedicada precisa ser quadrada (1:1) ou vertical 4:5.'];
                }
            } else {
                $this->renderer->render($sourcePath, $absolute);
            }
        } catch (RuntimeException $e) {
            @unlink($absolute);
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $caption = $this->captions->generate($title, $summary, $category);

        $tokens = $this->tokens();
        $tokens[$token] = [
            'author'      => (int) ($authorId ?? 0),
            'environment' => $environment,
            'post_id'     => $postId,
            'form_uid'    => $formUid,
            'file'        => $relative,
            'kind'        => $kind,
            'source_hash' => $sourceHash,
            'version'     => $version,
            'expires'     => time() + self::TOKEN_TTL,
        ];
        $this->storeTokens($tokens);

        return [
            'ok'          => true,
            'token'       => $token,
            'version'     => $version,
            'kind'        => $kind,
            'preview_url' => $relative,
        ] + $caption;
    }

    /**
     * Hash dos bytes originais da capa enviados neste save, calculado ANTES do
     * PostsService processar uploads ou mover a pasta por mudanca de slug.
     *
     * @param array<string,mixed> $input
     * @param array<string,mixed> $files
     */
    public function captureCoverHash(array $input, array $files): string
    {
        $upload = $files['imagem_capa_upload'] ?? null;
        if (is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmp = (string) ($upload['tmp_name'] ?? '');
            if ($tmp !== '' && is_uploaded_file($tmp)) {
                return (string) hash_file('sha256', $tmp);
            }
        }

        $path = $this->resolveUploadsPath((string) ($input['imagem_capa'] ?? ''));

        return $path !== null ? (string) hash_file('sha256', $path) : '';
    }

    // ── Gravacao apos salvar o blog ───────────────────────────────────────────

    /**
     * @param array<string,mixed> $input
     * @return array{status:string,message:string,instagram_post_id:int}
     */
    public function persistAfterSave(array $input, string $coverHash, int $blogPostId, bool $isCreate, ?int $authorId, string $environment): array
    {
        if ((string) ($input['ig_crosspost'] ?? '') !== '1') {
            return $this->result(self::STATUS_SKIPPED, '');
        }

        $token  = (string) ($input['ig_crosspost_token'] ?? '');
        $tokens = $this->tokens();
        $entry  = $tokens[$token] ?? null;

        if ($token === '' || !is_array($entry)) {
            return $this->result(self::STATUS_REFUSED, 'Previa ausente ou expirada. Gere a previa do Instagram novamente.');
        }
        if ((int) ($entry['expires'] ?? 0) < time()) {
            return $this->result(self::STATUS_REFUSED, 'A previa do Instagram expirou. Gere novamente.');
        }
        if ((int) ($entry['author'] ?? -1) !== (int) ($authorId ?? 0) || (string) ($entry['environment'] ?? '') !== $environment) {
            return $this->result(self::STATUS_REFUSED, 'A previa pertence a outro usuario ou ambiente.');
        }

        $entryPostId = (int) ($entry['post_id'] ?? 0);
        $samePost    = $entryPostId > 0
            ? $entryPostId === $blogPostId
            : ($isCreate && (string) ($entry['form_uid'] ?? '') === $this->normalizeFormUid((string) ($input['ig_crosspost_form_uid'] ?? '')));
        if (!$samePost) {
            return $this->result(self::STATUS_REFUSED, 'A previa foi gerada para outro post.');
        }

        if ((string) ($entry['kind'] ?? '') === 'capa' && ($coverHash === '' || !hash_equals((string) ($entry['source_hash'] ?? ''), $coverHash))) {
            return $this->result(self::STATUS_REFUSED, 'A capa mudou depois da previa. Gere a previa do Instagram novamente.');
        }

        $previewAbs = $this->absolute((string) ($entry['file'] ?? ''));
        if (!is_file($previewAbs)) {
            return $this->result(self::STATUS_REFUSED, 'O arquivo da previa nao existe mais. Gere novamente.');
        }

        $legenda = $this->composeCaption((string) ($input['ig_crosspost_legenda'] ?? ''), (string) ($input['ig_crosspost_hashtags'] ?? ''));
        $api     = new InstagramApiService('', '');
        $check   = $api->validateCaption($legenda);
        if (!$check['ok']) {
            return $this->result(self::STATUS_REFUSED, implode(' ', $check['errors']));
        }

        // Token e previa continuam validos ate expirar: salvar de novo so regrava o mesmo rascunho.
        return $this->writeDraft($blogPostId, $environment, $legenda, $api->countHashtags($legenda), $previewAbs, $authorId, true);
    }

    /**
     * Post do Instagram vinculado (criado pelo cross-post) a um post do blog.
     *
     * @return array<string,mixed>|null
     */
    public function findLinked(string $environment, int $blogPostId): ?array
    {
        if ($blogPostId <= 0) {
            return null;
        }

        try {
            $repo = new InstagramPostRepository($this->localPdo);
            $post = $repo->findByIdempotencyKey(self::idempotencyKey($environment, $blogPostId));
            if ($post === null) {
                return null;
            }
            $medias = $repo->findMediaByPostId((int) $post['id']);
            $post['_media'] = $medias[0]['caminho'] ?? '';
            $post['_editable'] = in_array((string) ($post['status'] ?? ''), self::EDITABLE, true);

            return $post;
        } catch (\Throwable $e) {
            error_log('[BlogCrosspostService] findLinked: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Posts do blog do ambiente informado que ja tem post no Instagram (qualquer status),
     * indexados pelo id do blog. O Instagram e local-only, entao o post_blog_id e ambiguo
     * entre ambientes: a chave UUID v5 diz de qual ambiente veio o cross-post; vinculos
     * manuais antigos (chave aleatoria) sempre foram do banco local.
     *
     * @return array<int,array{id:int,status:string}>
     */
    public function linkedBlogPosts(string $environment): array
    {
        $stmt = $this->localPdo->query('SELECT id, status, post_blog_id, idempotency_key FROM instagram_posts WHERE post_blog_id IS NOT NULL ORDER BY id ASC');
        $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $others = array_values(array_diff(['local', 'stage', 'production'], [$environment]));

        $linked = [];
        foreach ($rows as $row) {
            $blogId = (int) $row['post_blog_id'];
            $key    = (string) ($row['idempotency_key'] ?? '');

            $belongs = $key === self::idempotencyKey($environment, $blogId);
            if (!$belongs) {
                $fromOther = false;
                foreach ($others as $other) {
                    if ($key === self::idempotencyKey($other, $blogId)) {
                        $fromOther = true;
                    }
                }
                $belongs = !$fromOther && $environment === 'local';
            }

            if ($belongs) {
                $linked[$blogId] ??= ['id' => (int) $row['id'], 'status' => (string) $row['status']];
            }
        }

        return $linked;
    }

    public function composeCaption(string $text, string $hashtags): string
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        $tags = $this->captions->normalizeHashtags(preg_split('/[\s,]+/u', trim($hashtags), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $line = implode(' ', $tags);

        if ($line === '') {
            return $text;
        }

        return $text !== '' ? $text . "\n\n" . $line : $line;
    }

    // ── Internos ──────────────────────────────────────────────────────────────

    /**
     * @return array{status:string,message:string,instagram_post_id:int}
     */
    private function writeDraft(int $blogPostId, string $environment, string $legenda, int $hashtagsCount, string $previewAbs, ?int $authorId, bool $allowRetry): array
    {
        $repo    = new InstagramPostRepository($this->localPdo);
        $account = $repo->findActiveAccount();
        if ($account === null) {
            return $this->result(self::STATUS_FAILED, 'Nenhuma conta do Instagram configurada.');
        }

        $key         = self::idempotencyKey($environment, $blogPostId);
        $promotedRel = self::FINAL_REL_DIR . '/' . bin2hex(random_bytes(16)) . '.jpg';
        $promotedAbs = $this->absolute($promotedRel);
        $oldFiles    = [];
        $row         = null;
        $igPostId    = 0;

        try {
            if (!@copy($previewAbs, $promotedAbs)) {
                throw new RuntimeException('Falha ao copiar a imagem da previa para a pasta definitiva.');
            }

            $this->localPdo->beginTransaction();

            $row = $repo->lockByIdempotencyKey($key);
            if ($row !== null && !in_array((string) ($row['status'] ?? ''), self::EDITABLE, true)) {
                $this->localPdo->rollBack();
                @unlink($promotedAbs);

                return $this->result(
                    self::STATUS_REFUSED,
                    'O post do Instagram vinculado esta "' . (string) $row['status'] . '" e nao pode ser alterado por aqui.',
                    (int) $row['id'],
                );
            }

            if ($row === null) {
                $igPostId = $repo->create([
                    'account_id'      => (int) $account['id'],
                    'status'          => 'rascunho',
                    'tipo'            => 'imagem',
                    'legenda'         => $legenda !== '' ? $legenda : null,
                    'hashtags_count'  => $hashtagsCount,
                    'agendado_para'   => null,
                    'post_blog_id'    => $blogPostId,
                    'idempotency_key' => $key,
                    'origin'          => 'local',
                    'criado_por'      => $authorId,
                ]);
            } else {
                $igPostId = (int) $row['id'];
                $repo->updateCrosspostDraft($igPostId, $legenda, $hashtagsCount);
                foreach ($repo->findMediaByPostId($igPostId) as $media) {
                    $oldFiles[] = (string) ($media['caminho'] ?? '');
                }
                $repo->deleteMediaByPostId($igPostId);
            }

            $repo->addMedia($igPostId, [
                'ordem'        => 0,
                'tipo_arquivo' => 'imagem',
                'caminho'      => $promotedRel,
                'url_publica'  => rtrim((string) config('app.url', ''), '/') . '/' . $promotedRel,
                'largura'      => null,
                'altura'       => null,
            ]);

            $this->localPdo->commit();
        } catch (\Throwable $e) {
            if ($this->localPdo->inTransaction()) {
                $this->localPdo->rollBack();
            }
            @unlink($promotedAbs);

            if ($allowRetry && $e instanceof PDOException && in_array((string) $e->getCode(), ['23000', '40001'], true)) {
                // Save simultaneo (chave duplicada ou deadlock no gap lock): repetir cai no caminho de update.
                return $this->writeDraft($blogPostId, $environment, $legenda, $hashtagsCount, $previewAbs, $authorId, false);
            }

            error_log('[BlogCrosspostService] writeDraft: ' . $e->getMessage());

            return $this->result(self::STATUS_FAILED, 'Falha ao gravar o rascunho do Instagram. Tente salvar novamente.');
        }

        foreach ($oldFiles as $old) {
            $this->deleteOwnedFile($old);
        }

        return $this->result(self::STATUS_OK, $row === null ? 'Rascunho do Instagram criado.' : 'Rascunho do Instagram atualizado.', $igPostId);
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $files
     * @return array{0:string,1:string} caminho absoluto e tipo ('arte'|'capa')
     */
    private function resolvePreviewSource(array $input, array $files): array
    {
        foreach (['arte_dedicada' => 'arte', 'imagem_capa_upload' => 'capa'] as $field => $kind) {
            $upload = $files[$field] ?? null;
            if (!is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $tmp = (string) ($upload['tmp_name'] ?? '');
            if ((int) ($upload['error'] ?? 0) !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
                throw new RuntimeException('Falha no envio da imagem.');
            }

            return [$tmp, $kind];
        }

        $raw = trim((string) ($input['imagem_capa'] ?? ''));
        if ($raw === '') {
            throw new RuntimeException('Defina a capa do post ou envie uma arte dedicada para gerar a previa.');
        }

        $path = $this->resolveUploadsPath($raw);
        if ($path === null) {
            throw new RuntimeException('A capa informada nao esta nos uploads deste servidor. Envie uma arte dedicada.');
        }

        return [$path, 'capa'];
    }

    /**
     * Resolve um caminho "uploads/..." para arquivo real dentro de public/uploads (sem traversal).
     */
    private function resolveUploadsPath(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $parsed = parse_url($raw, PHP_URL_PATH);
        $raw    = ltrim(is_string($parsed) ? $parsed : $raw, '/\\');
        $marker = strpos($raw, 'uploads/');
        if ($marker === false) {
            return null;
        }
        $raw = substr($raw, $marker);
        if (str_contains($raw, '..') || str_contains($raw, "\0")) {
            return null;
        }

        $uploadsReal = realpath($this->publicRoot . DIRECTORY_SEPARATOR . 'uploads');
        $targetReal  = realpath($this->publicRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw));
        if ($uploadsReal === false || $targetReal === false || !is_file($targetReal)) {
            return null;
        }

        return str_starts_with($targetReal, $uploadsReal . DIRECTORY_SEPARATOR) ? $targetReal : null;
    }

    private function deleteOwnedFile(string $relative): void
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if (!str_starts_with($relative, self::FINAL_REL_DIR . '/') || str_starts_with($relative, self::PREVIEW_REL_DIR . '/') || str_contains($relative, '..')) {
            return;
        }

        $path = realpath($this->absolute($relative));
        $root = realpath($this->absolute(self::FINAL_REL_DIR));
        if ($path !== false && $root !== false && str_starts_with($path, $root . DIRECTORY_SEPARATOR) && is_file($path)) {
            @unlink($path);
        }
    }

    private function absolute(string $relative): string
    {
        return $this->publicRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relative, '/\\'));
    }

    private function normalizeFormUid(string $uid): string
    {
        return preg_match('/^[A-Za-z0-9]{8,64}$/', $uid) === 1 ? $uid : '';
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function tokens(): array
    {
        $tokens = Session::get(self::SESSION_KEY, []);

        return is_array($tokens) ? $tokens : [];
    }

    /**
     * @param array<string,array<string,mixed>> $tokens
     */
    private function storeTokens(array $tokens): void
    {
        uasort($tokens, static fn (array $a, array $b): int => (int) ($a['expires'] ?? 0) <=> (int) ($b['expires'] ?? 0));
        Session::put(self::SESSION_KEY, array_slice($tokens, -self::MAX_TOKENS, null, true));
    }

    private function purgeExpiredTokens(): void
    {
        $now = time();
        $this->storeTokens(array_filter($this->tokens(), static fn (array $t): bool => (int) ($t['expires'] ?? 0) >= $now));
    }

    /**
     * Previas mais velhas que o TTL ja nao tem token valido em nenhuma sessao.
     */
    private function cleanupOldPreviews(): void
    {
        $dir = $this->absolute(self::PREVIEW_REL_DIR);
        if (!is_dir($dir)) {
            return;
        }

        $limit = time() - self::TOKEN_TTL - 3600;
        foreach ((array) glob($dir . DIRECTORY_SEPARATOR . '*.jpg') as $file) {
            if (is_string($file) && is_file($file) && (int) filemtime($file) < $limit) {
                @unlink($file);
            }
        }
    }

    /**
     * @return array{status:string,message:string,instagram_post_id:int}
     */
    private function result(string $status, string $message, int $igPostId = 0): array
    {
        return ['status' => $status, 'message' => $message, 'instagram_post_id' => $igPostId];
    }
}
