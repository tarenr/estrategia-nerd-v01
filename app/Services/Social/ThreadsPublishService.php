<?php

declare(strict_types=1);

namespace App\Services\Social;

use Closure;
use RuntimeException;
use Throwable;

/**
 * Serviço de integração com a Threads Publishing API oficial da Meta.
 *
 * Suporta postagens de texto puro, imagens, vídeos verticais e carrosséis,
 * verificação de status do contêiner, publicação atômica e renovação de token (th_refresh_token).
 * Permite injeção de $httpTransport para testes unitários determinísticos isolados.
 */
final class ThreadsPublishService
{
    public const API_BASE = 'https://graph.threads.net/v1.0';
    public const DEFAULT_TIMEOUT = 30;
    public const PUBLISH_TIMEOUT = 45;
    public const CONNECT_TIMEOUT = 5;

    public const MEDIA_TYPE_TEXT = 'TEXT';
    public const MEDIA_TYPE_IMAGE = 'IMAGE';
    public const MEDIA_TYPE_VIDEO = 'VIDEO';
    public const MEDIA_TYPE_CAROUSEL = 'CAROUSEL';

    public const STATUS_FINISHED = 'FINISHED';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_ERROR = 'ERROR';
    public const STATUS_EXPIRED = 'EXPIRED';

    /**
     * @param string $accessToken Token OAuth de acesso da conta Threads (Long-Lived Token)
     * @param string $userId ID numérico do usuário Threads (ou 'me')
     * @param (Closure(string,string,array<string,mixed>,int,int): array{body:string|false,http_code:int,error:string,errno:int})|null $httpTransport
     */
    public function __construct(
        private readonly string $accessToken,
        private readonly string $userId = 'me',
        private readonly ?Closure $httpTransport = null,
    ) {
    }

    /**
     * Cria um contêiner de post de texto puro no Threads.
     *
     * @param string $text Conteúdo do post (limite 500 caracteres oficiais do Threads)
     * @param array<string,mixed> $options Opções adicionais (ex.: reply_control)
     * @return string containerId
     */
    public function createTextContainer(string $text, array $options = []): string
    {
        $text = trim($text);
        if ($text === '') {
            throw new RuntimeException('Post de texto no Threads exige conteúdo.');
        }

        $params = array_merge([
            'media_type' => self::MEDIA_TYPE_TEXT,
            'text'       => mb_substr($text, 0, 500, 'UTF-8'),
        ], $options);

        return $this->createContainer($params);
    }

    /**
     * Cria um contêiner com uma imagem única no Threads.
     *
     * @param string $imageUrl URL pública HTTPS da imagem
     * @param string|null $text Legenda opcional
     * @param array<string,mixed> $options Opções adicionais (alt_text, is_carousel_item)
     * @return string containerId
     */
    public function createImageContainer(string $imageUrl, ?string $text = null, array $options = []): string
    {
        $imageUrl = trim($imageUrl);
        if ($imageUrl === '' || !preg_match('#^https://#i', $imageUrl)) {
            throw new RuntimeException('URL da imagem para o Threads deve ser HTTPS absoluta e válida.');
        }

        $params = array_merge([
            'media_type' => self::MEDIA_TYPE_IMAGE,
            'image_url'  => $imageUrl,
        ], $options);

        if ($text !== null && trim($text) !== '') {
            $params['text'] = mb_substr(trim($text), 0, 500, 'UTF-8');
        }

        return $this->createContainer($params);
    }

    /**
     * Cria um contêiner com um vídeo no Threads.
     *
     * @param string $videoUrl URL pública HTTPS do vídeo MP4
     * @param string|null $text Legenda opcional
     * @param array<string,mixed> $options Opções adicionais (is_carousel_item)
     * @return string containerId
     */
    public function createVideoContainer(string $videoUrl, ?string $text = null, array $options = []): string
    {
        $videoUrl = trim($videoUrl);
        if ($videoUrl === '' || !preg_match('#^https://#i', $videoUrl)) {
            throw new RuntimeException('URL do vídeo para o Threads deve ser HTTPS absoluta e válida.');
        }

        $params = array_merge([
            'media_type' => self::MEDIA_TYPE_VIDEO,
            'video_url'  => $videoUrl,
        ], $options);

        if ($text !== null && trim($text) !== '') {
            $params['text'] = mb_substr(trim($text), 0, 500, 'UTF-8');
        }

        return $this->createContainer($params);
    }

    /**
     * Cria um contêiner de carrossel no Threads a partir de IDs de contêineres filhos.
     *
     * @param list<string> $childrenIds IDs dos contêineres filhos (mínimo 2, máximo 10)
     * @param string|null $text Legenda do carrossel
     * @return string containerId
     */
    public function createCarouselContainer(array $childrenIds, ?string $text = null): string
    {
        $count = count($childrenIds);
        if ($count < 2 || $count > 10) {
            throw new RuntimeException("Carrossel no Threads requer entre 2 e 10 itens (recebidos: {$count}).");
        }

        $params = [
            'media_type' => self::MEDIA_TYPE_CAROUSEL,
            'children'   => implode(',', $childrenIds),
        ];

        if ($text !== null && trim($text) !== '') {
            $params['text'] = mb_substr(trim($text), 0, 500, 'UTF-8');
        }

        return $this->createContainer($params);
    }

    /**
     * Consulta o status de processamento de um contêiner no Threads.
     *
     * @param string $containerId
     * @return array{status: string, error_message: string|null, raw: array<string,mixed>}
     */
    public function checkContainerStatus(string $containerId): array
    {
        $containerId = trim($containerId);
        if ($containerId === '') {
            throw new RuntimeException('ID do contêiner não pode ser vazio para verificação.');
        }

        $url = self::API_BASE . "/{$containerId}?" . http_build_query([
            'fields'       => 'id,status,error_message',
            'access_token' => $this->accessToken,
        ]);

        $res = $this->httpGet($url, self::DEFAULT_TIMEOUT);

        $status = (string) ($res['status'] ?? 'UNKNOWN');
        $errorMsg = isset($res['error_message']) && is_string($res['error_message']) ? $res['error_message'] : null;

        return [
            'status'        => $status,
            'error_message' => $errorMsg,
            'raw'           => $res,
        ];
    }

    /**
     * Publica definitivamente um contêiner validado no Threads.
     *
     * @param string $containerId
     * @return array{id: string, success: bool}
     */
    public function publishContainer(string $containerId): array
    {
        $containerId = trim($containerId);
        if ($containerId === '') {
            throw new RuntimeException('creation_id não pode ser vazio para publicação no Threads.');
        }

        $url = self::API_BASE . "/{$this->userId}/threads_publish";
        $payload = [
            'creation_id'  => $containerId,
            'access_token' => $this->accessToken,
        ];

        $response = $this->httpPost($url, $payload, self::PUBLISH_TIMEOUT);
        $publishedId = (string) ($response['id'] ?? '');

        if ($publishedId === '') {
            throw new RuntimeException('Threads não retornou um ID de mídia publicada válido.');
        }

        return [
            'id'      => $publishedId,
            'success' => true,
        ];
    }

    /**
     * Renova preventivamente o token de acesso de longa duração do Threads (th_refresh_token).
     *
     * @return array{access_token: string, token_type: string, expires_in: int, expires_at: string}
     */
    public function refreshAccessToken(): array
    {
        if ($this->accessToken === '') {
            throw new RuntimeException('Token Threads vazio; impossível renovar.');
        }

        $url = 'https://graph.threads.net/refresh_access_token?' . http_build_query([
            'grant_type'   => 'th_refresh_token',
            'access_token' => $this->accessToken,
        ]);

        $response = $this->httpGet($url, self::DEFAULT_TIMEOUT);

        $newToken = (string) ($response['access_token'] ?? '');
        if ($newToken === '') {
            throw new RuntimeException('Threads não retornou um novo access_token válido.');
        }

        $expiresIn = (int) ($response['expires_in'] ?? 5184000);
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

        return [
            'access_token' => $newToken,
            'token_type'   => (string) ($response['token_type'] ?? 'bearer'),
            'expires_in'   => $expiresIn,
            'expires_at'   => $expiresAt,
        ];
    }

    /**
     * Cria um contêiner genérico na Threads Publishing API.
     *
     * @param array<string,mixed> $params
     * @return string containerId
     */
    private function createContainer(array $params): string
    {
        $url = self::API_BASE . "/{$this->userId}/threads";
        $payload = array_merge(['access_token' => $this->accessToken], $params);

        $response = $this->httpPost($url, $payload, self::DEFAULT_TIMEOUT);

        $id = (string) ($response['id'] ?? '');
        if ($id === '') {
            $msg = (string) ($response['error']['message'] ?? 'A Threads API não retornou um ID de contêiner.');
            throw new RuntimeException("Falha ao criar contêiner Threads: {$msg}");
        }

        return $id;
    }

    /**
     * Executa GET HTTP e decodifica JSON.
     *
     * @param string $url
     * @param int $timeout
     * @return array<string,mixed>
     */
    private function httpGet(string $url, int $timeout): array
    {
        $res = $this->performHttp('GET', $url, [], $timeout);
        $body = $res['body'];

        if ($body === false || $res['error'] !== '') {
            throw new RuntimeException("cURL GET Threads falhou [{$this->sanitizeUrl($url)}]: {$res['error']}");
        }

        /** @var array<string,mixed>|null $decoded */
        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException("Resposta JSON inválida da Threads API (HTTP {$res['http_code']})");
        }

        if (isset($decoded['error']) && is_array($decoded['error'])) {
            $msg = (string) ($decoded['error']['message'] ?? 'Erro retornado pela Threads API');
            throw new RuntimeException("Threads API Error: {$msg}");
        }

        if ($res['http_code'] >= 400) {
            throw new RuntimeException("HTTP {$res['http_code']} em GET Threads");
        }

        return $decoded;
    }

    /**
     * Executa POST HTTP e decodifica JSON.
     *
     * @param string $url
     * @param array<string,mixed> $payload
     * @param int $timeout
     * @return array<string,mixed>
     */
    private function httpPost(string $url, array $payload, int $timeout): array
    {
        $res = $this->performHttp('POST', $url, $payload, $timeout);
        $body = $res['body'];

        if ($body === false || $res['error'] !== '') {
            throw new RuntimeException("cURL POST Threads falhou [{$this->sanitizeUrl($url)}]: {$res['error']}");
        }

        /** @var array<string,mixed>|null $decoded */
        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException("Resposta JSON inválida da Threads API no POST (HTTP {$res['http_code']})");
        }

        if (isset($decoded['error']) && is_array($decoded['error'])) {
            $msg = (string) ($decoded['error']['message'] ?? 'Erro retornado pela Threads API');
            throw new RuntimeException("Threads API Error: {$msg}");
        }

        if ($res['http_code'] >= 400) {
            throw new RuntimeException("HTTP {$res['http_code']} em POST Threads");
        }

        return $decoded;
    }

    /**
     * @param string $method
     * @param string $url
     * @param array<string,mixed> $payload
     * @param int $timeout
     * @return array{body:string|false,http_code:int,error:string,errno:int}
     */
    private function performHttp(string $method, string $url, array $payload, int $timeout): array
    {
        if ($this->httpTransport !== null) {
            return ($this->httpTransport)($method, $url, $payload, $timeout, self::CONNECT_TIMEOUT);
        }

        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('Não foi possível inicializar cURL para Threads.');
        }

        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($payload);
        } else {
            $options[CURLOPT_FOLLOWLOCATION] = true;
            $options[CURLOPT_MAXREDIRS] = 3;
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

    /**
     * Sanitiza URLs removendo tokens de acesso para logs e exceções limpas.
     */
    private function sanitizeUrl(string $url): string
    {
        return (string) preg_replace('/([?&]access_token=)[^&]+/i', '$1[REDACTED]', $url);
    }
}
