<?php
declare(strict_types=1);

namespace App\Support;

final class CloudflareAccessValidator
{
    private const CACHE_TTL = 3600; // 1 hora
    private const THROTTLE_SECONDS = 60; // maximo 1 re-busca por minuto para kid desconhecido
    private const HTTP_TIMEOUT = 3; // 3 segundos

    /**
     * Valida a asserção JWT do Cloudflare Access presente no cabeçalho Cf-Access-Jwt-Assertion.
     * Retorna true se for estritamente válida (RS256, AUD, ISS, EXP, NBF e assinatura conferida).
     * Retorna false para qualquer falha, credencial ausente ou erro de rede.
     */
    public static function validate(?string $jwt = null): bool
    {
        $teamDomain = trim((string) ($_ENV['CF_ACCESS_TEAM_DOMAIN'] ?? getenv('CF_ACCESS_TEAM_DOMAIN') ?: ''));
        $expectedAud = trim((string) ($_ENV['CF_ACCESS_AUD'] ?? getenv('CF_ACCESS_AUD') ?: ''));

        // Se team domain ou AUD estiverem ausentes/vazios no ambiente, nega sempre.
        if ($teamDomain === '' || $expectedAud === '') {
            return false;
        }

        if ($jwt === null) {
            $jwt = trim((string) ($_SERVER['HTTP_CF_ACCESS_JWT_ASSERTION'] ?? ''));
        } else {
            $jwt = trim($jwt);
        }

        if ($jwt === '') {
            return false;
        }

        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return false;
        }

        [$headerB64, $payloadB64, $sigB64] = $parts;

        $headerJson = self::base64UrlDecode($headerB64);
        $payloadJson = self::base64UrlDecode($payloadB64);
        $signature = self::base64UrlDecode($sigB64);

        if ($headerJson === '' || $payloadJson === '' || $signature === '') {
            return false;
        }

        $header = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);

        if (!is_array($header) || !is_array($payload)) {
            return false;
        }

        if (($header['alg'] ?? '') !== 'RS256') {
            return false;
        }

        $kid = trim((string) ($header['kid'] ?? ''));
        if ($kid === '') {
            return false;
        }

        // Validação das claims do payload antes da verificação criptográfica
        $now = time();

        // Expiração (exp)
        if (!isset($payload['exp']) || !is_numeric($payload['exp']) || (int) $payload['exp'] <= $now) {
            return false;
        }

        // Não antes (nbf) com tolerância de 60 segundos para desvio de relógio
        if (isset($payload['nbf']) && is_numeric($payload['nbf']) && (int) $payload['nbf'] > ($now + 60)) {
            return false;
        }

        // Issuer exato: https://<teamDomain>
        $expectedIss = 'https://' . rtrim($teamDomain, '/');
        if (($payload['iss'] ?? '') !== $expectedIss) {
            return false;
        }

        // AUD: trata formato array ou string
        $aud = $payload['aud'] ?? null;
        $audArray = is_array($aud) ? $aud : [$aud];
        if (!in_array($expectedAud, $audArray, true)) {
            return false;
        }

        // Obter a chave pública X.509 para o kid
        $certPem = self::getPublicKeyCert($teamDomain, $kid);
        if ($certPem === null || $certPem === '') {
            return false;
        }

        $data = $headerB64 . '.' . $payloadB64;
        $verifyResult = @openssl_verify($data, $signature, $certPem, OPENSSL_ALGO_SHA256);

        return $verifyResult === 1;
    }

    private static function getPublicKeyCert(string $teamDomain, string $kid): ?string
    {
        $cacheDir = base_path('storage/cache');
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        $cacheFile = $cacheDir . '/cf_access_certs.json';
        $throttleFile = $cacheDir . '/cf_access_throttle.txt';

        $certsData = null;
        $cacheExists = is_file($cacheFile);

        if ($cacheExists && (time() - (int) @filemtime($cacheFile) < self::CACHE_TTL)) {
            $cachedContent = @file_get_contents($cacheFile);
            if (is_string($cachedContent) && $cachedContent !== '') {
                $decoded = json_decode($cachedContent, true);
                if (is_array($decoded)) {
                    $certsData = $decoded;
                }
            }
        }

        // Tentar encontrar o kid no cache carregado
        if (is_array($certsData)) {
            $cert = self::findCertForKid($certsData, $kid);
            if ($cert !== null) {
                return $cert;
            }
        }

        // kid desconhecido ou cache expirado: re-buscar com rate-limit de 1 vez por minuto
        if (is_file($throttleFile) && (time() - (int) @filemtime($throttleFile) < self::THROTTLE_SECONDS)) {
            // Rate limit ativo: não faz re-busca externa
            return null;
        }

        @touch($throttleFile);

        $certsUrl = 'https://' . rtrim($teamDomain, '/') . '/cdn-cgi/access/certs';
        $fetchedJson = self::fetchUrl($certsUrl, self::HTTP_TIMEOUT);

        if ($fetchedJson === null || $fetchedJson === '') {
            return null;
        }

        $fetchedData = json_decode($fetchedJson, true);
        if (!is_array($fetchedData)) {
            return null;
        }

        // Salva cache local
        @file_put_contents($cacheFile, $fetchedJson, LOCK_EX);

        return self::findCertForKid($fetchedData, $kid);
    }

    private static function findCertForKid(array $certsData, string $kid): ?string
    {
        // Cloudflare retorna public_certs: [ { kid: '...', cert: '-----BEGIN CERTIFICATE-----\n...' } ]
        if (isset($certsData['public_certs']) && is_array($certsData['public_certs'])) {
            foreach ($certsData['public_certs'] as $certItem) {
                if (is_array($certItem) && ($certItem['kid'] ?? '') === $kid && !empty($certItem['cert'])) {
                    return (string) $certItem['cert'];
                }
            }
        }

        // Ou chave unica em public_cert
        if (isset($certsData['public_cert']) && is_array($certsData['public_cert'])) {
            if (($certsData['public_cert']['kid'] ?? '') === $kid && !empty($certsData['public_cert']['cert'])) {
                return (string) $certsData['public_cert']['cert'];
            }
        }

        return null;
    }

    private static function fetchUrl(string $url, int $timeout): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'EstrategiaNerd-AccessValidator/1.0',
            ]);
            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && is_string($response) && $response !== '') {
                return $response;
            }
            return null;
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'header' => "User-Agent: EstrategiaNerd-AccessValidator/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $ctx);
        return is_string($result) && $result !== '' ? $result : null;
    }

    public static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return is_string($decoded) ? $decoded : '';
    }
}
