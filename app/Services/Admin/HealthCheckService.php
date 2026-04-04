<?php
declare(strict_types=1);

namespace App\Services\Admin;

use PDO;
use Throwable;

final class HealthCheckService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getViewModel(): array
    {
        $checks = [
            'ambiente' => [
                $this->checkPhpVersion(),
                $this->checkAppUrl(),
                $this->checkTimezone(),
                $this->checkSession(),
            ],
            'banco' => [
                $this->checkDatabaseConnection(),
                $this->checkTable('posts', 'Tabela de posts'),
                $this->checkTable('links', 'Tabela de links'),
                $this->checkTable('configuracoes', 'Tabela de configuracoes'),
            ],
            'arquivos' => [
                $this->checkDirectory('public/uploads', 'Pasta de uploads'),
                $this->checkDirectory('public/uploads/media', 'Biblioteca de midia'),
                $this->checkDirectory('public/uploads/posts', 'Midia de posts'),
                $this->checkDirectory('public/uploads/configuracoes', 'Branding e configuracoes'),
            ],
            'extensoes' => [
                $this->checkExtension('pdo_mysql', 'PDO MySQL'),
                $this->checkExtension('mbstring', 'mbstring'),
                $this->checkExtension('fileinfo', 'fileinfo'),
                $this->checkExtension('curl', 'cURL'),
                $this->checkExtension('gd', 'GD'),
            ],
        ];

        return [
            'title' => 'Health Check',
            'checks' => $checks,
            'summary' => $this->buildSummary($checks),
            'runtime' => [
                'php_version' => PHP_VERSION,
                'app_url' => app_url() !== '' ? app_url() : '(vazio)',
                'base_path' => base_path(),
                'upload_root' => base_path('public/uploads'),
                'session_path' => (string) session_save_path(),
            ],
        ];
    }

    private function checkPhpVersion(): array
    {
        $ok = version_compare(PHP_VERSION, '8.1.0', '>=');
        return [
            'label' => 'Versao do PHP',
            'ok' => $ok,
            'status' => $ok ? 'ok' : 'warn',
            'value' => PHP_VERSION,
            'detail' => $ok ? 'Compatibilidade adequada para o painel.' : 'Recomendado usar PHP 8.1 ou superior.',
        ];
    }

    private function checkAppUrl(): array
    {
        $value = app_url();
        $ok = $value !== '';
        return [
            'label' => 'Base da aplicacao',
            'ok' => $ok,
            'status' => $ok ? 'ok' : 'warn',
            'value' => $ok ? $value : '(nao resolvida)',
            'detail' => $ok ? 'A URL base esta sendo resolvida para assets, uploads e rotas internas.' : 'Defina APP_URL ou garanta SCRIPT_NAME coerente no ambiente.',
        ];
    }

    private function checkTimezone(): array
    {
        $timezone = date_default_timezone_get();
        return [
            'label' => 'Timezone ativa',
            'ok' => $timezone !== '',
            'status' => $timezone !== '' ? 'ok' : 'warn',
            'value' => $timezone !== '' ? $timezone : '(nao definida)',
            'detail' => 'Importante para agendamentos, expiracoes e datas do painel.',
        ];
    }

    private function checkSession(): array
    {
        $path = (string) session_save_path();
        $resolved = $path !== '' ? $path : sys_get_temp_dir();
        $ok = $resolved !== '' && is_dir($resolved) && is_writable($resolved);
        return [
            'label' => 'Sessao PHP',
            'ok' => $ok,
            'status' => $ok ? 'ok' : 'fail',
            'value' => $resolved !== '' ? $resolved : '(indefinido)',
            'detail' => $ok ? 'Sessao com pasta acessivel para login e CSRF.' : 'A pasta de sessao nao esta acessivel para escrita.',
        ];
    }

    private function checkDatabaseConnection(): array
    {
        try {
            $stmt = $this->pdo->query('SELECT 1');
            $ok = $stmt !== false;
        } catch (Throwable) {
            $ok = false;
        }

        return [
            'label' => 'Conexao com banco',
            'ok' => $ok,
            'status' => $ok ? 'ok' : 'fail',
            'value' => $ok ? 'Conectado' : 'Falha',
            'detail' => $ok ? 'O painel consegue consultar o banco normalmente.' : 'A conexao com o banco falhou.',
        ];
    }

    private function checkTable(string $table, string $label): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT 1
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = :table
                 LIMIT 1'
            );
            $stmt->execute(['table' => $table]);
            $ok = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            $ok = false;
        }

        return [
            'label' => $label,
            'ok' => $ok,
            'status' => $ok ? 'ok' : 'fail',
            'value' => $ok ? 'Encontrada' : 'Ausente',
            'detail' => $ok ? 'Estrutura principal disponivel.' : 'Tabela essencial nao encontrada no banco.',
        ];
    }

    private function checkDirectory(string $relativePath, string $label): array
    {
        $path = base_path($relativePath);
        $exists = is_dir($path);
        if (!$exists) {
            try {
                if (@mkdir($path, 0775, true) || is_dir($path)) {
                    $exists = true;
                }
            } catch (Throwable) {
                $exists = false;
            }
        }

        $writable = $exists && is_writable($path);
        $probe = $writable ? $this->probeWrite($path) : false;
        $ok = $exists && $writable && $probe;
        $status = $ok ? 'ok' : ($exists ? 'warn' : 'fail');

        return [
            'label' => $label,
            'ok' => $ok,
            'status' => $status,
            'value' => $path,
            'detail' => $ok
                ? 'Pasta presente e pronta para escrita.'
                : ($exists ? 'A pasta existe, mas a escrita falhou ou esta bloqueada.' : 'A pasta ainda nao existe neste ambiente.'),
        ];
    }

    private function checkExtension(string $extension, string $label, bool $required = true): array
    {
        $loaded = extension_loaded($extension);
        $status = $loaded ? 'ok' : ($required ? 'fail' : 'warn');

        return [
            'label' => $label,
            'ok' => $loaded,
            'status' => $status,
            'value' => $loaded ? 'Carregada' : 'Ausente',
            'detail' => $loaded
                ? 'Extensao disponivel no ambiente.'
                : ($required ? 'Extensao recomendada para o portal nao esta habilitada.' : 'Opcional: util para processamento de imagens mais avancado.'),
        ];
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $checks
     */
    private function buildSummary(array $checks): array
    {
        $flat = [];
        foreach ($checks as $group) {
            foreach ($group as $item) {
                $flat[] = $item;
            }
        }

        $total = count($flat);
        $ok = count(array_filter($flat, static fn (array $item): bool => (string) ($item['status'] ?? '') === 'ok'));
        $warn = count(array_filter($flat, static fn (array $item): bool => (string) ($item['status'] ?? '') === 'warn'));
        $fail = count(array_filter($flat, static fn (array $item): bool => (string) ($item['status'] ?? '') === 'fail'));

        return [
            'total' => $total,
            'ok' => $ok,
            'warn' => $warn,
            'fail' => $fail,
            'score' => $total > 0 ? (int) round(($ok / $total) * 100) : 0,
        ];
    }

    private function probeWrite(string $directory): bool
    {
        $file = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.health-check.tmp';

        try {
            if (@file_put_contents($file, 'ok') === false) {
                return false;
            }

            @unlink($file);
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
