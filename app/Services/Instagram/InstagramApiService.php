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
    private const PUBLISH_TIMEOUT = 45;
    private const CONNECT_TIMEOUT = 5;

    /** Limite da legenda conforme documentação da Meta */
    public const MAX_CAPTION_LENGTH   = 2200;
    /** Limite de hashtags por post */
    public const MAX_HASHTAG_COUNT    = 30;
    /** Máximo de mídias num carrossel */
    public const MAX_CAROUSEL_ITEMS   = 10;
    /** Mínimo de mídias num carrossel */
    public const MIN_CAROUSEL_ITEMS   = 2;


    /** @param (\Closure(string,string,array<string,mixed>,int,int): array{body:string|false,http_code:int,error:string,errno:int})|null $httpTransport */
    public function __construct(
        private readonly string $accessToken,
        private readonly string $igUserId,
        private readonly ?\Closure $httpTransport = null,
    ) {
    }

    // ── Perfil ────────────────────────────────────────────────────────────────

    /**
     * Retorna dados do perfil da conta Instagram.
     * Prioriza a Meta Graph API v21.0 oficial (dados autoritativos com bio e website);
     * recorre ao helper local apenas como contingência se a Graph API falhar.
     *
     * @return array<string,mixed>
     */
    public function getProfile(): array
    {
        if ($this->accessToken !== '' && $this->igUserId !== '') {
            try {
                return $this->graphGet("/{$this->igUserId}", [
                    'fields' => 'id,username,name,biography,website,followers_count,follows_count,media_count,profile_picture_url',
                ]);
            } catch (RuntimeException $e) {
                // Tenta o helper local abaixo caso a chamada oficial falhe
            }
        }

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

        throw new RuntimeException('Não foi possível obter os dados do perfil nem via Graph API nem via helper local.');
    }

    // ── Insights ──────────────────────────────────────────────────────────────

    /**
     * Retorna insights da conta para o período ou datas informadas.
     * Suporta atalhos ('7d', '14d', '30d', '90d') ou timestamps $since e $until.
     *
     * @param string|int $since Atalho ('7d', '30d', etc.) ou timestamp inicial
     * @param int|null   $until Timestamp final (padrão agora)
     * @return array<string,mixed>
     */
    public function getInsights(string|int $since = '7d', ?int $until = null): array
    {
        $now = time();
        $minSince = strtotime('-90 days', $now); // Limite da Meta Graph API

        if (is_string($since) && preg_match('/^(\d+)d$/', $since, $m)) {
            $days = min(90, max(1, (int) $m[1]));
            $sinceTs = strtotime("-{$days} days", $now);
            $untilTs = $now;
        } elseif (is_numeric($since)) {
            $sinceTs = (int) $since;
            $untilTs = $until !== null ? (int) $until : $now;
        } else {
            $parsed = strtotime((string) $since);
            $sinceTs = $parsed !== false ? $parsed : strtotime('-7 days', $now);
            $untilTs = $until !== null ? (int) $until : $now;
        }

        // Clamping estrito contra limites da Meta
        if ($sinceTs < $minSince) {
            $sinceTs = $minSince;
        }
        if ($untilTs > $now) {
            $untilTs = $now;
        }
        if ($sinceTs > $untilTs) {
            [$sinceTs, $untilTs] = [$untilTs, $sinceTs];
        }

        // A Meta não permite mais de 30 dias (2592000s) entre since e until em uma única consulta
        $maxWindow = 30 * 86400;
        if (($untilTs - $sinceTs) > $maxWindow) {
            $sinceTs = $untilTs - $maxWindow;
        }

        // Se for exatamente 7d e helper local responder, usa o helper como cache rápido
        $isExactly7d = ($since === '7d' || (abs($untilTs - $sinceTs) >= 6 * 86400 && abs($untilTs - $sinceTs) <= 8 * 86400));
        if ($isExactly7d) {
            $helperData = $this->fetchHelper('/insights?period=7d');
            if ($helperData !== null) {
                $reach = isset($helperData['reach7d']) ? (int) $helperData['reach7d'] : 0;
                $profileViews = isset($helperData['profileViews7d']) ? (int) $helperData['profileViews7d'] : 0;
                return [
                    'data' => [
                        ['name' => 'reach', 'total_value' => ['value' => $reach]],
                        ['name' => 'views', 'total_value' => ['value' => 0]],
                        ['name' => 'profile_views', 'total_value' => ['value' => $profileViews]],
                        ['name' => 'total_interactions', 'total_value' => ['value' => 0]],
                    ],
                    '_source' => 'helper',
                    'since' => $sinceTs,
                    'until' => $untilTs,
                ];
            }
        }

        // Meta Graph API v21.0: exige views e metric_type=total_value
        $res = $this->graphGet("/{$this->igUserId}/insights", [
            'metric'      => 'reach,views,profile_views,total_interactions',
            'metric_type' => 'total_value',
            'period'      => 'day',
            'since'       => (string) $sinceTs,
            'until'       => (string) $untilTs,
        ]);

        $res['since'] = $sinceTs;
        $res['until'] = $untilTs;

        return $res;
    }

    // ── Mídia ─────────────────────────────────────────────────────────────────

    /**
     * Retorna mídias da conta com suporte a paginação para recuperar todo o feed.
     *
     * @param int $limit Limite máximo de mídias (0 = todas as mídias disponíveis)
     * @return array{data: list<array<string,mixed>>, total: int, is_partial: bool}
     */
    public function getMediaFeed(int $limit = 0): array
    {
        $allData = [];
        $fields  = 'id,media_type,media_url,thumbnail_url,permalink,caption,timestamp,like_count,comments_count';
        $pageSize = ($limit > 0 && $limit < 100) ? $limit : 100;
        $params  = [
            'fields' => $fields,
            'limit'  => (string) $pageSize,
        ];

        $page = $this->graphGet("/{$this->igUserId}/media", $params);
        $items = (array) ($page['data'] ?? []);
        $seenIds = [];

        foreach ($items as $item) {
            if (is_array($item) && isset($item['id'])) {
                $id = (string) $item['id'];
                if (!isset($seenIds[$id])) {
                    $seenIds[$id] = true;
                    $allData[] = $item;
                }
            }
        }

        $pageCount = 1;
        $maxPages = 50; // Proteção contra loops infinitos (até 5000 posts)
        $isPartial = false;

        while ((!empty($page['paging']['next'])) && ($limit === 0 || count($allData) < $limit) && $pageCount < $maxPages) {
            $nextUrl = (string) $page['paging']['next'];
            $parsed = parse_url($nextUrl);
            if (!isset($parsed['host']) || !str_ends_with($parsed['host'], 'facebook.com')) {
                break;
            }

            try {
                $page = $this->httpGet($nextUrl, self::API_TIMEOUT);
                $items = (array) ($page['data'] ?? []);
                if (empty($items)) {
                    break;
                }

                $pageCount++;
                foreach ($items as $item) {
                    if (is_array($item) && isset($item['id'])) {
                        $id = (string) $item['id'];
                        if (!isset($seenIds[$id])) {
                            $seenIds[$id] = true;
                            $allData[] = $item;
                            if ($limit > 0 && count($allData) >= $limit) {
                                break;
                            }
                        }
                    }
                }
            } catch (RuntimeException) {
                $isPartial = true;
                break;
            }
        }

        return [
            'data'       => $allData,
            'total'      => count($allData),
            'is_partial' => $isPartial,
        ];
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
     * Monta a URL pública acessível para envio de mídias à Meta API.
     *
     * - Caminho relativo: base pública configurada + '/' + caminho.
     * - URL absoluta https://: mantida sem alteração.
     * - URL legada apontando para localhost/127.0.0.1: extrai o caminho relativo e aplica a base pública.
     *
     * Valida a base pública contra localhost/127.0.0.1 ou valor vazio.
     *
     * @param string $pathOrUrl Caminho relativo do arquivo (ex.: uploads/reels/...) ou URL pública existente
     * @return string URL pública formatada para a Meta
     * @throws RuntimeException se a base configurada for vazia ou apontar para localhost/127.0.0.1
     */
    public static function buildPublicMediaUrl(string $pathOrUrl): string
    {
        $raw = trim($pathOrUrl);
        if ($raw === '') {
            throw new RuntimeException('Caminho ou URL da mídia não pode ser vazio para envio à Meta.');
        }

        // Se já for uma URL HTTPS externa/absoluta válida, retorna como está
        if (preg_match('#^https://#i', $raw) === 1) {
            return $raw;
        }

        // Se for uma URL legada local (http://localhost... ou http://127.0.0.1...), extrai o caminho relativo
        if (preg_match('#^https?://(?:localhost|127\.0\.0\.1)(?::\d+)?(?:/[^/]+)?/(uploads/.*)$#i', $raw, $matches) === 1) {
            $raw = $matches[1];
        }

        $base = trim((string) config('instagram.public_media_url', 'https://nerd.tfr-info.com.br'));

        $host = parse_url($base, PHP_URL_HOST);
        $hostLower = is_string($host) ? strtolower($host) : '';

        if (
            $base === ''
            || $hostLower === ''
            || $hostLower === 'localhost'
            || str_ends_with($hostLower, '.localhost')
            || $hostLower === '127.0.0.1'
            || str_starts_with($hostLower, '127.')
            || $hostLower === '::1'
        ) {
            throw new RuntimeException(
                "Endereço público das mídias não configurado para envio à Meta (base inválida, vazia ou aponta para localhost: '{$base}'). Configure a variável INSTAGRAM_PUBLIC_MEDIA_URL no ambiente."
            );
        }

        return rtrim($base, '/') . '/' . ltrim($raw, '/');
    }

    /**
     * Parâmetro de capa do Reels: a capa .jpg gerada ao lado do MP4, com o mesmo nome.
     *
     * Sem `cover_url`, a Meta usa o primeiro quadro do vídeo (thumb_offset 0), que nos
     * Reels animados é só o fundo, antes de o título e a imagem entrarem.
     * Sem uma capa JPEG válida dentro de public/, devolve [] e a Meta mantém o primeiro quadro.
     *
     * @param string $videoPath Caminho relativo do MP4 (ex.: uploads/reels/...)
     * @return array{cover_url?: string}
     */
    public static function reelCoverParams(string $videoPath, string $publicRoot): array
    {
        $videoRel = ltrim(str_replace('\\', '/', trim($videoPath)), '/');
        if ($videoRel === '' || preg_match('#^[a-z][a-z0-9+.-]*://#i', $videoRel) === 1) {
            return [];
        }
        $coverRel = (string) preg_replace('#\.mp4$#i', '.jpg', $videoRel);
        if ($coverRel === $videoRel) {
            return [];
        }

        $root = realpath($publicRoot);
        $full = realpath($publicRoot . '/' . $coverRel);
        if ($root === false || $full === false || !is_file($full) || filesize($full) === 0) {
            return [];
        }
        $prefix = strtolower(str_replace('\\', '/', $root)) . '/';
        if (!str_starts_with(strtolower(str_replace('\\', '/', $full)), $prefix)) {
            return [];
        }
        $info = @getimagesize($full);
        if (!is_array($info) || $info[2] !== IMAGETYPE_JPEG) {
            return [];
        }

        return ['cover_url' => self::buildPublicMediaUrl($coverRel)];
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
        $response = $this->httpPost(self::GRAPH_BASE . "/{$this->igUserId}/media_publish", [
            'creation_id'  => $creationId,
            'access_token' => $this->accessToken,
        ], self::PUBLISH_TIMEOUT);

        $id = (string) ($response['id'] ?? '');

        if ($id === '' || !ctype_digit($id) || $id === $creationId) {
            throw new InstagramPublishOutcomeUnknownException('Publicação enviada, mas a Meta não retornou um ID de mídia válido. Não reenviar.');
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
        $result = $this->performHttpRequest('GET', $url, [], $timeout);
        $body = $result['body']; $httpCode = $result['http_code']; $curlErr = $result['error'];

        $safeUrl = $this->sanitizeUrl($url);

        if ($body === false || $curlErr !== '') {
            throw new RuntimeException("cURL GET falhou [{$safeUrl}]: {$curlErr}");
        }

        /** @var array<string,mixed>|null $decoded */
        $decoded = json_decode((string) $body, true);

        if (!is_array($decoded)) {
            throw new RuntimeException("Resposta inválida da API [{$safeUrl}]: {$body}");
        }

        if (isset($decoded['error']) && is_array($decoded['error'])) {
            $msg = (string) ($decoded['error']['message'] ?? 'Erro desconhecido da Meta API');
            throw new RuntimeException("Meta API error [{$safeUrl}]: {$msg}");
        }

        if ($httpCode >= 400) {
            throw new RuntimeException("HTTP {$httpCode} em [{$safeUrl}]");
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
        $isPublish = str_ends_with($url, '/media_publish');
        try { $result = $this->performHttpRequest('POST', $url, $payload, $timeout); }
        catch (\Throwable $e) {
            if ($isPublish) { throw new InstagramPublishOutcomeUnknownException('Resultado da publicação desconhecido; não reenviar.'); }
            throw $e;
        }
        $body = $result['body']; $httpCode = $result['http_code']; $curlErr = $result['error'];

        if ($isPublish) {
            $decoded = is_string($body) ? json_decode($body, true) : null;
            if ($body === false || $curlErr !== '' || $httpCode < 200 || $httpCode >= 500 || !is_array($decoded)) {
                throw new InstagramPublishOutcomeUnknownException('Resposta de publicação inconclusiva (HTTP ' . $httpCode . ', cURL ' . $result['errno'] . '); aguardando confirmação.');
            }
            if ($httpCode >= 300 || isset($decoded['error'])) {
                $error = $decoded['error'] ?? [];
                if ($httpCode >= 400 && is_array($error)
                    && empty($error['is_transient']) && in_array((int) ($error['code'] ?? 0), [10, 190, 200], true)) {
                    throw new RuntimeException('Meta rejeitou a publicação por autenticação/permissão (código ' . (int) $error['code'] . ').');
                }
                throw new InstagramPublishOutcomeUnknownException('A resposta da Meta exige confirmação antes de qualquer novo envio.');
            }
            return $decoded;
        }

        $safeUrl = $this->sanitizeUrl($url);

        if ($body === false || $curlErr !== '') {
            throw new RuntimeException("cURL POST falhou [{$safeUrl}]: {$curlErr}");
        }

        /** @var array<string,mixed>|null $decoded */
        $decoded = json_decode((string) $body, true);

        if (!is_array($decoded)) {
            throw new RuntimeException("Resposta inválida da API POST [{$safeUrl}]: {$body}");
        }

        if (isset($decoded['error']) && is_array($decoded['error'])) {
            $msg = (string) ($decoded['error']['message'] ?? 'Erro desconhecido da Meta API');
            throw new RuntimeException("Meta API POST error [{$safeUrl}]: {$msg}");
        }

        if ($httpCode >= 400) {
            throw new RuntimeException("HTTP {$httpCode} em POST [{$safeUrl}]");
        }

        return $decoded;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{body:string|false,http_code:int,error:string,errno:int}
     */
    private function performHttpRequest(string $method, string $url, array $payload, int $timeout): array
    {
        if ($this->httpTransport !== null) { return ($this->httpTransport)($method, $url, $payload, $timeout, self::CONNECT_TIMEOUT); }
        $ch = curl_init();
        if ($ch === false) { throw new RuntimeException('Não foi possível inicializar cURL.'); }
        $options = [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT, CURLOPT_HTTPHEADER => ['Accept: application/json']];
        if ($method === 'POST') { $options += [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($payload)]; }
        else { $options += [CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3]; }
        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $result = ['body' => is_string($body) ? $body : false, 'http_code' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'error' => curl_error($ch), 'errno' => curl_errno($ch)];
        curl_close($ch);
        return $result;
    }

    /**
     * Remove tokens de acesso de URLs para evitar vazamento em logs e mensagens de erro.
     */
    private function sanitizeUrl(string $url): string
    {
        return (string) preg_replace('/([?&]access_token=)[^&]+/i', '$1[REDACTED]', $url);
    }
}
