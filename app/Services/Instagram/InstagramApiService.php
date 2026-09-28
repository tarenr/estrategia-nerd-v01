<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Services/Instagram/InstagramApiService.php
 * @project     Estrategia Nerd
 * @purpose     Integração com a Meta Graph API v21.0 e helper local (FEAT-010)
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Services\Instagram;

use RuntimeException;

/**
 * Serviço de integração com a Meta Graph API v21.0 para o Instagram.
 *
 * Suporta fallback inteligente para o helper local (porta 58772) nas
 * operações de leitura de perfil e insights rápidos.
 */
final class InstagramApiService
{
    private const GRAPH_BASE     = 'https://graph.facebook.com/v21.0';
    private const HELPER_BASE    = 'http://127.0.0.1:58772';
    private const HELPER_TIMEOUT = 2;
    private const API_TIMEOUT    = 15;

    /** Limite da legenda conforme documentação da Meta */
    public const MAX_CAPTION_LENGTH   = 2200;
    /** Limite de hashtags por post */
    public const MAX_HASHTAG_COUNT    = 30;
    /** Máximo de mídias num carrossel */
    public const MAX_CAROUSEL_ITEMS   = 10;
    /** Mínimo de mídias num carrossel */
    public const MIN_CAROUSEL_ITEMS   = 2;


    public function __construct(
        private readonly string $accessToken,
        private readonly string $igUserId,
    ) {
    }

    // ── Perfil ────────────────────────────────────────────────────────────────

    /**
     * Retorna dados do perfil da conta Instagram.
     * Tenta o helper local primeiro; em caso de falha usa a Graph API.
     *
     * @return array<string,mixed>
     */
    public function getProfile(): array
    {
        $helperData = $this->fetchHelper('/profile');
        if ($helperData !== null) {
            return [
                'id'                  => (string) ($helperData['id'] ?? $this->igUserId),
                'username'            => (string) ($helperData['username'] ?? ''),
                'name'                => (string) ($helperData['name'] ?? ''),
                'biography'           => (string) ($helperData['biography'] ?? ''),
                'website'             => (string) ($helperData['website'] ?? ''),
                'followers_count'     => (int) ($helperData['followers'] ?? $helperData['followers_count'] ?? 0),
                'follows_count'       => (int) ($helperData['follows'] ?? $helperData['follows_count'] ?? 0),
                'media_count'         => (int) ($helperData['media'] ?? $helperData['media_count'] ?? 0),
                'profile_picture_url' => (string) ($helperData['profilePic'] ?? $helperData['profile_picture_url'] ?? ''),
            ];
        }

        return $this->graphGet("/{$this->igUserId}", [
            'fields' => 'id,username,name,biography,website,followers_count,follows_count,media_count,profile_picture_url',
        ]);
    }

    // ── Insights ──────────────────────────────────────────────────────────────

    /**
     * Retorna insights da conta para o período informado ('7d' ou '30d').
     * Tenta o helper local primeiro; em caso de falha usa a Graph API.
     *
     * @return array<string,mixed>
     */
    public function getInsights(string $period = '7d'): array
    {
        $helperData = $this->fetchHelper('/insights?period=' . urlencode($period));
        if ($helperData !== null) {
            $reach = isset($helperData['reach7d']) ? (int) $helperData['reach7d'] : 0;
            $profileViews = isset($helperData['profileViews7d']) ? (int) $helperData['profileViews7d'] : 0;
            return [
                'data' => [
                    ['name' => 'reach', 'values' => [['value' => $reach]]],
                    ['name' => 'impressions', 'values' => [['value' => null]]],
                    ['name' => 'profile_views', 'values' => [['value' => $profileViews]]],
                    ['name' => 'total_interactions', 'values' => [['value' => null]]],
                ],
                '_source' => 'helper',
            ];
        }

        $days  = $period === '30d' ? 30 : 7;
        $since = date('Y-m-d', strtotime("-{$days} days"));
        $until = date('Y-m-d');

        return $this->graphGet("/{$this->igUserId}/insights", [
            'metric'    => 'reach,impressions,profile_views,total_interactions',
            'period'    => 'day',
            'since'     => $since,
            'until'     => $until,
        ]);
    }

    // ── Mídia ─────────────────────────────────────────────────────────────────

    /**
     * Retorna as mídias mais recentes da conta.
     *
     * @return array<string,mixed>
     */
    public function getMediaFeed(int $limit = 12): array
    {
        return $this->graphGet("/{$this->igUserId}/media", [
            'fields' => 'id,media_type,media_url,thumbnail_url,permalink,caption,timestamp,like_count,comments_count',
            'limit'  => (string) $limit,
        ]);
    }

    /**
     * Retorna detalhes e insights de uma mídia específica.
     *
     * @return array<string,mixed>
     */
    public function getMediaDetails(string $mediaId): array
    {
        $media = $this->graphGet("/{$mediaId}", [
            'fields' => 'id,media_type,media_url,thumbnail_url,permalink,caption,timestamp,like_count,comments_count',
        ]);

        try {
            $insights = $this->graphGet("/{$mediaId}/insights", [
                'metric' => 'reach,impressions,saved,total_interactions',
            ]);
            $media['insights'] = $insights['data'] ?? [];
        } catch (RuntimeException) {
            $media['insights'] = [];
        }

        return $media;
    }

    /**
     * Retorna comentários de uma mídia específica.
     *
     * @return array<string,mixed>
     */
    public function getMediaComments(string $mediaId, int $limit = 20): array
    {
        return $this->graphGet("/{$mediaId}/comments", [
            'fields' => 'id,text,username,timestamp',
            'limit'  => (string) $limit,
        ]);
    }

    // ── Publicação ────────────────────────────────────────────────────────────

    /**
     * Cria um container de mídia (imagem, vídeo ou item de carrossel) na Meta.
     *
     * @param array<string,mixed> $params Parâmetros do container
     * @return string creation_id do container
     */
    public function createMediaContainer(array $params): string
    {
        $payload = array_merge(['access_token' => $this->accessToken], $params);
        $response = $this->graphPost("/{$this->igUserId}/media", $payload);
        $id = (string) ($response['id'] ?? '');

        if ($id === '') {
            throw new RuntimeException('Meta API não retornou um creation_id válido.');
        }

        return $id;
    }

    /**
     * Cria um container de imagem única ou Story (imagem).
     *
     * @param array<string,mixed> $extra Parâmetros adicionais (ex: is_carousel_item, media_type)
     * @return string creation_id
     */
    public function createImageContainer(string $imageUrl, ?string $caption, array $extra = []): string
    {
        $params = array_merge([
            'image_url' => $imageUrl,
        ], $extra);

        if (($params['media_type'] ?? '') !== 'STORIES' && $caption !== null && $caption !== '') {
            $params['caption'] = $caption;
        }

        return $this->createMediaContainer($params);
    }

    /**
     * Cria um container de vídeo (Reels ou Stories em vídeo) na Meta.
     *
     * @param array<string,mixed> $extra Parâmetros adicionais (ex: media_type => 'REELS' ou 'STORIES')
     * @return string creation_id retornado pela Meta
     */
    public function createVideoContainer(string $videoUrl, ?string $caption = null, array $extra = []): string
    {
        $mediaType = (string) ($extra['media_type'] ?? 'REELS');
        $params = array_merge([
            'media_type' => $mediaType,
            'video_url'  => $videoUrl,
        ], $extra);

        // Story não aceita legenda na Meta Graph API
        if ($mediaType === 'STORIES') {
            unset($params['caption']);
        } elseif ($caption !== null && $caption !== '') {
            $params['caption'] = $caption;
        }

        return $this->createMediaContainer($params);
    }

    /**
     * Cria um container de carrossel a partir de uma lista de creation_ids.
     *
     * @param list<string> $childrenIds IDs dos containers-filho
     * @return string creation_id do carrossel
     */
    public function createCarouselContainer(array $childrenIds, ?string $caption): string
    {
        if (count($childrenIds) < self::MIN_CAROUSEL_ITEMS) {
            throw new RuntimeException(
                'Carrossel requer no mínimo ' . self::MIN_CAROUSEL_ITEMS . ' mídias.'
            );
        }

        if (count($childrenIds) > self::MAX_CAROUSEL_ITEMS) {
            throw new RuntimeException(
                'Carrossel suporta no máximo ' . self::MAX_CAROUSEL_ITEMS . ' mídias.'
            );
        }

        return $this->createMediaContainer([
            'media_type' => 'CAROUSEL',
            'children'   => implode(',', $childrenIds),
            'caption'    => $caption,
        ]);
    }

    /**
     * Verifica o status de um container de mídia na Meta.
     * Retorna 'FINISHED', 'IN_PROGRESS', 'PUBLISHED', 'ERROR', etc.
     */
    public function checkContainerStatus(string $creationId): string
    {
        $data = $this->graphGet("/{$creationId}", ['fields' => 'status_code,status']);

        return (string) ($data['status_code'] ?? $data['status'] ?? 'UNKNOWN');
    }

    /**
     * Publica um container já criado na Meta.
     *
     * @return string ig_media_id da publicação
     */
    public function publishMedia(string $creationId): string
    {
        $response = $this->graphPost("/{$this->igUserId}/media_publish", [
            'creation_id'  => $creationId,
            'access_token' => $this->accessToken,
        ]);

        $id = (string) ($response['id'] ?? '');

        if ($id === '') {
            throw new RuntimeException('Meta API não retornou ig_media_id após publicação.');
        }

        return $id;
    }

    // ── Validação ─────────────────────────────────────────────────────────────

    /**
     * Valida a legenda: limite de 2.200 caracteres (mb_strlen) e 30 hashtags.
     *
     * @return array{ok:bool,errors:list<string>}
     */
    public function validateCaption(?string $caption): array
    {
        if ($caption === null || $caption === '') {
            return ['ok' => true, 'errors' => []];
        }

        $errors = [];
        $len = mb_strlen($caption, 'UTF-8');

        if ($len > self::MAX_CAPTION_LENGTH) {
            $errors[] = "Legenda muito longa ({$len} chars). Limite: " . self::MAX_CAPTION_LENGTH . '.';
        }

        preg_match_all('/#\w+/u', $caption, $matches);
        $hashtagCount = count($matches[0]);

        if ($hashtagCount > self::MAX_HASHTAG_COUNT) {
            $errors[] = "Muitas hashtags ({$hashtagCount}). Limite: " . self::MAX_HASHTAG_COUNT . '.';
        }

        return ['ok' => $errors === [], 'errors' => $errors];
    }

    /**
     * Conta hashtags em uma legenda.
     */
    public function countHashtags(?string $caption): int
    {
        if ($caption === null || $caption === '') {
            return 0;
        }

        preg_match_all('/#\w+/u', $caption, $matches);

        return count($matches[0]);
    }

    // ── HTTP Internos ─────────────────────────────────────────────────────────

    /**
     * GET na Graph API com parâmetros e access_token injetado automaticamente.
     *
     * @param array<string,string> $params
     * @return array<string,mixed>
     */
    private function graphGet(string $path, array $params = []): array
    {
        $params['access_token'] = $this->accessToken;
        $url = self::GRAPH_BASE . $path . '?' . http_build_query($params);

        return $this->httpGet($url, self::API_TIMEOUT);
    }

    /**
     * POST na Graph API.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function graphPost(string $path, array $payload): array
    {
        $url = self::GRAPH_BASE . $path;

        return $this->httpPost($url, $payload, self::API_TIMEOUT);
    }

    /**
     * Tenta buscar dados do helper local; retorna null em caso de falha.
     *
     * @return array<string,mixed>|null
     */
    private function fetchHelper(string $path): ?array
    {
        $url = self::HELPER_BASE . $path;

        try {
            $data = $this->httpGet($url, self::HELPER_TIMEOUT);

            return !empty($data) ? $data : null;
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * Executa um GET HTTP via cURL e decodifica o JSON retornado.
     *
     * @return array<string,mixed>
     */
    private function httpGet(string $url, int $timeout): array
    {
        $ch = curl_init();

        if ($ch === false) {
            throw new RuntimeException('Não foi possível inicializar cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $curlErr !== '') {
            throw new RuntimeException("cURL GET falhou [{$url}]: {$curlErr}");
        }

        /** @var array<string,mixed>|null $decoded */
        $decoded = json_decode((string) $body, true);

        if (!is_array($decoded)) {
            throw new RuntimeException("Resposta inválida da API [{$url}]: {$body}");
        }

        if (isset($decoded['error']) && is_array($decoded['error'])) {
            $msg = (string) ($decoded['error']['message'] ?? 'Erro desconhecido da Meta API');
            throw new RuntimeException("Meta API error [{$url}]: {$msg}");
        }

        if ($httpCode >= 400) {
            throw new RuntimeException("HTTP {$httpCode} em [{$url}]");
        }

        return $decoded;
    }

    /**
     * Executa um POST HTTP via cURL e decodifica o JSON retornado.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function httpPost(string $url, array $payload, int $timeout): array
    {
        $ch = curl_init();

        if ($ch === false) {
            throw new RuntimeException('Não foi possível inicializar cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $body     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $curlErr !== '') {
            throw new RuntimeException("cURL POST falhou [{$url}]: {$curlErr}");
        }

        /** @var array<string,mixed>|null $decoded */
        $decoded = json_decode((string) $body, true);

        if (!is_array($decoded)) {
            throw new RuntimeException("Resposta inválida da API POST [{$url}]: {$body}");
        }

        if (isset($decoded['error']) && is_array($decoded['error'])) {
            $msg = (string) ($decoded['error']['message'] ?? 'Erro desconhecido da Meta API');
            throw new RuntimeException("Meta API POST error [{$url}]: {$msg}");
        }

        if ($httpCode >= 400) {
            throw new RuntimeException("HTTP {$httpCode} em POST [{$url}]");
        }

        return $decoded;
    }
}
