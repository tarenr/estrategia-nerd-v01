<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Controllers/Admin/PostsController.php
 * @project     Estrategia Nerd
 * @purpose     Fluxo da central de posts no painel admin
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Repositories\CategoriaPostRepository;
use App\Repositories\PostRepository;
use App\Services\Admin\MidiaService;
use App\Services\Admin\PostsService;
use App\Services\Site\SitemapCacheService;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\ProductionChangeGuard;
use App\Support\TargetEnvironmentDatabase;
use App\Support\View;

final class PostsController
{
    public function index(): void
    {
        View::render('admin/posts/index', $this->service()->getIndexViewModel($_GET));
    }

    public function create(): void
    {
        View::render('admin/posts/create', $this->service()->getCreateViewModel());
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $result = $this->service()->createPost($_POST, $_FILES, Auth::id());
        if (($result['ok'] ?? false) !== true) {
            http_response_code(422);
            View::render('admin/posts/create', $result['viewModel'] ?? []);
            return;
        }

        header('Location: ' . url('/admin/posts?created=1'));
        exit;
    }

    public function edit(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $viewModel = $this->service()->getEditViewModel($id);
        if ($viewModel === null) {
            http_response_code(404);
            echo 'Post nao encontrado.';
            return;
        }

        View::render('admin/posts/edit', $viewModel);
    }

    public function update(): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $result = $this->service()->updatePost($id, $_POST, $_FILES, Auth::id());
        if (($result['not_found'] ?? false) === true) {
            http_response_code(404);
            echo 'Post nao encontrado.';
            return;
        }

        if (($result['ok'] ?? false) !== true) {
            http_response_code(422);
            View::render('admin/posts/edit', $result['viewModel'] ?? []);
            return;
        }

        header('Location: ' . url('/admin/editar-post?id=' . $id . '&updated=1'));
        exit;
    }

    public function uploadInlineImage(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'error' => 'Token CSRF invalido.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $result = $this->service()->uploadInlineImage($_POST, $_FILES);
        if (($result['ok'] ?? false) !== true) {
            http_response_code(422);
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function copyInlineImageFromLibrary(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'error' => 'Token CSRF invalido.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $result = $this->service()->copyInlineImageFromLibrary($_POST);
        if (($result['ok'] ?? false) !== true) {
            http_response_code(422);
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function copyLibraryMediaToPost(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'error' => 'Token CSRF invalido.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $result = $this->service()->copyLibraryMediaToPost($_POST);
        if (($result['ok'] ?? false) !== true) {
            http_response_code(422);
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function cleanupOrphanFiles(): void
    {
        $this->cleanupOrphanFilesLegacy();
    }

    public function cleanupOrphanFilesLegacy(): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $result = $this->service()->cleanupOrphanBodyFiles($id);
        if (($result['not_found'] ?? false) === true) {
            http_response_code(404);
            echo 'Post nao encontrado.';
            return;
        }

        $removed = max(0, (int) ($result['removed'] ?? 0));
        header('Location: ' . url('/admin/editar-post?id=' . $id . '&orphan_cleaned=1&orphan_removed=' . $removed));
        exit;
    }

    public function duplicate(): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $result = $this->service()->duplicatePost($id, Auth::id());
        if (($result['not_found'] ?? false) === true) {
            http_response_code(404);
            echo 'Post nao encontrado.';
            return;
        }

        $newId = (int) ($result['id'] ?? 0);
        header('Location: ' . url('/admin/editar-post?id=' . $newId . '&duplicated=1'));
        exit;
    }

    public function deleteConfirm(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $viewModel = $this->service()->getDeleteViewModel($id);
        if ($viewModel === null) {
            http_response_code(404);
            echo 'Post nao encontrado.';
            return;
        }

        View::render('admin/posts/delete', $this->withEnvironmentContext($viewModel));
    }

    public function destroy(): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        if (ProductionChangeGuard::requiresConfirmation(target_environment())
            && !ProductionChangeGuard::isValidPhrase($_POST['production_confirmation'] ?? null)) {
            $viewModel = $this->service()->getDeleteViewModel($id);
            if ($viewModel === null) {
                http_response_code(404);
                echo 'Post nao encontrado.';
                return;
            }

            http_response_code(422);
            $viewModel = $this->withEnvironmentContext($viewModel);
            $viewModel['errors'] = ['production_confirmation' => 'Digite PRODUCAO para confirmar a exclusao no ambiente de producao.'];
            View::render('admin/posts/delete', $viewModel);
            return;
        }

        $result = $this->service()->deletePost($id, Auth::user());
        if (($result['not_found'] ?? false) === true) {
            http_response_code(404);
            echo 'Post nao encontrado.';
            return;
        }

        header('Location: ' . url('/admin/posts?deleted=1'));
        exit;
    }

    /**
     * @param array<string,mixed> $viewModel
     * @return array<string,mixed>
     */
    private function withEnvironmentContext(array $viewModel): array
    {
        $targetEnvironment = target_environment();

        return array_merge($viewModel, [
            'target_environment' => $targetEnvironment,
            'target_environment_label' => environment_label($targetEnvironment),
            'is_remote_target' => $targetEnvironment !== current_environment(),
            'requires_production_confirmation' => ProductionChangeGuard::requiresConfirmation($targetEnvironment),
        ]);
    }

    private function service(): PostsService
    {
        $targetEnvironment = target_environment();
        $pdo = TargetEnvironmentDatabase::pdo($targetEnvironment);
        /** @var \PDO $localPdo */
        $localPdo = $GLOBALS['pdo'];

        return new PostsService(
            new PostRepository($pdo),
            new CategoriaPostRepository($pdo),
            new MidiaService($localPdo),
            SitemapCacheService::fromGlobals(),
            $targetEnvironment,
        );
    }
}
