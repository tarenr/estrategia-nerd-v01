<?php
declare(strict_types=1);

namespace App\Services\Affiliate;

use Exception;

class ShopeeApiService
{
    private string $appId;
    private string $appSecret;
    private string $endpoint;
    private string $defaultSubId;

    public function __construct(?string $appId = null, ?string $appSecret = null, ?string $endpoint = null)
    {
        $this->appId = $appId ?? (string)($_ENV['SHOPEE_APP_ID'] ?? getenv('SHOPEE_APP_ID') ?: '');
        $this->appSecret = $appSecret ?? (string)($_ENV['SHOPEE_APP_SECRET'] ?? getenv('SHOPEE_APP_SECRET') ?: '');
        $this->endpoint = $endpoint ?? 'https://open-api.affiliate.shopee.com.br/graphql';
        $this->defaultSubId = (string)($_ENV['SHOPEE_SUB_ID_DEFAULT'] ?? getenv('SHOPEE_SUB_ID_DEFAULT') ?: 'centralnerd');
    }

    public function isConfigured(): bool
    {
        return !empty($this->appId) && !empty($this->appSecret);
    }

    /**
     * Envia uma requisição GraphQL autenticada para a Shopee Affiliate Open API (HMAC-SHA256).
     *
     * @param string $query
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     * @throws Exception
     */
    public function query(string $query, array $variables = []): array
    {
        if (!$this->isConfigured()) {
            throw new Exception('Credenciais do Shopee Afiliados (SHOPEE_APP_ID e SHOPEE_APP_SECRET) nao encontradas no arquivo central de credenciais (.env).');
        }

        $timestamp = time();
        $payloadData = [
            'query' => $query,
            'variables' => $variables,
        ];

        $payloadJson = json_encode($payloadData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payloadJson === false) {
            throw new Exception('Erro ao codificar payload JSON da requisicao Shopee.');
        }

        $factor = $this->appId . $timestamp . $payloadJson . $this->appSecret;
        $signature = hash('sha256', $factor);

        $headers = [
            'Content-Type: application/json',
            "Authorization: SHA256 Credential={$this->appId}, Timestamp={$timestamp}, Signature={$signature}",
        ];

        $ch = curl_init($this->endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("Falha na conexao cURL com a API da Shopee: {$curlError}");
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            throw new Exception("Resposta invalida da API Shopee (HTTP {$httpCode}): {$response}");
        }

        if (isset($decoded['errors']) && !empty($decoded['errors'])) {
            $msg = $decoded['errors'][0]['message'] ?? 'Erro desconhecido GraphQL';
            throw new Exception("Erro GraphQL da API Shopee: {$msg}");
        }

        return $decoded;
    }

    /**
     * Gera um link curto oficial de afiliado para qualquer URL de produto da Shopee.
     *
     * @param string $originUrl
     * @param string|null $subId
     * @return string
     * @throws Exception
     */
    public function generateShortLink(string $originUrl, ?string $subId = null): string
    {
        $targetSubId = $subId ?? $this->defaultSubId;

        $queryStr = <<<'GRAPHQL'
mutation GenerateLink($originUrl: String!, $subIds: [String!]) {
  generateShortLink(input: { originUrl: $originUrl, subIds: $subIds }) {
    shortLink
  }
}
GRAPHQL;

        $variables = [
            'originUrl' => $originUrl,
            'subIds' => [$targetSubId],
        ];

        $res = $this->query($queryStr, $variables);
        $shortLink = $res['data']['generateShortLink']['shortLink'] ?? null;

        if (empty($shortLink)) {
            throw new Exception('A API da Shopee nao retornou o shortLink para o produto enviado.');
        }

        return (string)$shortLink;
    }
}
