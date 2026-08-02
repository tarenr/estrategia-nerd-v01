<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\Site\HostingerApiService;
use App\Support\View;

final class HostingerApiController
{
    public function index(): void
    {
        $service = new HostingerApiService(require base_path('config/hostinger-api.php'));

        View::render('admin/hostinger-api/index', [
            'title' => 'Hostinger API | Estrategia Nerd',
            'hostinger_api' => $service->dashboard(),
        ]);
    }
}
