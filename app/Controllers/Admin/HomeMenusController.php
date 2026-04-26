<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Repositories\ConfiguracaoRepository;
use App\Services\Admin\HomeMenusService;
use App\Services\Site\SitemapCacheService;
use App\Support\TargetEnvironmentDatabase;
use App\Support\Csrf;
use App\Support\ProductionChangeGuard;
use App\Support\View;

final class HomeMenusController
{
    public function index(): void
    {
        View::render('admin/home-menus/index', $this->service()->getIndexViewModel());
    }

    public function update(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        if (ProductionChangeGuard::requiresConfirmation(target_environment())
            && !ProductionChangeGuard::isValidPhrase($_POST['production_confirmation'] ?? null)) {
            http_response_code(422);
            View::render('admin/home-menus/index', $this->service()->getIndexViewModel(
                (array) ($_POST['sections'] ?? []),
                ['production_confirmation' => 'Digite PRODUCAO para confirmar alteracoes estruturais no ambiente de producao.']
            ));
            return;
        }

        $result = $this->service()->save($_POST);
        if (($result['ok'] ?? false) !== true) {
            View::render('admin/home-menus/index', $this->service()->getIndexViewModel(
                (array) ($result['old'] ?? []),
                (array) ($result['errors'] ?? [])
            ));
            return;
        }

        header('Location: ' . url('/admin/home-e-menus?saved=1'));
        exit;
    }

    private function service(): HomeMenusService
    {
        $targetEnvironment = target_environment();
        $pdo = TargetEnvironmentDatabase::pdo($targetEnvironment);

        return new HomeMenusService(
            new ConfiguracaoRepository($pdo),
            SitemapCacheService::fromGlobals(),
            $targetEnvironment,
        );
    }
}
