<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\Admin\HealthCheckService;
use App\Support\View;

final class HealthCheckController
{
    public function index(): void
    {
        View::render('admin/health/index', $this->service()->getViewModel());
    }

    private function service(): HealthCheckService
    {
        /** @var \PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        return new HealthCheckService($pdo);
    }
}
