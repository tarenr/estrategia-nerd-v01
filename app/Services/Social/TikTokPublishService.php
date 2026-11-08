<?php

declare(strict_types=1);

namespace App\Services\Social;

use Closure;
use RuntimeException;
use Throwable;

/**
 * Serviço de integração com a TikTok Content Posting API v2.
 *
 * Suporta inicialização de publicação via PULL_FROM_URL (vídeos verticais do Echo 1080x1920),
 * polling assíncrono de status de processamento e renovação de OAuth token.
 * Permite injeção de $httpTransport para testes unitários determinísticos isolados.
 */
final class TikTokPublishService
{
    public const API_BASE = 'https://open.tiktokapis.com/v2';
    public const DEFAULT_TIMEOUT = 30;
    public const CONNECT_TIMEOUT = 5;

    public const PRIVACY_PUBLIC_TO_EVERYONE = 'PUBLIC_TO_EVERYONE';
    public const PRIVACY_MUTUAL_FOLLOW_FRIENDS = 'MUTUAL_FOLLOW_FRIENDS';
    public const PRIVACY_FOLLOWER_OF_CREATOR = 'FOLLOWER_OF_CREATOR';
    public const PRIVACY_SELF_ONLY = 'SELF_ONLY';

    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_PROCESSING_DOWNLOAD = 'PROCESSING_DOWNLOAD';
    public const STATUS_PROCESSING_UPLOAD = 'PROCESSING_UPLOAD';

    /**
     * @param string $accessToken Token OAuth de acesso da conta TikTok
     * @param (Closure(string,string,array<string,string>,string,int,int): array{body:string|false,http_code:int,error:string,errno:int})|null $httpTransport
     */
    public function __construct(
        private readonly string $accessToken,
        private readonly ?Closure $httpTransport = null,
    ) {
    }

    /**
     * Inicia a publicação de um vídeo vertical na API v2 da TikTok (PULL_FROM_URL).
     *
     * @param string $videoUrl URL pública HTTPS do vídeo MP4 (ex.: 1080x1920)
     * @param string $title Título / legenda do vídeo (máximo 2200 caracteres)
     * @param array<string,mixed> $options Opções adicionais (privacy_level, disable_comment, etc.)
     * @return array{publish_id: string, upload_url: string|null, raw: array<string,mixed>}
     */
    public function initVideoPublish(string $videoUrl, string $title, array $options = []): array
    {
        $videoUrl = trim($videoUrl);
        if ($videoUrl === '' || !preg_match('#^https://#i', $videoUrl)) {
            throw new RuntimeException('URL do vídeo deve ser HTTPS absoluta e não vazia para o TikTok.');
        }

        $privacy = (string) ($options['privacy_level'] ?? self::PRIVACY_PUBLIC_TO_EVERYONE);
        $validPrivacies = [
            self::PRIVACY_PUBLIC_TO_EVERYONE,
            self::PRIVACY_MUTUAL_FOLLOW_FRIENDS,
            self::PRIVACY_FOLLOWER_OF_CREATOR,
            self::PRIVACY_SELF_ONLY,
        ];

        if (!in_array($privacy, $validPrivacies, true)) {
            throw new RuntimeException("Nível de privacidade inválido para TikTok: {$privacy}");
        }

        $payload = [
            'post_info' => [
                'title'                    => mb_substr($title, 0, 2200, 'UTF-8'),
                'privacy_level'            => $privacy,
                'disable_duet'             => (bool) ($options['disable_duet'] ?? false),
                'disable_stitch'           => (bool) ($options['disable_stitch'] ?? false),
                'disable_comment'          => (bool) ($options['disable_comment'] ?? false),
                'video_cover_timestamp_ms' => max(0, (int) ($options['video_cover_timestamp_ms'] ?? 1000)),
                'brand_content_toggle'     => (bool) ($options['brand_content_toggle'] ?? false),
                'brand_organic_toggle'     => (bool) ($options['brand_organic_toggle'] ?? false),
            ],
            'source_info' => [
                'source'    => 'PULL_FROM_URL',
                'video_url' => $videoUrl,
            ],
        ];

        $response = $this->requestJson('POST', self::API_BASE . '/post/publish/video/init/', $payload);

        $data = (array) ($response['data'] ?? []);
        $publishId = (string) ($data['publish_id'] ?? '');

        if ($publishId === '') {
            $errMsg = (string) ($response['error']['message'] ?? 'Resposta da TikTok não contém publish_id');
            throw new RuntimeException("Falha ao inicializar vídeo no TikTok: {$errMsg}");
        }

        return [
            'publish_id' => $publishId,
            'upload_url' => isset($data['upload_url']) && is_string($data['upload_url']) ? $data['upload_url'] : null,
            'raw'        => $response,
        ];
    }

    /**
     * Consulta o status atual de processamento do vídeo no TikTok.
     *
     * @param string $publishId ID retornado em initVideoPublish
     * @return array{status: string, post_id: string|null, fail_reason: string|null, raw: array<string,mixed>}
     */
    public function fetchPublishStatus(string $publishId): array
    {
        $publishId = trim($publishId);
        if ($publishId === '') {
            throw new RuntimeException('publish_id não pode ser vazio para consulta de status.');
        }

        $payload = [
            'publish_id' => $publishId,
        ];

        $response = $this->requestJson('POST', self::API_BASE . '/post/publish/status/fetch/', $payload);

        $data = (array) ($response['data'] ?? []);
        $status = (string) ($data['status'] ?? 'UNKNOWN');
        $failReason = isset($data['fail_reason']) && is_string($data['fail_reason']) ? $data['fail_reason'] : null;

        $postId = null;
        if (isset($data['publicaly_available_post_id']) && is_array($data['publicaly_available_post_id']) && !empty($data['publicaly_available_post_id'])) {
            $postId = (string) $data['publicaly_available_post_id'][0];
        }

        return [
            'status'      => $status,
            'post_id'     => $postId,
            'fail_reason' => $failReason,
            'raw'         => $response,
        ];
    }

    /**
     * Aguarda o término do processamento do vídeo no TikTok realizando polling com intervalo configurável.
     *
     * @param string $publishId
     * @param int $maxAttempts Número máximo de tentativas
     * @param int $intervalSeconds Intervalo em segundos entre tentativas
     * @return array{status: string, post_id: string|null, is_success: bool, fail_reason: string|null}
     */
    public function waitForPublishCompletion(string $publishId, int $maxAttempts = 6, int $intervalSeconds = 5): array
    {
        $lastStatus = 'IN_PROGRESS';
        $lastPostId = null;
        $failReason = null;

        for ($i = 0; $i < $maxAttempts; $i++) {
            if ($i > 0 && $intervalSeconds > 0) {
                sleep($intervalSeconds);
            }

            $check = $this->fetchPublishStatus($publishId);
            $lastStatus = $check['status'];
            $lastPostId = $check['post_id'];
            $failReason = $check['fail_reason'];

            if ($lastStatus === self::STATUS_SUCCESS) {
                return [
                    'status'      => self::STATUS_SUCCESS,
                    'post_id'     => $lastPostId,
                    'is_success'  => true,
                    'fail_reason' => null,
                ];
            }

            if ($lastStatus === self::STATUS_FAILED) {
                return [
                    'status'      => self::STATUS_FAILED,
                    'post_id'     => null,
                    'is_success'  => false,
                    'fail_reason' => $failReason ?? 'Processamento rejeitado pelo TikTok',
                ];
            }
        }

        return [
            'status'      => $lastStatus,
            'post_id'     => $lastPostId,
            'is_success'  => false,
            'fail_reason' => "Tempo limite excedido ({$maxAttempts} tentativas). Último status: {$lastStatus}",
        ];
    }

    /**
     * Renova o token de acesso OAuth do TikTok utilizando o refresh_token.
     *
     * @param string $clientKey Client Key do app TikTok Developer
     * @param string $clientSecret Client Secret
     * @param string $refreshToken Refresh token persistido
     * @return array{access_token: string, refresh_token: string, expires_in: int, refresh_expires_in: int, open_id: string}
     */
    public function refreshAccessToken(string $clientKey, string $clientSecret, string $refreshToken): array
    {
        $url = self::API_BASE . '/oauth/token/';
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Cache-Control: no-cache',
        ];

        $postData = http_build_query([
            'client_key'    => $clientKey,
            'client_secret' => $clientSecret,
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        $res = $this->performHttp('POST', $url, $headers, $postData, self::DEFAULT_TIMEOUT);

        $body = $res['body'];
        if ($body === false || $res['error'] !== '') {
            throw new RuntimeException("Falha na chamada cURL para renovar token TikTok: {$res['error']}");
        }

        /** @var array<string,mixed>|null $decoded */
        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException("Resposta JSON inválida da TikTok no refresh de token (HTTP {$res['http_code']})");
        }

        $data = (array) ($decoded['data'] ?? $decoded);
        $accessToken = (string) ($data['access_token'] ?? '');

        if ($accessToken === '') {
            $err = (string) ($decoded['error_description'] ?? $decoded['message'] ?? 'Erro desconhecido');
            throw new RuntimeException("TikTok recusou renovação de token: {$err}");
        }

        return [
            'access_token'       => $accessToken,
            'refresh_token'      => (string) ($data['refresh_token'] ?? $refreshToken),
            'expires_in'         => (int) ($data['expires_in'] ?? 86400),
            'refresh_expires_in' => (int) ($data['refresh_expires_in'] ?? 31536000),
            'open_id'            => (string) ($data['open_id'] ?? ''),
        ];
    }

    /**
     * Executa requisição POST JSON autenticada com Bearer Token.
     *
     * @param string $method
     * @param string $url
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function requestJson(string $method, string $url, array $payload): array
    {
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json; charset=UTF-8',
            'Accept: application/json',
        ];

        $jsonBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $res = $this->performHttp($method, $url, $headers, $jsonBody, self::DEFAULT_TIMEOUT);

        $body = $res['body'];
        if ($body === false || $res['error'] !== '') {
            throw new RuntimeException("cURL falhou em {$method} {$url}: {$res['error']}");
        }

        /** @var array<string,mixed>|null $decoded */
        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException("Resposta não-JSON da TikTok API (HTTP {$res['http_code']})");
        }

        if (isset($decoded['error']) && is_array($decoded['error'])) {
            $code = (string) ($decoded['error']['code'] ?? '');
            if ($code !== '' && $code !== 'ok' && $code !== '0') {
                $msg = (string) ($decoded['error']['message'] ?? 'Erro retornado pela TikTok API');
                throw new RuntimeException("TikTok API Error [{$code}]: {$msg}");
            }
        }

        if ($res['http_code'] >= 400) {
            throw new RuntimeException("HTTP {$res['http_code']} retornado pela TikTok API");
        }

        return $decoded;
    }

    /**
     * @param string $method
     * @param string $url
     * @param list<string> $headers
     * @param string $body
     * @param int $timeout
     * @return array{body:string|false,http_code:int,error:string,errno:int}
     */
    private function performHttp(string $method, string $url, array $headers, string $body, int $timeout): array
    {
        if ($this->httpTransport !== null) {
            /** @var array<string,string> $headerMap */
            $headerMap = [];
            foreach ($headers as $h) {
                $parts = explode(':', $h, 2);
                if (count($parts) === 2) {
                    $headerMap[trim($parts[0])] = trim($parts[1]);
                }
            }
            return ($this->httpTransport)($method, $url, $headerMap, $body, $timeout, self::CONNECT_TIMEOUT);
        }

        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('Não foi possível inicializar cURL para TikTok.');
        }

        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_HTTPHEADER     => $headers,
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $body;
        } else {
            $options[CURLOPT_CUSTOMREQUEST] = $method;
        }

        curl_setopt_array($ch, $options);
        $resultBody = curl_exec($ch);

        $result = [
            'body'      => is_string($resultBody) ? $resultBody : false,
            'http_code' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'error'     => curl_error($ch),
            'errno'     => curl_errno($ch),
        ];

        curl_close($ch);

        return $result;
    }
}
