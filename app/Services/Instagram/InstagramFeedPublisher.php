<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Services/Instagram/InstagramFeedPublisher.php
 * @project     Estrategia Nerd
 * @purpose     Publica o feed do Instagram (config/instagram-feed.json + capas) em
 *              Stage/Producao por FTP, com validacao, backup e verificacao (FEAT-010 v2.12.0)
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Services\Instagram;

use RuntimeException;
use Throwable;

/**
 * Escopo remoto restrito: <core>/config/instagram-feed.json e <public>/assets/instagram-feed/.
 * As capas sobem antes do JSON; o JSON entra por arquivo temporario + rename e o anterior
 * fica como .bak. Nunca toca em codigo, .env ou uploads.
 */
final class InstagramFeedPublisher
{
    public const MAX_POSTS = 18;
    public const MIN_COVER_BYTES = 1000;

    private const PUBLIC_URLS = [
        'stage' => 'https://estrategianerd.com.br/stage',
        'production' => 'https://estrategianerd.com.br',
    ];
    private const JSON_REL = 'config/instagram-feed.json';
    private const COVER_DIR_REL = 'assets/instagram-feed';
    private const COVER_PATTERN = '#^/assets/instagram-feed/(post_\d+\.jpg)$#';

    /** @var callable(string): void */
    private $log;
    private ?\FTP\Connection $ftp = null;
    private string $publicRoot = '';
    private string $coreRoot = '';

    /**
     * @param array<string,mixed> $profile Perfil do config/content-sync.php (stage|production).
     */
    public function __construct(
        private readonly string $target,
        private readonly array $profile,
        private readonly string $projectRoot,
        ?callable $log = null,
    ) {
        if (!isset(self::PUBLIC_URLS[$target])) {
            throw new RuntimeException('Alvo invalido; use stage ou production.');
        }
        $this->log = $log ?? static function (string $message): void {};
    }

    /**
     * Valida o feed local antes de qualquer envio.
     *
     * @param array<string,mixed> $feed
     * @return array{errors: list<string>, covers: list<string>}
     */
    public static function validateFeed(array $feed, string $publicRoot): array
    {
        $errors = [];
        $covers = [];
        $posts = $feed['posts'] ?? null;
        if (!is_array($posts) || $posts === []) {
            return ['errors' => ['Feed sem posts.'], 'covers' => []];
        }
        if (count($posts) > self::MAX_POSTS) {
            $errors[] = 'Feed com mais de ' . self::MAX_POSTS . ' posts.';
        }

        $previous = null;
        foreach (array_values($posts) as $index => $post) {
            $label = 'Post #' . (is_array($post) ? (int) ($post['id'] ?? 0) : 0) . ' (posicao ' . ($index + 1) . ')';
            if (!is_array($post)) {
                $errors[] = $label . ': formato invalido.';
                continue;
            }
            $media = (string) ($post['media_url'] ?? '');
            if (preg_match(self::COVER_PATTERN, $media, $match) !== 1) {
                $errors[] = $label . ': media_url nao e uma capa local .jpg (' . $media . ').';
            } else {
                $file = rtrim($publicRoot, '/\\') . $media;
                if (!is_file($file) || (int) filesize($file) < self::MIN_COVER_BYTES) {
                    $errors[] = $label . ': capa local ausente ou menor que 1 KB.';
                } else {
                    $covers[] = $match[1];
                }
            }

            $timestamp = strtotime((string) ($post['publicado_em'] ?? ''));
            if ($timestamp === false) {
                $errors[] = $label . ': publicado_em invalido.';
            } elseif ($previous !== null && $timestamp > $previous) {
                $errors[] = $label . ': fora da ordem decrescente de data.';
            }
            if ($timestamp !== false) {
                $previous = $timestamp;
            }
        }

        return ['errors' => $errors, 'covers' => $errors === [] ? $covers : []];
    }

    // ── Estado local (compartilhado com o agendador) ─────────────────────────

    public static function statePath(string $projectRoot): string
    {
        return $projectRoot . '/storage/instagram-feed-publish/state.json';
    }

    /**
     * @return array<string,mixed>
     */
    public static function readState(string $projectRoot): array
    {
        $path = self::statePath($projectRoot);
        $state = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($state) ? $state : [];
    }

    /**
     * @param array<string,mixed> $state
     */
    public static function writeState(string $projectRoot, array $state): void
    {
        $path = self::statePath($projectRoot);
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        $tmp = $path . '.tmp';
        file_put_contents($tmp, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        rename($tmp, $path);
    }

    /**
     * Precisa publicar quando o ultimo envio deste alvo nao foi bem-sucedido ou o JSON local mudou.
     */
    public static function needsPublish(string $projectRoot, string $target): bool
    {
        $localJson = $projectRoot . '/' . self::JSON_REL;
        if (!is_file($localJson)) {
            return false;
        }
        $entry = (array) (self::readState($projectRoot)[$target] ?? []);

        return !in_array($entry['status'] ?? '', ['ok', 'unchanged'], true)
            || ($entry['hash'] ?? '') !== hash_file('sha256', $localJson);
    }

    /**
     * Testa se a hospedagem aceita rename sobre arquivo existente (sem tocar no feed).
     *
     * @return array{ok: bool, message: string}
     */
    public function probeRename(): array
    {
        try {
            $this->connect();
            $a = $this->coreRoot . '/config/.feed-probe-a.tmp';
            $b = $this->coreRoot . '/config/.feed-probe-b.tmp';
            $this->putBytes($a, 'probe-a');
            $this->putBytes($b, 'probe-b');
            $renamed = @ftp_rename($this->conn(), $a, $b);
            $content = $renamed ? $this->readBytes($b) : '';
            @ftp_delete($this->conn(), $a);
            @ftp_delete($this->conn(), $b);
            if (!$renamed || $content !== 'probe-a') {
                return ['ok' => false, 'message' => 'Hospedagem nao substitui arquivo existente com rename.'];
            }

            return ['ok' => true, 'message' => 'rename sobre arquivo existente suportado.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        } finally {
            $this->close();
        }
    }

    /**
     * @return array{status: string, message: string, uploaded: int, hash: string, verified: bool}
     */
    public function publish(bool $dryRun): array
    {
        $result = ['status' => 'failed', 'message' => '', 'uploaded' => 0, 'hash' => '', 'verified' => false];
        try {
            $localJson = $this->projectRoot . '/' . self::JSON_REL;
            $bytes = is_file($localJson) ? (string) file_get_contents($localJson) : '';
            $feed = json_decode($bytes, true);
            if (!is_array($feed)) {
                throw new RuntimeException('config/instagram-feed.json local ausente ou invalido.');
            }
            $validation = self::validateFeed($feed, $this->projectRoot . '/public');
            if ($validation['errors'] !== []) {
                throw new RuntimeException('Feed local reprovado: ' . implode(' | ', $validation['errors']));
            }
            $result['hash'] = hash('sha256', $bytes);

            $this->connect();
            $remoteJson = $this->coreRoot . '/' . self::JSON_REL;
            $remoteBytes = $this->exists($remoteJson) ? $this->readBytes($remoteJson) : '';

            $missing = [];
            foreach ($validation['covers'] as $cover) {
                $local = $this->projectRoot . '/public/' . self::COVER_DIR_REL . '/' . $cover;
                $remote = $this->publicRoot . '/' . self::COVER_DIR_REL . '/' . $cover;
                if (@ftp_size($this->conn(), $remote) !== (int) filesize($local)) {
                    $missing[] = $cover;
                }
            }

            if ($remoteBytes !== '' && hash('sha256', $remoteBytes) === $result['hash'] && $missing === []) {
                return ['status' => 'unchanged', 'message' => 'Remoto ja esta igual ao local.'] + $result;
            }
            if ($dryRun) {
                $message = 'Dry-run: enviaria ' . count($missing) . ' capa(s) [' . implode(', ', $missing) . '] e '
                    . ($remoteBytes === $bytes ? 'manteria' : 'trocaria') . ' o JSON.';

                return ['status' => 'dry-run', 'message' => $message, 'uploaded' => count($missing)] + $result;
            }

            // Evidencia local do estado remoto anterior e .bak remoto (nunca antes de validar o feed).
            $evidenceDir = $this->projectRoot . '/storage/instagram-feed-publish/' . $this->target;
            if (!is_dir($evidenceDir)) {
                mkdir($evidenceDir, 0700, true);
            }
            if ($remoteBytes !== '') {
                file_put_contents($evidenceDir . '/before-' . date('Ymd-His') . '.json', $remoteBytes, LOCK_EX);
                $this->putBytes($remoteJson . '.bak', $remoteBytes);
            }

            // Capas primeiro: o JSON antigo nunca aponta para capa que ainda nao chegou.
            foreach ($missing as $cover) {
                $this->putFile(
                    $this->publicRoot . '/' . self::COVER_DIR_REL . '/' . $cover,
                    $this->projectRoot . '/public/' . self::COVER_DIR_REL . '/' . $cover
                );
                $result['uploaded']++;
                ($this->log)('capa enviada: ' . $cover);
            }

            $this->replaceJson($remoteJson, $bytes);
            ($this->log)('JSON remoto substituido (' . strlen($bytes) . ' bytes).');

            $check = $this->verifyHttp($feed);
            if ($check['fatal'] && $remoteBytes !== '') {
                $this->replaceJson($remoteJson, $remoteBytes);
                throw new RuntimeException('Site respondeu erro apos a troca; JSON anterior restaurado. ' . $check['message']);
            }
            $result['verified'] = $check['ok'];
            $result['status'] = $check['ok'] ? 'ok' : 'unverified';
            $result['message'] = $check['message'];

            return $result;
        } catch (Throwable $e) {
            $result['status'] = 'failed';
            $result['message'] = $e->getMessage();

            return $result;
        } finally {
            $this->close();
        }
    }

    // ── FTP ──────────────────────────────────────────────────────────────────

    private function connect(): void
    {
        $code = (array) ($this->profile['code_deploy'] ?? []);
        $uploads = (array) ($this->profile['uploads'] ?? []);
        foreach (['host', 'port', 'username', 'password', 'root'] as $required) {
            if (trim((string) ($code[$required] ?? '')) === '') {
                throw new RuntimeException('Configuracao de FTP incompleta para ' . $this->target . ' (faltando ' . $required . ').');
            }
        }
        if (strtolower((string) ($code['mode'] ?? 'ftp')) !== 'ftp') {
            throw new RuntimeException('Perfil ' . $this->target . ' nao usa FTP.');
        }

        $ftp = @ftp_connect((string) $code['host'], (int) $code['port'], 30);
        if ($ftp === false || !@ftp_login($ftp, (string) $code['username'], (string) $code['password'])) {
            throw new RuntimeException('FTP indisponivel para ' . $this->target . '.');
        }
        @ftp_pasv($ftp, (bool) ($code['passive'] ?? true));
        $this->ftp = $ftp;

        $this->publicRoot = dirname('/' . trim((string) ($uploads['root'] ?? ''), '/'));
        $codeRoot = '/' . trim((string) $code['root'], '/');
        if (!str_ends_with($codeRoot, '/_app_core') && @ftp_size($ftp, $codeRoot . '/_app_core/bootstrap.php') >= 0) {
            $codeRoot .= '/_app_core';
        }
        $this->coreRoot = $codeRoot;

        $stageRoot = $this->target === 'stage';
        $validPublic = $stageRoot
            ? str_ends_with($this->publicRoot, '/public_html/stage')
            : (str_ends_with($this->publicRoot, '/public_html') && !str_contains($this->publicRoot, '/stage'));
        if (!$validPublic || !in_array($this->coreRoot, [$this->publicRoot, $this->publicRoot . '/_app_core'], true)) {
            throw new RuntimeException('Raizes remotas nao conferem com o alvo ' . $this->target . '.');
        }
        if (@ftp_size($ftp, $this->coreRoot . '/bootstrap.php') < 0) {
            throw new RuntimeException('Core remoto nao confirmado em ' . $this->target . '.');
        }
    }

    private function conn(): \FTP\Connection
    {
        if ($this->ftp === null) {
            throw new RuntimeException('FTP nao conectado.');
        }

        return $this->ftp;
    }

    private function close(): void
    {
        if ($this->ftp !== null) {
            @ftp_close($this->ftp);
            $this->ftp = null;
        }
    }

    private function exists(string $path): bool
    {
        return @ftp_size($this->conn(), $path) >= 0;
    }

    private function readBytes(string $path): string
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false || !@ftp_fget($this->conn(), $stream, $path, FTP_BINARY)) {
            throw new RuntimeException('Leitura remota indisponivel: ' . basename($path));
        }
        rewind($stream);
        $bytes = (string) stream_get_contents($stream);
        fclose($stream);

        return $bytes;
    }

    private function putBytes(string $path, string $bytes): void
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('Memoria temporaria indisponivel.');
        }
        fwrite($stream, $bytes);
        rewind($stream);
        $ok = @ftp_fput($this->conn(), $path, $stream, FTP_BINARY);
        fclose($stream);
        if (!$ok || @ftp_size($this->conn(), $path) !== strlen($bytes)) {
            throw new RuntimeException('Envio remoto falhou: ' . basename($path));
        }
    }

    private function putFile(string $remote, string $local): void
    {
        if (!@ftp_put($this->conn(), $remote, $local, FTP_BINARY) || @ftp_size($this->conn(), $remote) !== (int) filesize($local)) {
            throw new RuntimeException('Envio de capa falhou: ' . basename($remote));
        }
    }

    private function replaceJson(string $remoteJson, string $bytes): void
    {
        $tmp = $remoteJson . '.tmp';
        $this->putBytes($tmp, $bytes);
        if (hash('sha256', $this->readBytes($tmp)) !== hash('sha256', $bytes)) {
            @ftp_delete($this->conn(), $tmp);
            throw new RuntimeException('Arquivo temporario remoto divergiu; JSON remoto nao foi trocado.');
        }
        if (!@ftp_rename($this->conn(), $tmp, $remoteJson)) {
            @ftp_delete($this->conn(), $tmp);
            throw new RuntimeException('Hospedagem recusou rename; JSON remoto nao foi trocado.');
        }
        if (hash('sha256', $this->readBytes($remoteJson)) !== hash('sha256', $bytes)) {
            throw new RuntimeException('JSON remoto nao confere apos a troca.');
        }
    }

    // ── Verificacao HTTP ─────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $feed
     * @return array{ok: bool, fatal: bool, message: string}
     */
    private function verifyHttp(array $feed): array
    {
        $base = self::PUBLIC_URLS[$this->target];
        $first = (array) (($feed['posts'] ?? [])[0] ?? []);
        $cover = (string) ($first['media_url'] ?? '');

        $home = $this->httpGet($base . '/?feedcheck=' . time());
        if ($home['code'] >= 500 || $home['code'] === 0) {
            return ['ok' => false, 'fatal' => true, 'message' => 'Home respondeu ' . $home['code'] . '.'];
        }
        $coverResponse = $this->httpGet($base . $cover . '?feedcheck=' . time());
        $coverOk = $coverResponse['code'] === 200
            && str_starts_with($coverResponse['type'], 'image/')
            && $coverResponse['size'] >= self::MIN_COVER_BYTES;
        $homeOk = $home['code'] === 200 && str_contains($home['body'], $cover);

        if ($homeOk && $coverOk) {
            return ['ok' => true, 'fatal' => false, 'message' => 'Home e capa do primeiro card verificadas.'];
        }

        return [
            'ok' => false,
            'fatal' => false,
            'message' => 'Verificacao HTTP pendente (home ' . $home['code'] . ', primeiro card '
                . ($homeOk ? 'ok' : 'ausente') . ', capa ' . $coverResponse['code'] . ').',
        ];
    }

    /**
     * @return array{code: int, type: string, size: int, body: string}
     */
    private function httpGet(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'EstrategiaNerd-FeedPublisher/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        $body = is_string($body) ? $body : '';

        return ['code' => $code, 'type' => strtolower($type), 'size' => strlen($body), 'body' => $body];
    }
}
