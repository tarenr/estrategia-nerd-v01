<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Repositories\ConfiguracaoRepository;
use App\Services\Admin\ConfiguracoesService;
use App\Services\Admin\MidiaService;
use App\Services\Site\SitemapCacheService;
use App\Support\Csrf;
use App\Support\View;

final class ConfiguracoesController
{
    public function index(): void
    {
        View::render('admin/settings/index', $this->service()->getIndexViewModel());
    }

    public function update(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $result = $this->service()->save($_POST, $_FILES);
        if (($result['ok'] ?? false) !== true) {
            View::render('admin/settings/index', $this->service()->getIndexViewModel(
                (array) ($result['old'] ?? []),
                (array) ($result['errors'] ?? [])
            ));
            return;
        }

        header('Location: ' . url('/admin/configuracoes?saved=1'));
        exit;
    }

    private function service(): ConfiguracoesService
    {
        /** @var \PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        return new ConfiguracoesService(
            new ConfiguracaoRepository($pdo),
            new MidiaService(),
            SitemapCacheService::fromGlobals(),
        );
    }
}
