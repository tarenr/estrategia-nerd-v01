<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Repositories\LinkClickRepository;
use App\Repositories\LinkRepository;
use App\Services\Admin\LinksService;
use App\Services\Admin\MidiaService;
use App\Support\Csrf;
use App\Support\View;

final class LinksController
{
    public function index(): void
    {
        View::render('admin/links/index', $this->service()->getIndexViewModel($_GET));
    }

    public function create(): void
    {
        View::render('admin/links/create', $this->service()->getCreateViewModel());
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $result = $this->service()->createLink($_POST);
        if (($result['ok'] ?? false) !== true) {
            http_response_code(422);
            View::render('admin/links/create', $result['viewModel'] ?? []);
            return;
        }

        header('Location: ' . url('/admin/links?created=1'));
        exit;
    }

    public function edit(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $viewModel = $this->service()->getEditViewModel($id);
        if ($viewModel === null) {
            http_response_code(404);
            echo 'Link nao encontrado.';
            return;
        }

        View::render('admin/links/edit', $viewModel);
    }

    public function update(): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $result = $this->service()->updateLink($id, $_POST);
        if (($result['not_found'] ?? false) === true) {
            http_response_code(404);
            echo 'Link nao encontrado.';
            return;
        }

        if (($result['ok'] ?? false) !== true) {
            http_response_code(422);
            View::render('admin/links/edit', $result['viewModel'] ?? []);
            return;
        }

        header('Location: ' . url('/admin/links?updated=1'));
        exit;
    }

    public function deleteConfirm(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $viewModel = $this->service()->getDeleteViewModel($id);
        if ($viewModel === null) {
            http_response_code(404);
            echo 'Link nao encontrado.';
            return;
        }

        View::render('admin/links/delete', $viewModel);
    }

    public function quickAction(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $action = trim((string) ($_POST['action'] ?? ''));
        $returnTo = $this->sanitizeReturnUrl((string) ($_POST['return_to'] ?? ''));
        $result = $this->service()->quickAction($id, $action);

        if (($result['not_found'] ?? false) === true) {
            http_response_code(404);
            echo 'Link nao encontrado.';
            return;
        }

        if (($result['invalid_action'] ?? false) === true) {
            http_response_code(422);
            echo 'Acao invalida.';
            return;
        }

        if ($this->isAjaxRequest()) {
            $query = $this->extractQueryParams($returnTo);
            $query['updated'] = '1';
            $query['mode'] = (string) ($result['mode'] ?? 'updated');
            View::render('admin/links/index', $this->service()->getIndexViewModel($query));
            return;
        }

        header('Location: ' . $this->appendQuery($returnTo, ['updated' => '1', 'mode' => (string) ($result['mode'] ?? 'updated')]));
        exit;
    }

    public function reorder(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $returnTo = $this->sanitizeReturnUrl((string) ($_POST['return_to'] ?? ''));
        $result = $this->service()->reorderLinks($ids);

        if (($result['invalid_action'] ?? false) === true) {
            http_response_code(422);
            echo 'Ordenacao invalida.';
            return;
        }

        if ($this->isAjaxRequest()) {
            $query = $this->extractQueryParams($returnTo);
            $query['updated'] = '1';
            $query['mode'] = (string) ($result['mode'] ?? 'updated');
            View::render('admin/links/index', $this->service()->getIndexViewModel($query));
            return;
        }

        header('Location: ' . $this->appendQuery($returnTo, ['updated' => '1', 'mode' => (string) ($result['mode'] ?? 'updated')]));
        exit;
    }

    public function destroy(): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $result = $this->service()->deleteLink($id);
        if (($result['not_found'] ?? false) === true) {
            http_response_code(404);
            echo 'Link nao encontrado.';
            return;
        }

        header('Location: ' . url('/admin/links?deleted=1'));
        exit;
    }

    private function service(): LinksService
    {
        /** @var \PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        return new LinksService(
            new LinkRepository($pdo),
            new LinkClickRepository($pdo),
            new MidiaService(),
        );
    }

    private function sanitizeReturnUrl(string $returnTo): string
    {
        $fallback = url('/admin/links');
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

    private function extractQueryParams(string $url): array
    {
        $parts = parse_url($url);
        $query = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        return is_array($query) ? $query : [];
    }

    private function isAjaxRequest(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }
}