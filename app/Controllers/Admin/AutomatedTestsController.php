<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\Site\SmokeTestService;
use App\Support\View;

final class AutomatedTestsController
{
    public function index(): void
    {
        $service = new SmokeTestService(require base_path('config/smoke-tests.php'));

        View::render('admin/tests/index', [
            'title' => 'Testes Automatizados | Estrategia Nerd',
            'tests' => $service->viewModel(),
        ]);
    }
}
