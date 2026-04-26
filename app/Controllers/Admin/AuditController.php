<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\Admin\AuditService;
use App\Support\View;

final class AuditController
{
    public function index(): void
    {
        View::render('admin/audit/index', $this->service()->getViewModel());
    }

    private function service(): AuditService
    {
        return new AuditService();
    }
}
