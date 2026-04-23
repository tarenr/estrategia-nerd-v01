<?php
declare(strict_types=1);

namespace App\Services\Site;

use App\Repositories\CategoriaPostRepository;
use App\Repositories\PostRepository;
use PDO;

final class SitemapCacheService
{
    private const CACHE_RELATIVE_PATH = 'storage/cache/seo/sitemap.xml';
    private const META_RELATIVE_PATH = 'storage/cache/seo/sitemap.meta.json';

    public function __construct(
        private SitemapService $sitemap,
        private string $cachePath,
        private string $metaPath,
    ) {
    }

    public static function fromGlobals(): self
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!$pdo instanceof PDO) {
            throw new \RuntimeException('PDO global nao disponivel para o cache do sitemap.');
        }

        return new self(
            new SitemapService(
                new PostRepository($pdo),
                new CategoriaPostRepository($pdo),
            ),
            \base_path(self::CACHE_RELATIVE_PATH),
            \base_path(self::META_RELATIVE_PATH),
        );
    }

    public function currentXml(): string
    {
        $signature = $this->sitemap->fingerprint();
        $cached = $this->readCacheIfFresh($signature);
        if ($cached !== null) {
            return $cached;
        }

        return $this->refresh($signature);
    }

    public function refreshQuietly(): void
    {
        try {
            $this->refresh();
        } catch (\Throwable) {
        }
    }

    public function refresh(?string $signature = null): string
    {
        $xml = $this->sitemap->renderXml();
        $this->ensureDirectory(dirname($this->cachePath));
        $this->atomicWrite($this->cachePath, $xml);
        $this->atomicWrite(
            $this->metaPath,
            (string) json_encode([
                'signature' => $signature ?? $this->sitemap->fingerprint(),
                'generated_at' => date('c'),
                'cache_path' => $this->cachePath,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        return $xml;
    }

    private function readCacheIfFresh(string $signature): ?string
    {
        if (!is_file($this->cachePath) || !is_file($this->metaPath)) {
            return null;
        }

        $metaRaw = @file_get_contents($this->metaPath);
        $meta = is_string($metaRaw) && $metaRaw !== ''
            ? json_decode($metaRaw, true)
            : null;
        if (!is_array($meta) || (string) ($meta['signature'] ?? '') !== $signature) {
            return null;
        }

        $xml = @file_get_contents($this->cachePath);
        if (!is_string($xml) || trim($xml) === '') {
            return null;
        }

        return $xml;
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Nao foi possivel preparar o diretorio do cache do sitemap.');
        }
    }

    private function atomicWrite(string $path, string $content): void
    {
        $tmpPath = $path . '.tmp';
        if (@file_put_contents($tmpPath, $content, LOCK_EX) === false) {
            throw new \RuntimeException('Nao foi possivel escrever o cache do sitemap.');
        }

        if (!@rename($tmpPath, $path)) {
            @unlink($path);
            if (!@rename($tmpPath, $path)) {
                @unlink($tmpPath);
                throw new \RuntimeException('Nao foi possivel finalizar o cache do sitemap.');
            }
        }
    }
}
