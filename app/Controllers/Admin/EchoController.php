<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Repositories\ConfiguracaoRepository;
use App\Support\Csrf;
use App\Support\EchoFantasias;
use App\Support\ProductionChangeGuard;
use App\Support\TargetEnvironmentDatabase;
use App\Support\View;

/**
 * Fantasia do Echo na home (FEAT-016). Segue o seletor de ambiente do admin:
 * lê e grava a chave echo_fantasia na tabela configuracoes do ambiente alvo.
 */
final class EchoController
{
    public function index(): void
    {
        View::render('admin/echo/index', $this->viewModel());
    }

    public function update(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $choice = trim((string) ($_POST['fantasia'] ?? ''));
        if (!EchoFantasias::exists($choice)) {
            http_response_code(422);
            View::render('admin/echo/index', $this->viewModel($choice, ['fantasia' => 'Escolha uma das fantasias da lista.']));
            return;
        }

        if (ProductionChangeGuard::requiresConfirmation(target_environment())
            && !ProductionChangeGuard::isValidPhrase($_POST['production_confirmation'] ?? null)) {
            http_response_code(422);
            View::render('admin/echo/index', $this->viewModel(
                $choice,
                ['production_confirmation' => 'Digite PRODUCAO para confirmar a alteracao no ambiente de producao.']
            ));
            return;
        }

        try {
            $this->repository()->saveMany([EchoFantasias::CONFIG_KEY => $choice]);
        } catch (\Throwable $e) {
            error_log('[EchoController] ' . $e->getMessage());
            http_response_code(500);
            View::render('admin/echo/index', $this->viewModel($choice, ['fantasia' => 'Nao foi possivel salvar no banco do ambiente alvo.']));
            return;
        }

        header('Location: ' . url('/admin/echo?saved=1'));
        exit;
    }

    /**
     * @param array<string, string> $errors
     * @return array<string, mixed>
     */
    private function viewModel(?string $selected = null, array $errors = []): array
    {
        $targetEnvironment = target_environment();
        $saved = '';
        $readError = '';
        try {
            $saved = (string) ($this->repository()->all()[EchoFantasias::CONFIG_KEY] ?? '');
        } catch (\Throwable $e) {
            error_log('[EchoController] ' . $e->getMessage());
            $readError = 'Nao foi possivel ler a configuracao do ambiente alvo.';
        }

        return [
            'title' => 'Fantasia do Echo | Admin',
            'fantasias' => EchoFantasias::all(),
            'default_id' => EchoFantasias::defaultId(),
            'saved_id' => $saved,
            'current_id' => EchoFantasias::resolve($saved),
            'selected_id' => $selected !== null && $selected !== '' ? $selected : EchoFantasias::resolve($saved),
            'errors' => $errors,
            'read_error' => $readError,
            'target_environment' => $targetEnvironment,
            'target_environment_label' => environment_label($targetEnvironment),
            'is_remote_target' => $targetEnvironment !== current_environment(),
            'requires_production_confirmation' => requires_production_confirmation($targetEnvironment),
        ];
    }

    private function repository(): ConfiguracaoRepository
    {
        return new ConfiguracaoRepository(TargetEnvironmentDatabase::pdo(target_environment()));
    }
}
