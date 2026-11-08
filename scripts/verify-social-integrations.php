<?php

declare(strict_types=1);

/**
 * -----------------------------------------------------------------------------
 * @file        scripts/verify-social-integrations.php
 * @project     Estrategia Nerd
 * @purpose     Suíte determinística de validação das integrações sociais (Frente 3)
 *              - Blindagem de Token e Anti-duplicação no Instagram
 *              - TikTokPublishService (Content Posting API v2)
 *              - ThreadsPublishService (Threads Publishing API)
 * @usage       C:\xampp\php\php.exe scripts/verify-social-integrations.php
 * -----------------------------------------------------------------------------
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Repositories\InstagramPostRepository;
use App\Services\Instagram\InstagramApiService;
use App\Services\Instagram\InstagramPublishConfirmationService;
use App\Services\Social\TikTokPublishService;
use App\Services\Social\ThreadsPublishService;

$failures = [];
$checks = 0;

$check = static function (bool $condition, string $message) use (&$failures, &$checks): void {
    $checks++;
    if (!$condition) {
        $failures[] = $message;
        echo "  [FAIL] {$message}\n";
    } else {
        echo "  [PASS] {$message}\n";
    }
};

echo "=== Verificação das Integrações Sociais & Blindagem (Frente 3) ===\n\n";

// ── 1. TikTokPublishService ──────────────────────────────────────────────────
echo "1. Testes do Conector TikTok (TikTokPublishService):\n";

// 1.1 Validações de entrada
try {
    $service = new TikTokPublishService('mock_token');
    $service->initVideoPublish('http://inseguro.com/video.mp4', 'Vídeo');
    $check(false, 'TikTok deve rejeitar URLs não HTTPS.');
} catch (RuntimeException $e) {
    $check(str_contains($e->getMessage(), 'HTTPS'), 'TikTok rejeita URLs não-HTTPS com mensagem descritiva.');
}

try {
    $service = new TikTokPublishService('mock_token');
    $service->initVideoPublish('https://exemplo.com/video.mp4', 'Vídeo', ['privacy_level' => 'INVALIDO']);
    $check(false, 'TikTok deve rejeitar privacy_level desconhecido.');
} catch (RuntimeException $e) {
    $check(str_contains($e->getMessage(), 'privacidade'), 'TikTok valida níveis de privacidade permitidos.');
}

// 1.2 Inicialização de vídeo com Mock de transporte HTTP
$mockCalls = [];
$mockTransport = static function (string $method, string $url, array $headers, string $body, int $timeout, int $connectTimeout) use (&$mockCalls): array {
    $mockCalls[] = compact('method', 'url', 'headers', 'body', 'timeout');

    if (str_contains($url, '/post/publish/video/init/')) {
        return [
            'body'      => json_encode(['data' => ['publish_id' => 'v_pub_123456', 'upload_url' => null], 'error' => ['code' => 'ok']]),
            'http_code' => 200,
            'error'     => '',
            'errno'     => 0,
        ];
    }

    if (str_contains($url, '/post/publish/status/fetch/')) {
        return [
            'body'      => json_encode([
                'data' => [
                    'status' => 'SUCCESS',
                    'publicaly_available_post_id' => ['729182371923'],
                ],
                'error' => ['code' => 'ok'],
            ]),
            'http_code' => 200,
            'error'     => '',
            'errno'     => 0,
        ];
    }

    if (str_contains($url, '/oauth/token/')) {
        return [
            'body'      => json_encode([
                'data' => [
                    'access_token'       => 'tok_renewed_999',
                    'refresh_token'      => 'ref_renewed_888',
                    'expires_in'         => 86400,
                    'refresh_expires_in' => 31536000,
                    'open_id'            => 'open_id_123',
                ],
            ]),
            'http_code' => 200,
            'error'     => '',
            'errno'     => 0,
        ];
    }

    return ['body' => false, 'http_code' => 404, 'error' => 'Not found', 'errno' => 0];
};

$ttService = new TikTokPublishService('valid_tok', $mockTransport);
$initRes = $ttService->initVideoPublish('https://nerd.tfr-info.com.br/uploads/reels/echo.mp4', 'Echo apresentando', [
    'privacy_level' => TikTokPublishService::PRIVACY_PUBLIC_TO_EVERYONE,
    'disable_comment' => true,
]);

$check($initRes['publish_id'] === 'v_pub_123456', 'TikTok initVideoPublish retorna publish_id correto.');
$check(isset($mockCalls[0]) && $mockCalls[0]['method'] === 'POST', 'TikTok initVideoPublish envia requisição POST.');
$check(str_contains((string) $mockCalls[0]['body'], '"disable_comment":true'), 'TikTok initVideoPublish repassa parâmetros de post_info no JSON.');

$statusRes = $ttService->fetchPublishStatus('v_pub_123456');
$check($statusRes['status'] === 'SUCCESS' && $statusRes['post_id'] === '729182371923', 'TikTok fetchPublishStatus decodifica status SUCCESS e publicaly_available_post_id.');

$tokenRes = $ttService->refreshAccessToken('mock_key', 'mock_secret', 'mock_refresh');
$check($tokenRes['access_token'] === 'tok_renewed_999', 'TikTok refreshAccessToken retorna novo access_token com sucesso.');

// ── 2. ThreadsPublishService ─────────────────────────────────────────────────
echo "\n2. Testes do Conector Threads (ThreadsPublishService):\n";

$threadsCalls = [];
$threadsTransport = static function (string $method, string $url, array $payload, int $timeout, int $connectTimeout) use (&$threadsCalls): array {
    $threadsCalls[] = compact('method', 'url', 'payload', 'timeout');

    if (str_contains($url, '/threads_publish')) {
        return [
            'body'      => json_encode(['id' => 'th_media_987654']),
            'http_code' => 200,
            'error'     => '',
            'errno'     => 0,
        ];
    }

    if (str_contains($url, '/refresh_access_token')) {
        return [
            'body'      => json_encode(['access_token' => 'th_renewed_token_555', 'expires_in' => 5184000]),
            'http_code' => 200,
            'error'     => '',
            'errno'     => 0,
        ];
    }

    if (str_contains($url, '/threads')) {
        return [
            'body'      => json_encode(['id' => 'th_container_112233']),
            'http_code' => 200,
            'error'     => '',
            'errno'     => 0,
        ];
    }

    if (str_contains($url, '/th_container_112233')) {
        return [
            'body'      => json_encode(['id' => 'th_container_112233', 'status' => 'FINISHED']),
            'http_code' => 200,
            'error'     => '',
            'errno'     => 0,
        ];
    }

    return ['body' => false, 'http_code' => 404, 'error' => 'Not found', 'errno' => 0];
};

$thService = new ThreadsPublishService('th_token_123', 'me', $threadsTransport);

// 2.1 Post de texto
$textContainer = $thService->createTextContainer('Notícia urgente do Estratégia Nerd');
$check($textContainer === 'th_container_112233', 'Threads createTextContainer retorna container ID.');
$check($threadsCalls[0]['payload']['media_type'] === 'TEXT', 'Threads envia media_type=TEXT para post de texto.');

// 2.2 Post de imagem e vídeo
$imgContainer = $thService->createImageContainer('https://exemplo.com/foto.jpg', 'Foto nerd');
$check($imgContainer === 'th_container_112233', 'Threads createImageContainer cria container de imagem com sucesso.');

$vidContainer = $thService->createVideoContainer('https://exemplo.com/video.mp4', 'Reel nerd');
$check($vidContainer === 'th_container_112233', 'Threads createVideoContainer cria container de vídeo com sucesso.');

// 2.3 Carrossel e limites
try {
    $thService->createCarouselContainer(['only_one']);
    $check(false, 'Threads deve proibir carrossel com menos de 2 itens.');
} catch (RuntimeException $e) {
    $check(str_contains($e->getMessage(), 'entre 2 e 10'), 'Threads valida limites numéricos de itens no carrossel.');
}

$carouselContainer = $thService->createCarouselContainer(['child_1', 'child_2'], 'Carrossel duplo');
$check($carouselContainer === 'th_container_112233', 'Threads createCarouselContainer aceita múltiplos filhos.');

// 2.4 Status, publicação e refresh
$thStatus = $thService->checkContainerStatus('th_container_112233');
$check($thStatus['status'] === 'FINISHED', 'Threads checkContainerStatus recupera status FINISHED.');

$publishRes = $thService->publishContainer('th_container_112233');
$check($publishRes['id'] === 'th_media_987654' && $publishRes['success'] === true, 'Threads publishContainer conclui publicação com sucesso.');

$thRefresh = $thService->refreshAccessToken();
$check($thRefresh['access_token'] === 'th_renewed_token_555' && $thRefresh['expires_in'] === 5184000, 'Threads refreshAccessToken renova token com sucesso.');

// ── 3. Blindagem Instagram (InstagramApiService & PostRepository) ─────────────
echo "\n3. Testes de Blindagem de Token e Anti-Duplicação do Instagram:\n";

$igCalls = [];
$igMockTransport = static function (string $method, string $url, array $payload, int $timeout, int $connectTimeout) use (&$igCalls): array {
    $igCalls[] = compact('method', 'url', 'payload', 'timeout');

    if (str_contains($url, '/debug_token')) {
        return [
            'body'      => json_encode([
                'data' => [
                    'app_id'     => '123456789',
                    'is_valid'   => true,
                    'expires_at' => time() + 3600 * 24 * 30,
                    'scopes'     => ['instagram_basic', 'instagram_content_publish'],
                ],
            ]),
            'http_code' => 200,
            'error'     => '',
            'errno'     => 0,
        ];
    }

    if (str_contains($url, 'refresh_access_token')) {
        return [
            'body'      => json_encode([
                'access_token' => 'ig_renewed_tok_777',
                'token_type'   => 'bearer',
                'expires_in'   => 5184000,
            ]),
            'http_code' => 200,
            'error'     => '',
            'errno'     => 0,
        ];
    }

    if (str_contains($url, 'oauth/access_token')) {
        return [
            'body'      => json_encode([
                'access_token' => 'fb_exchanged_tok_888',
                'token_type'   => 'bearer',
                'expires_in'   => 5184000,
            ]),
            'http_code' => 200,
            'error'     => '',
            'errno'     => 0,
        ];
    }

    return ['body' => false, 'http_code' => 404, 'error' => 'Not found', 'errno' => 0];
};

$igApi = new InstagramApiService('active_ig_tok', '17841470400718850', $igMockTransport);

$inspect = $igApi->inspectToken();
$check($inspect['is_valid'] === true && count($inspect['scopes']) === 2, 'InstagramApiService::inspectToken identifica token válido e permissões.');

$igRefresh = $igApi->refreshLongLivedToken();
$check($igRefresh['access_token'] === 'ig_renewed_tok_777', 'InstagramApiService::refreshLongLivedToken (ig_refresh_token) renova com sucesso.');

$fbRefresh = $igApi->refreshLongLivedToken('app_id_123', 'app_secret_456');
$check($fbRefresh['access_token'] === 'fb_exchanged_tok_888', 'InstagramApiService::refreshLongLivedToken (fb_exchange_token) renova com sucesso.');

// 3.2 Anti-duplicação no InstagramPublishConfirmationService
$repo = new InstagramPostRepository($GLOBALS['pdo']);
$confirmation = new InstagramPublishConfirmationService($repo, $igApi);

// Testando método de reutilização de container válido
$reused = $confirmation->reuseExistingContainerIfValid(999999);
$check($reused === null, 'reuseExistingContainerIfValid retorna null para post inexistente de forma segura.');

echo "\nTotal de verificações: {$checks} | Falhas: " . count($failures) . "\n";

if (count($failures) > 0) {
    echo "Suíte de integrações sociais FALHOU.\n";
    exit(1);
}

echo "Suíte de integrações sociais APROVADA com 100% de sucesso.\n";
exit(0);
