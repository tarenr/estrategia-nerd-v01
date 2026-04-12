<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Repositories\NewsletterRepository;
use App\Services\Site\NewsletterSubscribeService;
use App\Support\Csrf;

final class NewsletterController
{
    public function subscribe(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if (!site_section_public_active('newsletter')) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Newsletter indisponível no momento.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'message' => 'Sessão expirada. Atualize a página e tente novamente.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $result = $this->service()->subscribe(
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['nome'] ?? ''),
            (string) ($_SERVER['REMOTE_ADDR'] ?? '')
        );

        http_response_code(($result['ok'] ?? false) ? 200 : 422);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    private function service(): NewsletterSubscribeService
    {
        /** @var \PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        return new NewsletterSubscribeService(new NewsletterRepository($pdo));
    }
}