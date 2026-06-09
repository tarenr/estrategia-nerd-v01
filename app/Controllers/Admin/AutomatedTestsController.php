<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\Site\AutomatedTestService;
use App\Services\Site\SmokeTestService;
use App\Support\Csrf;
use App\Support\Session;
use App\Support\View;

final class AutomatedTestsController
{
    public function index(): void
    {
        $smokeService = new SmokeTestService(require base_path('config/smoke-tests.php'));
        $automatedService = new AutomatedTestService(require base_path('config/automated-tests.php'));

        View::render('admin/tests/index', [
            'title' => 'Testes Automatizados | Estrategia Nerd',
            'tests' => $smokeService->viewModel(),
            'automated_tests' => $automatedService->viewModel(),
            'automated_flash' => Session::pull('automated_tests_flash'),
        ]);
    }

    public function run(): void
    {
        try {
            Csrf::validate($_POST['_csrf_token'] ?? null);
            $level = strtolower(trim((string) ($_POST['level'] ?? 'safe')));
            $environment = strtolower(trim((string) ($_POST['environment'] ?? 'local')));
            $routines = array_values(array_filter(array_map('strval', (array) ($_POST['routines'] ?? []))));

            $service = new AutomatedTestService(require base_path('config/automated-tests.php'));
            $result = $service->run($level, $environment, $routines);
            $selected = array_values(array_map('strval', (array) ($result['selected_routines'] ?? [])));
            $selectedLabel = $selected !== [] ? ' Rotinas: ' . implode(', ', $selected) . '.' : '';
            Session::put('automated_tests_flash', [
                'type' => (string) ($result['status'] ?? 'fail') === 'fail' ? 'error' : 'success',
                'message' => sprintf(
                    'Suite %s/%s concluida com status %s.%s',
                    strtoupper($environment),
                    strtoupper($level),
                    strtoupper((string) ($result['status'] ?? 'fail')),
                    $selectedLabel
                ),
            ]);
        } catch (\Throwable $exception) {
            Session::put('automated_tests_flash', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        $redirectTab = match (strtolower(trim((string) ($_POST['level'] ?? 'safe')))) {
            'unit' => 'unitarios',
            'routine' => 'rotinas',
            'safe' => 'safe',
            default => 'visao-geral',
        };
        header('Location: ' . url('/admin/testes') . '?aba=' . rawurlencode($redirectTab));
        exit;
    }
}
