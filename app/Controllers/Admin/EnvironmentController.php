<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Support\Csrf;
use App\Support\EnvironmentGuard;

final class EnvironmentController
{
    public function updateTarget(): void
    {
        EnvironmentGuard::requireLocal();

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $target = trim((string) ($_POST['target_environment'] ?? 'local'));
        set_target_environment($target);

        $redirect = trim((string) ($_POST['redirect_to'] ?? url('/admin')));
        if ($redirect === '' || !str_starts_with($redirect, '/')) {
            $redirect = url('/admin');
        }

        header('Location: ' . $redirect);
        exit;
    }
}
