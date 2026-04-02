<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Repositories\ComentarioRepository;
use App\Services\Admin\ComentariosService;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\View;

final class ComentariosController
{
    public function index(): void
    {
        View::render('admin/comments/index', $this->service()->getIndexViewModel($_GET));
    }

    public function reply(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $returnTo = $this->sanitizeReturnUrl((string) ($_GET['return_to'] ?? ''));
        $viewModel = $this->service()->getReplyViewModel($id, $returnTo);
        if ($viewModel === null) {
            http_response_code(404);
            echo 'Comentario nao encontrado.';
            return;
        }

        View::render('admin/comments/reply', $viewModel);
    }

    public function storeReply(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $returnTo = $this->sanitizeReturnUrl((string) ($_POST['return_to'] ?? ''));
        $result = $this->service()->replyToComment($id, $_POST, Auth::user());

        if (($result['not_found'] ?? false) === true) {
            http_response_code(404);
            echo 'Comentario nao encontrado.';
            return;
        }

        if (($result['ok'] ?? false) !== true) {
            http_response_code(422);
            View::render('admin/comments/reply', $result['viewModel'] ?? []);
            return;
        }

        header('Location: ' . $this->appendQuery($returnTo, ['moderated' => '1', 'mode' => 'replied']));
        exit;
    }

    public function moderate(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $action = (string) ($_POST['action'] ?? '');
        $returnTo = $this->sanitizeReturnUrl((string) ($_POST['return_to'] ?? ''));
        $result = $this->service()->moderateComment($id, $action);

        if (($result['not_found'] ?? false) === true) {
            http_response_code(404);
            echo 'Comentario nao encontrado.';
            return;
        }

        $target = $this->appendQuery($returnTo, [
            'moderated' => '1',
            'mode' => (string) ($result['mode'] ?? 'updated'),
        ]);

        header('Location: ' . $target);
        exit;
    }

    public function deleteConfirm(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $returnTo = $this->sanitizeReturnUrl((string) ($_GET['return_to'] ?? ''));
        $viewModel = $this->service()->getDeleteViewModel($id, $returnTo);
        if ($viewModel === null) {
            http_response_code(404);
            echo 'Comentario nao encontrado.';
            return;
        }

        View::render('admin/comments/delete', $viewModel);
    }

    public function destroy(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $returnTo = $this->sanitizeReturnUrl((string) ($_POST['return_to'] ?? $_GET['return_to'] ?? ''));
        $result = $this->service()->deleteComment($id);

        if (($result['not_found'] ?? false) === true) {
            http_response_code(404);
            echo 'Comentario nao encontrado.';
            return;
        }

        header('Location: ' . $this->appendQuery($returnTo, ['deleted' => '1']));
        exit;
    }

    private function service(): ComentariosService
    {
        /** @var \PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        return new ComentariosService(new ComentarioRepository($pdo));
    }

    private function sanitizeReturnUrl(string $returnTo): string
    {
        $fallback = url('/admin/comentarios');
        $returnTo = trim($returnTo);
        if ($returnTo === '') {
            return $fallback;
        }

        $appBase = app_url();
        if ($appBase !== '' && str_starts_with($returnTo, $appBase)) {
            return $returnTo;
        }

        if (str_starts_with($returnTo, '/')) {
            return rtrim(app_url(), '/') . $returnTo;
        }

        return $fallback;
    }

    private function appendQuery(string $url, array $params): string
    {
        $parts = parse_url($url);
        $query = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        foreach ($params as $key => $value) {
            $query[$key] = $value;
        }

        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
        $qs = http_build_query($query);

        return $scheme . $host . $port . $path . ($qs !== '' ? '?' . $qs : '') . $fragment;
    }
}