<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Repositories\NewsletterRepository;
use App\Services\Admin\NewsletterService;
use App\Support\Csrf;
use App\Support\ProductionChangeGuard;
use App\Support\TargetEnvironmentDatabase;
use App\Support\View;

final class NewsletterController
{
    public function index(): void
    {
        View::render('admin/newsletter/index', $this->service()->getIndexViewModel($_GET));
    }

    public function updateStatus(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        $returnTo = $this->sanitizeReturnUrl((string) ($_POST['return_to'] ?? ''));
        $result = $this->service()->updateStatus($id, $status);

        if (($result['not_found'] ?? false) === true) {
            http_response_code(404);
            echo 'Inscrito nao encontrado.';
            return;
        }

        if (($result['invalid_status'] ?? false) === true) {
            http_response_code(422);
            echo 'Status invalido.';
            return;
        }

        header('Location: ' . $this->appendQuery($returnTo, ['updated' => '1', 'mode' => (string) ($result['mode'] ?? 'updated')]));
        exit;
    }

    public function deleteConfirm(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $returnTo = $this->sanitizeReturnUrl((string) ($_GET['return_to'] ?? ''));
        $viewModel = $this->service()->getDeleteViewModel($id, $returnTo);
        if ($viewModel === null) {
            http_response_code(404);
            echo 'Inscrito nao encontrado.';
            return;
        }

        View::render('admin/newsletter/delete', $this->withEnvironmentContext($viewModel));
    }

    public function destroy(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        $returnTo = $this->sanitizeReturnUrl((string) ($_POST['return_to'] ?? $_GET['return_to'] ?? ''));

        if (ProductionChangeGuard::requiresConfirmation(target_environment())
            && !ProductionChangeGuard::isValidPhrase($_POST['production_confirmation'] ?? null)) {
            $viewModel = $this->service()->getDeleteViewModel($id, $returnTo);
            if ($viewModel === null) {
                http_response_code(404);
                echo 'Inscrito nao encontrado.';
                return;
            }

            http_response_code(422);
            $viewModel = $this->withEnvironmentContext($viewModel);
            $viewModel['errors'] = ['production_confirmation' => 'Digite PRODUCAO para confirmar a exclusao no ambiente de producao.'];
            View::render('admin/newsletter/delete', $viewModel);
            return;
        }

        $result = $this->service()->deleteSubscriber($id);

        if (($result['not_found'] ?? false) === true) {
            http_response_code(404);
            echo 'Inscrito nao encontrado.';
            return;
        }

        header('Location: ' . $this->appendQuery($returnTo, ['deleted' => '1']));
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

    private function service(): NewsletterService
    {
        $targetEnvironment = target_environment();
        $pdo = TargetEnvironmentDatabase::pdo($targetEnvironment);

        return new NewsletterService(new NewsletterRepository($pdo));
    }

    private function sanitizeReturnUrl(string $returnTo): string
    {
        $fallback = url('/admin/newsletter');
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
