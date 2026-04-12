<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Repositories\LinkRepository;
use App\Services\Site\CentralService;
use App\Support\View;

final class CentralController
{
    public function index(): void
    {
        if (!site_section_public_active('central_nerd')) {
            http_response_code(404);
            echo 'Central Nerd indisponível no momento.';
            return;
        }

        View::render('site/central', $this->service()->getViewModel());
    }

    private function service(): CentralService
    {
        /** @var \PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        return new CentralService(
            new LinkRepository($pdo),
        );
    }
}