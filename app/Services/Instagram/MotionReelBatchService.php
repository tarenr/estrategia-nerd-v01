<?php

declare(strict_types=1);

namespace App\Services\Instagram;

use PDO;
use RuntimeException;
use Throwable;

/** Migracao explicita: nenhuma chamada a publicadores, APIs de musica ou captions. */
final class MotionReelBatchService
{
    private const POST_FIELDS = ['video_rendered_path', 'render_status', 'audio_duration_seconds', 'atualizado_em'];
    private const MEDIA_FIELDS = ['caminho', 'url_publica', 'largura', 'altura', 'duracao_s'];

    public function __construct(
        private readonly PDO $local,
        private readonly EditorialMotionReelRenderer $renderer,
        private readonly string $root,
    ) {}

    /** @return array{post:array<string,mixed>,media:list<array<string,mixed>>} */
    public function snapshot(int $id, bool $lock = false): array
    {
        $suffix = $lock && $this->local->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $s = $this->local->prepare('SELECT * FROM instagram_posts WHERE id = ?' . $suffix);
        $s->execute([$id]);
        $post = $s->fetch(PDO::FETCH_ASSOC);
        if (!is_array($post)) { throw new RuntimeException('Reel nao encontrado.'); }
        $s = $this->local->prepare('SELECT * FROM instagram_post_media WHERE post_id = ? ORDER BY ordem,id' . $suffix);
        $s->execute([$id]);
        return ['post' => $post, 'media' => $s->fetchAll(PDO::FETCH_ASSOC)];
    }

    /** @param array<string,mixed> $post */
    public static function eligibility(array $post, ?int $now = null): void
    {
        if (($post['tipo'] ?? '') !== 'reels' || ($post['origin'] ?? '') !== 'local'
            || !in_array($post['status'] ?? '', ['rascunho', 'agendado'], true)) {
            throw new RuntimeException('Registro fora do escopo: apenas Reels locais em rascunho ou agendados.');
        }
        if ($post['status'] === 'agendado') {
            $scheduled = strtotime((string) ($post['agendado_para'] ?? ''));
            // Nao modifica a agenda; a janela evita o publicador ler o snapshot antigo.
            if ($scheduled === false || $scheduled <= ($now ?? time()) + 900) {
                throw new RuntimeException('Agendamento vencido ou a menos de 15 minutos; item preservado.');
            }
        }
    }

    /** @return list<array<string,mixed>> */
    public function inventory(PDO $production): array
    {
        $ids = $this->local->query("SELECT id FROM instagram_posts WHERE tipo='reels' AND origin='local'
            AND status IN ('rascunho','agendado') AND post_blog_id IS NOT NULL ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        $items = [];
        foreach ($ids as $id) {
            $snapshot = $this->snapshot((int) $id);
            $post = $snapshot['post'];
            $item = ['id' => (int) $id, 'before' => $snapshot, 'state' => 'pending'];
            try {
                self::eligibility($post);
                $blogId = (int) $post['post_blog_id'];
                if ($blogId < 1 || ($post['idempotency_key'] ?? '') !== BlogCrosspostService::idempotencyKey('production', $blogId)) {
                    throw new RuntimeException('Vinculo de origem nao confirmado; nao usa artigo local como fallback.');
                }
                if (count($snapshot['media']) !== 1 || $snapshot['media'][0]['tipo_arquivo'] !== 'video') {
                    throw new RuntimeException('Esperada exatamente uma midia de video; composicao personalizada preservada.');
                }
                $previous = $this->asset((string) $snapshot['media'][0]['caminho']);
                $s = $production->prepare('SELECT id,titulo,resumo,categoria,imagem_capa FROM posts WHERE id=?');
                $s->execute([$blogId]);
                $article = $s->fetch(PDO::FETCH_ASSOC);
                if (!is_array($article)) { throw new RuntimeException('Artigo de origem ausente.'); }
                $s = $this->local->prepare('SELECT id,arquivo_path FROM instagram_audio_tracks WHERE id=?');
                $s->execute([(int) ($post['audio_track_id'] ?? 0)]);
                $track = $s->fetch(PDO::FETCH_ASSOC);
                if (!is_array($track)) { throw new RuntimeException('Trilha original ausente; nao escolhe outra musica.'); }
                $cover = $this->asset((string) $article['imagem_capa']);
                $audio = $this->asset((string) $track['arquivo_path']);
                (new SmartCanvasRenderer())->assertValidSource($cover);
                $seconds = $this->renderer->duration($article);
                $start = (int) ($post['audio_start_seconds'] ?? 0);
                $audioSpec = $this->renderer->probe($audio);
                if ($start < 0 || $start + $seconds > (float) ($audioSpec['format']['duration'] ?? 0)) {
                    throw new RuntimeException('Trilha no inicio escolhido insuficiente para a nova duracao.');
                }
                $item += ['environment' => 'production', 'article' => $article, 'track' => $track,
                    'previous_media_sha256' => hash_file('sha256', $previous),
                    'template' => EditorialMotionReelRenderer::templateKey((string) $article['categoria']),
                    'source_cover_sha256' => hash_file('sha256', $cover), 'source_audio_sha256' => hash_file('sha256', $audio),
                    'duration' => $seconds, 'audio_start' => $start];
                $item['state'] = 'inventoried';
            } catch (RuntimeException $e) {
                $item['state'] = 'skipped';
                $item['reason'] = $e->getMessage();
            }
            $items[] = $item;
        }
        return $items;
    }

    public function asset(string $relative): string
    {
        if ($relative === '' || str_contains($relative, '\\') || str_contains($relative, '..')
            || str_starts_with($relative, '/') || preg_match('~^[a-z]+:~i', $relative)) {
            throw new RuntimeException('Asset deve ser um caminho relativo seguro em public.');
        }
        $base = realpath($this->root . '/public');
        $path = realpath($this->root . '/public/' . $relative);
        if ($base === false || $path === false || !is_file($path)
            || !str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', $base) . '/')) {
            throw new RuntimeException('Asset local ausente ou fora de public.');
        }
        return $path;
    }

    /** @param array<string,mixed> $item */
    public function assertSources(array $item): void
    {
        $s = $this->local->prepare('SELECT id,arquivo_path FROM instagram_audio_tracks WHERE id=?');
        $s->execute([(int) $item['track']['id']]);
        if ($s->fetch(PDO::FETCH_ASSOC) !== $item['track']) { throw new RuntimeException('Cadastro da trilha original mudou.'); }
        if (($item['environment'] ?? '') !== 'production'
            || (int) $item['article']['id'] !== (int) $item['before']['post']['post_blog_id']
            || $item['before']['post']['idempotency_key'] !== BlogCrosspostService::idempotencyKey('production', (int) $item['article']['id'])
            || (int) $item['track']['id'] !== (int) $item['before']['post']['audio_track_id']
            || (int) $item['audio_start'] !== (int) $item['before']['post']['audio_start_seconds']
            || $item['source_cover_sha256'] !== hash_file('sha256', $this->asset((string) $item['article']['imagem_capa']))
            || $item['source_audio_sha256'] !== hash_file('sha256', $this->asset((string) $item['track']['arquivo_path']))) {
            throw new RuntimeException('Fontes ou vinculo mudaram; novo inventario necessario.');
        }
    }

    /** @param array<string,mixed> $item */
    public function assertArticle(PDO $production, array $item): void
    {
        $s = $production->prepare('SELECT id,titulo,resumo,categoria,imagem_capa FROM posts WHERE id=?');
        $s->execute([(int) $item['article']['id']]);
        if ($s->fetch(PDO::FETCH_ASSOC) !== $item['article']) {
            throw new RuntimeException('Artigo original mudou; refaca o inventario deste lote.');
        }
    }

    /** @param array<string,mixed> $manifest */
    public function assertVersion(array $manifest): void
    {
        if (($manifest['renderer'] ?? '') !== EditorialMotionReelRenderer::VERSION
            || ($manifest['renderer_sha256'] ?? '') !== hash_file('sha256', __DIR__ . '/EditorialMotionReelRenderer.php')
            || ($manifest['config_sha256'] ?? '') !== hash_file('sha256', $this->root . '/config/instagram-motion-templates.php')) {
            throw new RuntimeException('Renderer/configuracao mudou; prepare um novo lote.');
        }
    }

    /** @param array<string,mixed> $item @return array<string,mixed> */
    public function prepareItem(array $item, string $run): array
    {
        self::eligibility($this->snapshot((int) $item['id'])['post']);
        if ($this->snapshot((int) $item['id']) !== $item['before']) {
            throw new RuntimeException('Registro editado apos inventario; preservado.');
        }
        $this->assertSources($item);
        if (($item['state'] ?? '') === 'ready') {
            $path = $this->asset((string) $item['video']);
            if (hash_file('sha256', $path) !== $item['video_sha256']) { throw new RuntimeException('Video pronto alterado.'); }
            $this->renderer->assertVideo($path, (int) $item['duration']);
            return $item; // Retomada sem recomprimir.
        }
        if (!preg_match('/^batch-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}$/', $run)) { throw new RuntimeException('Run ID invalido.'); }
        // Tentativas com falha nunca sobrescrevem MP4/camadas parciais.
        $relative = 'uploads/reels/motion-batch/' . $run . '/reel-' . $item['id'] . '-article-'
            . $item['article']['id'] . '-' . bin2hex(random_bytes(4)) . '.mp4';
        $output = $this->root . '/public/' . $relative;
        $result = $this->renderer->render($this->asset((string) $item['article']['imagem_capa']),
            $this->asset((string) $item['track']['arquivo_path']), $item['article'], $output, (int) $item['audio_start']);
        $this->assertSources($item);
        $item['video'] = $relative;
        $item['duration'] = $result['duration'];
        $item['video_sha256'] = hash_file('sha256', $output);
        $sheet = imagecreatetruecolor(1350, 480);
        if (!$sheet instanceof \GdImage) { throw new RuntimeException('Falha ao iniciar contato visual do lote.'); }
        $times = [0.75, 4.6, 6.5, ($result['duration'] + 2) / 2 + 1, $result['duration'] - 1.5];
        foreach ($times as $i => $time) {
            $frame = dirname($output) . '/' . pathinfo($output, PATHINFO_FILENAME) . '-layers/qa-' . ($i + 1) . '.png';
            $this->renderer->capture($output, $time, $frame);
            $image = imagecreatefrompng($frame);
            if (!$image instanceof \GdImage) { throw new RuntimeException('Frame visual do lote ausente.'); }
            imagecopyresampled($sheet, $image, $i * 270, 0, 0, 0, 270, 480, 1080, 1920);
            imagedestroy($image);
        }
        $item['contact_sheet'] = dirname($relative) . '/' . pathinfo($relative, PATHINFO_FILENAME) . '-contact.png';
        if (!imagepng($sheet, $this->root . '/public/' . $item['contact_sheet'])) { throw new RuntimeException('Falha ao salvar contato visual.'); }
        imagedestroy($sheet);
        $item['state'] = 'ready';
        return $item;
    }

    /** @param array<string,mixed> $item @return array<string,mixed> */
    public function applyItem(array $item): array
    {
        $this->assertPrevious($item);
        $this->assertSources($item);
        $path = $this->asset((string) $item['video']);
        if (hash_file('sha256', $path) !== $item['video_sha256']) { throw new RuntimeException('Video diferente do aprovado.'); }
        $this->renderer->assertVideo($path, (int) $item['duration']);
        $this->local->beginTransaction();
        try {
            $current = $this->snapshot((int) $item['id'], true);
            self::eligibility($current['post']);
            if ($current !== $item['before']) { throw new RuntimeException('Registro mudou; aplicacao recusada.'); }
            $this->replace($item, (string) $item['video'], (int) $item['duration']);
            $after = $this->snapshot((int) $item['id']);
            $this->local->commit();
            return $after;
        } catch (Throwable $e) {
            $this->local->rollBack();
            throw $e;
        }
    }

    /** @param array<string,mixed> $item */
    private function replace(array $item, string $video, int $seconds): void
    {
        // Mantem o ID da midia; URL e recalculada pelo publicador existente.
        $s = $this->local->prepare('UPDATE instagram_post_media SET caminho=?,url_publica=NULL,largura=1080,altura=1920,duracao_s=? WHERE id=? AND post_id=?');
        $s->execute([$video, $seconds, $item['before']['media'][0]['id'], $item['id']]);
        $s = $this->local->prepare("UPDATE instagram_posts SET video_rendered_path=?,render_status='ready',audio_duration_seconds=?,atualizado_em=? WHERE id=?");
        $s->execute([$video, $seconds, $item['apply_time'] ?? date('Y-m-d H:i:s'), $item['id']]);
    }

    /** @param array<string,mixed> $item @return array<string,mixed> */
    public function expectedAfter(array $item): array
    {
        $after = $item['before'];
        $after['post']['video_rendered_path'] = $item['video'];
        $after['post']['render_status'] = 'ready';
        $after['post']['audio_duration_seconds'] = (int) $item['duration'];
        $after['post']['atualizado_em'] = $item['apply_time'];
        $after['media'][0]['caminho'] = $item['video'];
        $after['media'][0]['url_publica'] = null;
        $after['media'][0]['largura'] = 1080;
        $after['media'][0]['altura'] = 1920;
        $after['media'][0]['duracao_s'] = (int) $item['duration'];
        return $after;
    }

    /** @param array<string,mixed> $item @param array<string,mixed> $after */
    public function rollbackItem(array $item, array $after): void
    {
        $this->assertPrevious($item);
        $this->local->beginTransaction();
        try {
            $current = $this->snapshot((int) $item['id'], true);
            self::eligibility($current['post']);
            if ($current !== $after) { throw new RuntimeException('Estado posterior mudou; rollback recusado.'); }
            foreach ($item['before']['media'] as $media) {
                $sql = 'UPDATE instagram_post_media SET ' . implode(',', array_map(static fn (string $f): string => $f . '=?', self::MEDIA_FIELDS)) . ' WHERE id=? AND post_id=?';
                $values = array_map(static fn (string $f): mixed => $media[$f], self::MEDIA_FIELDS);
                $this->local->prepare($sql)->execute([...$values, $media['id'], $item['id']]);
            }
            $values = array_map(static fn (string $f): mixed => $item['before']['post'][$f], self::POST_FIELDS);
            $sql = 'UPDATE instagram_posts SET ' . implode(',', array_map(static fn (string $f): string => $f . '=?', self::POST_FIELDS)) . ' WHERE id=?';
            $this->local->prepare($sql)->execute([...$values, $item['id']]);
            $this->local->commit();
        } catch (Throwable $e) { $this->local->rollBack(); throw $e; }
    }

    /** @param array<string,mixed> $item */
    private function assertPrevious(array $item): void
    {
        if (($item['previous_media_sha256'] ?? '') !== hash_file('sha256', $this->asset((string) $item['before']['media'][0]['caminho']))) {
            throw new RuntimeException('Midia anterior ausente/alterada; backup nao e recuperavel com seguranca.');
        }
        $cached = (string) ($item['before']['post']['video_rendered_path'] ?? '');
        if ($cached !== '') { $this->asset($cached); }
    }

    /** @param array<string,mixed> $value */
    public static function save(string $path, array $value): void
    {
        // Rename no mesmo volume evita manifest parcialmente gravado.
        $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $path)) {
            throw new RuntimeException('Falha ao gravar manifesto/backup.');
        }
    }
}
