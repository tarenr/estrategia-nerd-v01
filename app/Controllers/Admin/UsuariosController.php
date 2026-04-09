<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Repositories\UsuarioRepository;
use App\Services\Admin\MidiaService;
use App\Services\Admin\UsuariosService;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\View;

final class UsuariosController
{
    public function index(): void
    {
        View::render('admin/users/index', $this->service()->getIndexViewModel($_GET, Auth::id()));
    }

    public function create(): void
    {
        View::render('admin/users/create', $this->service()->getCreateViewModel());
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $result = $this->service()->createUsuario($_POST, $_FILES);
        if (($result['ok'] ?? false) !== true) {
            http_response_code(422);
            View::render('admin/users/create', $result['viewModel'] ?? []);
            return;
        }

        header('Location: ' . url('/admin/usuarios?flash=created'));
        exit;
    }

    public function edit(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $viewModel = $this->service()->getEditViewModel($id);
        if ($viewModel === null) {
            http_response_code(404);
            echo 'Usuario nao encontrado.';
            return;
        }

        View::render('admin/users/edit', $viewModel);
    }

    public function update(): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $result = $this->service()->updateUsuario($id, $_POST, $_FILES, Auth::id());
        if (($result['not_found'] ?? false) === true) {
            http_response_code(404);
            echo 'Usuario nao encontrado.';
            return;
        }

        if (($result['ok'] ?? false) !== true) {
            http_response_code(422);
            View::render('admin/users/edit', $result['viewModel'] ?? []);
            return;
        }

        if (!empty($result['session_user']) && is_array($result['session_user'])) {
            Auth::login($result['session_user']);
        }

        header('Location: ' . url('/admin/usuarios?flash=updated'));
        exit;
    }

    public function updateStatus(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $result = $this->service()->toggleStatus($id, Auth::id());
        if (($result['not_found'] ?? false) === true) {
            http_response_code(404);
            echo 'Usuario nao encontrado.';
            return;
        }

        header('Location: ' . url('/admin/usuarios?flash=' . rawurlencode((string) ($result['flash'] ?? 'status_updated'))));
        exit;
    }

    public function deleteConfirm(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $viewModel = $this->service()->getDeleteViewModel($id, Auth::id());
        if ($viewModel === null) {
            http_response_code(404);
            echo 'Usuario nao encontrado.';
            return;
        }

        View::render('admin/users/delete', $viewModel);
    }

    public function destroy(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $result = $this->service()->deleteUsuario($id, Auth::id());
        if (($result['not_found'] ?? false) === true) {
            http_response_code(404);
            echo 'Usuario nao encontrado.';
            return;
        }

        header('Location: ' . url('/admin/usuarios?flash=' . rawurlencode((string) ($result['flash'] ?? 'deleted'))));
        exit;
    }

    private function service(): UsuariosService
    {
        /** @var \PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        return new UsuariosService(new UsuarioRepository($pdo), new MidiaService($pdo));
    }
}