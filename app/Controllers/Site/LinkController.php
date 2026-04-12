<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Repositories\LinkClickRepository;
use App\Repositories\LinkRepository;
use App\Services\Site\LinkRedirectService;

final class LinkController
{
    public function go(string $slug): void
    {
        if (!site_section_public_active('central_nerd')) {
            http_response_code(404);
            echo 'Central Nerd indisponível no momento.';
            return;
        }

        $result = $this->service()->resolve($slug, $_GET, $_SERVER);
        if (!is_array($result) || ($result['ok'] ?? false) !== true) {
            http_response_code(404);
            echo 'Link não encontrado.';
            return;
        }

        header('Location: ' . (string) ($result['target_url'] ?? url('/central-nerd')), true, 302);
        exit;
    }

    private function service(): LinkRedirectService
    {
        /** @var \PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        return new LinkRedirectService(
            new LinkRepository($pdo),
            new LinkClickRepository($pdo),
        );
    }
}