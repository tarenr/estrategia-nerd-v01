<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Services/Instagram/InstagramMediaFitter.php
 * @project     Estrategia Nerd
 * @purpose     Ajustar com Smart Canvas (sem cortes e sem textos) as imagens de
 *              um post do Instagram que estejam fora do formato aceito (FEAT-010)
 * @notes       Instagram e local-only: usa o PDO local. Nunca altera o arquivo
 *              original; gera um arquivo novo em uploads/instagram/.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Services\Instagram;

use App\Repositories\InstagramPostRepository;
use PDO;
use RuntimeException;

final class InstagramMediaFitter
{
    public const FEED_MIN    = 0.8;
    public const FEED_MAX    = 1.91;
    public const STORY_RATIO = 0.5625;

    private const RATIO_TOLERANCE = 0.02;
    private const OUTPUT_REL_DIR  = 'uploads/instagram';

    public function __construct(
        private readonly PDO $localPdo,
        private readonly SmartCanvasRenderer $renderer,
        private readonly string $publicRoot,
        private readonly string $appUrl,
    ) {
    }

    public static function fromGlobals(): self
    {
        /** @var PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        return new self($pdo, new SmartCanvasRenderer(), base_path('public'), rtrim((string) config('app.url', ''), '/'));
    }

    /**
     * Formato de saida por item (null = nao mexer), a partir das proporcoes (largura/altura).
     * Itens com proporcao null (video ou arquivo nao local) nunca sao ajustados.
     *
     * @param array<int|string,float|null> $ratios
     * @return array<int|string,array{0:int,1:int}|null>
     */
    public static function targets(string $tipo, array $ratios): array
    {
        $result = array_fill_keys(array_keys($ratios), null);
        $images = array_filter($ratios, static fn ($r): bool => $r !== null && $r > 0);

        if ($tipo === 'reels' || $images === []) {
            return $result;
        }

        if ($tipo === 'story') {
            foreach ($images as $key => $r) {
                if (abs($r - self::STORY_RATIO) > self::RATIO_TOLERANCE) {
                    $result[$key] = [1080, 1920];
                }
            }

            return $result;
        }

        if ($tipo === 'carrossel') {
            $outOfRange = false;
            foreach ($images as $r) {
                if ($r < self::FEED_MIN || $r > self::FEED_MAX) {
                    $outOfRange = true;
                }
            }
            $mixed = (max($images) - min($images)) > self::RATIO_TOLERANCE;

            // O Instagram corta todo o carrossel na proporcao do primeiro item: com
            // proporcoes diferentes ou fora da faixa, tudo vira quadrado sem cortes.
            if ($outOfRange || $mixed) {
                foreach ($images as $key => $r) {
                    if (abs($r - 1.0) > self::RATIO_TOLERANCE) {
                        $result[$key] = [1080, 1080];
                    }
                }
            }

            return $result;
        }

        foreach ($images as $key => $r) {
            if ($r < self::FEED_MIN) {
                $result[$key] = [1080, 1350];
            } elseif ($r > self::FEED_MAX) {
                $result[$key] = [1080, 1080];
            }
        }

        return $result;
    }

    /**
     * Ajusta o conjunto final de midias do post. Gera todos os arquivos antes e so
     * entao atualiza o banco numa transacao; em falha desfaz e apaga o que gerou.
     *
     * @return array{ok:bool,ajustadas:int,erro:?string}
     */
    public function fitPost(int $postId, string $tipo): array
    {
        $repo   = new InstagramPostRepository($this->localPdo);
        $medias = $repo->findMediaByPostId($postId);

        $ratios = [];
        $paths  = [];
        foreach ($medias as $media) {
            $id = (int) ($media['id'] ?? 0);
            $ratios[$id] = null;
            if ((string) ($media['tipo_arquivo'] ?? 'imagem') !== 'imagem') {
                continue;
            }

            $path = $this->resolveLocalUpload((string) ($media['caminho'] ?? ''));
            if ($path === null) {
                continue;
            }

            try {
                [$w, $h] = $this->renderer->effectiveSize($path);
            } catch (RuntimeException $e) {
                error_log('[InstagramMediaFitter] midia ' . $id . ' ignorada: ' . $e->getMessage());
                continue;
            }

            $ratios[$id] = $w / max(1, $h);
            $paths[$id]  = $path;
        }

        $targets = array_filter(self::targets($tipo, $ratios));
        if ($targets === []) {
            return ['ok' => true, 'ajustadas' => 0, 'erro' => null];
        }

        $generated = [];
        try {
            foreach ($targets as $id => [$width, $height]) {
                $relative = self::OUTPUT_REL_DIR . '/' . bin2hex(random_bytes(16)) . '.jpg';
                $absolute = $this->absolute($relative);
                $this->renderer->render($paths[$id], $absolute, $width, $height, false);
                $generated[$id] = ['rel' => $relative, 'abs' => $absolute, 'w' => $width, 'h' => $height];
            }

            $this->localPdo->beginTransaction();
            foreach ($generated as $id => $file) {
                $repo->updateMediaFile((int) $id, $file['rel'], $this->appUrl . '/' . $file['rel'], $file['w'], $file['h']);
            }
            $this->localPdo->commit();
        } catch (\Throwable $e) {
            if ($this->localPdo->inTransaction()) {
                $this->localPdo->rollBack();
            }
            foreach ($generated as $file) {
                @unlink($file['abs']);
            }
            error_log('[InstagramMediaFitter] post ' . $postId . ': ' . $e->getMessage());

            return ['ok' => false, 'ajustadas' => 0, 'erro' => 'Nao foi possivel ajustar as imagens com o Smart Canvas. As imagens originais foram mantidas.'];
        }

        return ['ok' => true, 'ajustadas' => count($generated), 'erro' => null];
    }

    /**
     * Resolve caminho/URL para um arquivo real dentro de public/uploads (sem traversal).
     * URLs de outro host (ex.: CDN da Meta) nao sao locais e ficam de fora.
     */
    private function resolveLocalUpload(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (preg_match('~^https?://~i', $raw) === 1) {
            $host    = parse_url($raw, PHP_URL_HOST);
            $appHost = parse_url($this->appUrl, PHP_URL_HOST);
            if (!is_string($host) || !is_string($appHost) || strcasecmp($host, $appHost) !== 0) {
                return null;
            }
        }

        $path   = parse_url($raw, PHP_URL_PATH);
        $path   = ltrim(is_string($path) ? $path : $raw, '/\\');
        $marker = strpos($path, 'uploads/');
        if ($marker === false) {
            return null;
        }
        $path = substr($path, $marker);
        if (str_contains($path, '..') || str_contains($path, "\0")) {
            return null;
        }

        $uploadsReal = realpath($this->publicRoot . DIRECTORY_SEPARATOR . 'uploads');
        $targetReal  = realpath($this->absolute($path));
        if ($uploadsReal === false || $targetReal === false || !is_file($targetReal)) {
            return null;
        }

        return str_starts_with($targetReal, $uploadsReal . DIRECTORY_SEPARATOR) ? $targetReal : null;
    }

    private function absolute(string $relative): string
    {
        return $this->publicRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relative, '/\\'));
    }
}
