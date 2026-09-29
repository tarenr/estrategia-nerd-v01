<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Services/Site/InstagramFeedService.php
 * @project     Estrategia Nerd
 * @purpose     Serviço de feed público do Instagram para o frontend (FEAT-010 #327)
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Services\Site;

use App\Repositories\InstagramPostRepository;
use Throwable;

final class InstagramFeedService
{
    private string $cachePath;

    public function __construct(
        private ?InstagramPostRepository $instagramPosts = null,
        ?string $cachePath = null
    ) {
        $this->cachePath = $cachePath ?? base_path('config/instagram-feed.json');
    }

    /**
     * Retorna os dados do feed do Instagram para exibição pública.
     * Tenta o banco local se o repositório estiver disponível; se falhar ou estiver ausente,
     * consome o cache serializado em storage/cache/instagram_feed.json.
     *
     * @param int $limit
     * @return array<string, mixed>|null
     */
    public function getFeed(int $limit = 18): ?array
    {
        try {
            // 1. Tentar ler do repositório local caso injetado
            if ($this->instagramPosts !== null) {
                $account = $this->instagramPosts->findActiveAccount();
                if ($account !== null) {
                    $accountId = (int) ($account['id'] ?? 0);
                    $published = $this->instagramPosts->listPublished($accountId, $limit);

                    if (!empty($published)) {
                        $feed = $this->formatFeedData($account, $published);
                        $this->saveCache($feed);
                        return $feed;
                    }
                }
            }

            // 2. Fallback para cache local/estático
            return $this->loadCache();
        } catch (Throwable $e) {
            error_log('Falha ao obter feed do Instagram para o site: ' . $e->getMessage());
            return $this->loadCache();
        }
    }

    /**
     * @param array<string, mixed> $account
     * @param array<int, array<string, mixed>> $posts
     * @return array<string, mixed>
     */
    private function formatFeedData(array $account, array $posts): array
    {
        $items = [];
        foreach ($posts as $post) {
            $medias = explode('|', (string) ($post['medias'] ?? ''));
            $firstMedia = trim($medias[0]);
            $mediaUrl = '';

            if ($firstMedia !== '') {
                if (str_starts_with($firstMedia, 'http://') || str_starts_with($firstMedia, 'https://')) {
                    $mediaUrl = $firstMedia;
                } else {
                    $mediaUrl = url('/' . ltrim($firstMedia, '/'));
                }
            }

            $items[] = [
                'id' => (int) ($post['id'] ?? 0),
                'tipo' => (string) ($post['tipo'] ?? 'imagem'),
                'legenda' => (string) ($post['legenda'] ?? ''),
                'permalink' => (string) ($post['permalink'] ?? 'https://www.instagram.com/' . ($account['username'] ?? 'estrategia_nerd')),
                'curtidas' => (int) ($post['curtidas'] ?? 0),
                'comentarios_count' => (int) ($post['comentarios_count'] ?? 0),
                'media_url' => $mediaUrl,
                'publicado_em' => (string) ($post['publicado_em'] ?? ''),
            ];
        }

        return [
            'username' => (string) ($account['username'] ?? 'estrategia_nerd'),
            'profile_picture' => (string) ($account['profile_picture'] ?? ''),
            'followers_count' => (int) ($account['followers_count'] ?? 0),
            'media_count' => (int) ($account['media_count'] ?? count($items)),
            'profile_url' => 'https://www.instagram.com/' . (string) ($account['username'] ?? 'estrategia_nerd'),
            'posts' => $items,
            'updated_at' => date('c'),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function saveCache(array $data): void
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return;
        }

        $targets = array_unique([
            $this->cachePath,
            base_path('config/instagram-feed.json'),
            base_path('storage/cache/instagram_feed.json'),
        ]);

        foreach ($targets as $target) {
            try {
                $dir = dirname($target);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                file_put_contents($target, $payload);
            } catch (Throwable) {
                // Silencioso em caso de permissão de escrita
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadCache(): ?array
    {
        $sources = array_unique([
            $this->cachePath,
            base_path('config/instagram-feed.json'),
            base_path('storage/cache/instagram_feed.json'),
        ]);

        foreach ($sources as $source) {
            try {
                if (is_file($source)) {
                    $raw = (string) file_get_contents($source);
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded) && !empty($decoded['posts'])) {
                        return $decoded;
                    }
                }
            } catch (Throwable) {
                // Silencioso
            }
        }

        return null;
    }
}
