<?php

declare(strict_types=1);

namespace App\Services\YouTube;

use RuntimeException;

final class YouTubeOAuthService
{
    private const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SCOPES = [
        'https://www.googleapis.com/auth/youtube.upload',
        'https://www.googleapis.com/auth/youtube.readonly',
        'https://www.googleapis.com/auth/yt-analytics.readonly',
    ];

    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '' && $this->redirectUri() !== '';
    }

    /** @return list<string> */
    public function scopes(): array { return self::SCOPES; }

    public function authorizationUrl(string $state): string
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Credenciais OAuth do YouTube não configuradas.');
        }
        return self::AUTHORIZE_URL . '?' . http_build_query([
            'client_id' => $this->clientId(), 'redirect_uri' => $this->redirectUri(), 'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES), 'access_type' => 'offline', 'include_granted_scopes' => 'true',
            'prompt' => 'consent', 'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array<string,mixed> */
    public function completeAuthorization(string $code): array
    {
        $tokens = $this->tokenRequest([
            'code' => $code, 'client_id' => $this->clientId(), 'client_secret' => $this->clientSecret(),
            'redirect_uri' => $this->redirectUri(), 'grant_type' => 'authorization_code',
        ]);
        if (trim((string) ($tokens['refresh_token'] ?? '')) === '') {
            throw new RuntimeException('O Google não retornou refresh token. Revogue o consentimento anterior e conecte novamente.');
        }
        $tokens['obtained_at'] = time();
        $this->writeState($tokens);
        return $tokens;
    }

    public function accessToken(): string
    {
        $state = $this->readState();
        $access = trim((string) ($state['access_token'] ?? ''));
        $expires = (int) ($state['expires_in'] ?? 0);
        $obtained = (int) ($state['obtained_at'] ?? 0);
        if ($access !== '' && $obtained + max(0, $expires - 120) > time()) {
            return $access;
        }
        $refresh = trim((string) ($state['refresh_token'] ?? ''));
        if ($refresh === '') {
            throw new RuntimeException('Canal do YouTube não conectado.');
        }
        $fresh = $this->tokenRequest([
            'client_id' => $this->clientId(), 'client_secret' => $this->clientSecret(),
            'refresh_token' => $refresh, 'grant_type' => 'refresh_token',
        ]);
        $fresh['refresh_token'] = $refresh;
        $fresh['obtained_at'] = time();
        $this->writeState($fresh);
        return trim((string) ($fresh['access_token'] ?? ''));
    }

    public function connected(): bool
    {
        try { return trim((string) ($this->readState()['refresh_token'] ?? '')) !== ''; }
        catch (\Throwable) { return false; }
    }

    /** @param array<string,string> $fields @return array<string,mixed> */
    private function tokenRequest(array $fields): array
    {
        $handle = curl_init(self::TOKEN_URL);
        if ($handle === false) { throw new RuntimeException('Não foi possível iniciar o OAuth do Google.'); }
        curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($fields), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']]);
        $body = curl_exec($handle); $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE); $error = curl_error($handle); curl_close($handle);
        $payload = is_string($body) ? json_decode($body, true) : null;
        if ($status < 200 || $status >= 300 || !is_array($payload) || trim((string) ($payload['access_token'] ?? '')) === '') {
            $message = is_array($payload) ? (string) ($payload['error_description'] ?? $payload['error'] ?? 'Resposta OAuth inválida.') : ($error !== '' ? $error : 'Resposta OAuth inválida.');
            throw new RuntimeException('OAuth YouTube: ' . $message);
        }
        return $payload;
    }

    /** @return array<string,mixed> */
    private function readState(): array
    {
        $path = $this->statePath();
        if (!is_file($path)) { return []; }
        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string,mixed> $state */
    private function writeState(array $state): void
    {
        $path = $this->statePath(); $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) { throw new RuntimeException('Não foi possível criar o diretório seguro do YouTube.'); }
        $temporary = $path . '.tmp';
        if (file_put_contents($temporary, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX) === false || !rename($temporary, $path)) {
            throw new RuntimeException('Não foi possível persistir o OAuth do YouTube.');
        }
        @chmod($path, 0600);
    }

    private function statePath(): string { return base_path('storage/app/youtube/connection.json'); }
    private function clientId(): string { return trim((string) ($_ENV['YOUTUBE_CLIENT_ID'] ?? '')); }
    private function clientSecret(): string { return trim((string) ($_ENV['YOUTUBE_CLIENT_SECRET'] ?? '')); }
    private function redirectUri(): string { return trim((string) ($_ENV['YOUTUBE_REDIRECT_URI'] ?? '')); }
}
