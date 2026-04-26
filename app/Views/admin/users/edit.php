<?php

declare(strict_types=1);

use App\Support\View;

$adminUsersJsPath = base_path('public/assets/js/admin-users.js');
$adminUsersJsVersion = is_file($adminUsersJsPath) ? (string) filemtime($adminUsersJsPath) : '1';

View::component('admin/users/form', [
    'title' => $title ?? 'Editar Usuario',
    'mode' => $mode ?? 'edit',
    'form' => $form ?? [],
    'errors' => $errors ?? [],
    'usuario' => $usuario ?? null,
    'papel_options' => $papel_options ?? [],
    'status_options' => $status_options ?? [],
    'avatar_icon_options' => $avatar_icon_options ?? [],
    'target_environment' => $target_environment ?? current_environment(),
    'target_environment_label' => $target_environment_label ?? environment_label((string) ($target_environment ?? current_environment())),
    'is_remote_target' => $is_remote_target ?? false,
    'allow_avatar_uploads' => $allow_avatar_uploads ?? true,
]);
?>
<script src="<?= url('/assets/js/admin-users.js?v=' . $adminUsersJsVersion) ?>" defer></script>
