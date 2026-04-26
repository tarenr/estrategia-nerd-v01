<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\Admin\HealthCheckService;
use App\Support\TargetEnvironmentDatabase;
use App\Support\View;

final class HealthCheckController
{
    public function index(): void
    {
        View::render('admin/health/index', $this->service()->getViewModel());
    }

    private function service(): HealthCheckService
    {
        $targetEnvironment = target_environment();
        $pdo = TargetEnvironmentDatabase::pdo($targetEnvironment);

        return new HealthCheckService($pdo, $targetEnvironment);
    }
}
