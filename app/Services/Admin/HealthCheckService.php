<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Support\EnvironmentManager;
use App\Support\TargetEnvironmentDatabase;
use PDO;
use Throwable;

final class HealthCheckService
{
    /**
     * @var array<string, mixed>
     */
    private array $targetProfile;

    public function __construct(
        private PDO $pdo,
        private string $targetEnvironment
    ) {
        $this->targetEnvironment = EnvironmentManager::normalize($this->targetEnvironment);
        $this->targetProfile = TargetEnvironmentDatabase::profile($this->targetEnvironment);
    }

    public function getViewModel(): array
    {
        $isRemoteTarget = $this->isRemoteTarget();
        $checks = $isRemoteTarget
            ? $this->buildRemoteChecks()
            : $this->buildLocalChecks();

        return [
            'title' => 'Health Check',
            'checks' => $checks,
            'summary' => $this->buildSummary($checks),
            'runtime' => $this->buildRuntime(),
            'target_environment' => $this->targetEnvironment,
            'target_environment_label' => environment_label($this->targetEnvironment),
            'execution_environment' => current_environment(),
            'execution_environment_label' => environment_label(current_environment()),
            'is_remote_target' => $isRemoteTarget,
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildLocalChecks(): array
    {
        return [
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
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildRemoteChecks(): array
    {
        $uploads = (array) ($this->targetProfile['uploads'] ?? []);
        $mode = strtolower(trim((string) ($uploads['mode'] ?? 'ftp')));
        $extensionChecks = [
            $this->checkExtension('pdo_mysql', 'PDO MySQL'),
        ];

        if ($mode === 'ftp') {
            $extensionChecks[] = $this->checkExtension('ftp', 'FTP');
        }

        return [
            'ambiente' => [
                $this->checkTargetEnvironment(),
                $this->checkProfileConfiguration(),
                $this->checkTargetSiteUrl(),
            ],
            'banco' => [
                $this->checkDatabaseConnection(),
                $this->checkTable('posts', 'Tabela de posts'),
                $this->checkTable('links', 'Tabela de links'),
                $this->checkTable('configuracoes', 'Tabela de configuracoes'),
                $this->checkPublishedPosts(),
            ],
            'arquivos' => [
                $this->checkUploadsProfileConfiguration(),
                $this->checkUploadsTransportMode(),
                $this->checkUploadsAccess(),
            ],
            'extensoes' => $extensionChecks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRuntime(): array
    {
        $profile = $this->targetProfile;
        $database = (array) ($profile['database'] ?? []);
        $uploads = (array) ($profile['uploads'] ?? []);

        if ($this->isRemoteTarget()) {
            return [
                'php_version' => PHP_VERSION,
                'app_url' => $this->fetchTargetConfigValue('site_url') ?: '(nao configurada)',
                'base_path' => base_path(),
                'session_path' => (string) session_save_path(),
                'target_database_host' => (string) ($database['host'] ?? '-'),
                'target_database_name' => (string) ($database['database'] ?? '-'),
                'target_uploads_mode' => strtoupper((string) ($uploads['mode'] ?? 'ftp')),
                'target_uploads_root' => (string) ($uploads['root'] ?? $uploads['path'] ?? '-'),
            ];
        }

        return [
            'php_version' => PHP_VERSION,
            'app_url' => app_url() !== '' ? app_url() : '(vazio)',
            'base_path' => base_path(),
            'upload_root' => base_path('public/uploads'),
            'session_path' => (string) session_save_path(),
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

    private function checkTargetEnvironment(): array
    {
        return [
            'label' => 'Leitura do ambiente alvo',
            'ok' => true,
            'status' => 'ok',
            'value' => environment_label($this->targetEnvironment),
            'detail' => 'O diagnostico esta sendo executado no local e consultando o ambiente alvo de forma remota e somente leitura.',
        ];
    }

    private function checkProfileConfiguration(): array
    {
        $database = (array) ($this->targetProfile['database'] ?? []);
        $uploads = (array) ($this->targetProfile['uploads'] ?? []);

        $databaseReady = true;
        foreach (['host', 'database', 'username'] as $required) {
            if (trim((string) ($database[$required] ?? '')) === '') {
                $databaseReady = false;
                break;
            }
        }

        $uploadsReady = true;
        $mode = strtolower(trim((string) ($uploads['mode'] ?? 'ftp')));
        $requiredFields = $mode === 'local'
            ? ['path']
            : ['host', 'username', 'password', 'root'];

        foreach ($requiredFields as $required) {
            if (trim((string) ($uploads[$required] ?? '')) === '') {
                $uploadsReady = false;
                break;
            }
        }

        $ok = $databaseReady && $uploadsReady;

        return [
            'label' => 'Perfil remoto configurado',
            'ok' => $ok,
            'status' => $ok ? 'ok' : 'fail',
            'value' => (string) ($this->targetProfile['label'] ?? environment_label($this->targetEnvironment)),
            'detail' => $ok
                ? 'Banco e storage do alvo possuem dados minimos para diagnostico remoto.'
                : 'Faltam credenciais ou caminhos no perfil configurado para esse ambiente.',
        ];
    }

    private function checkTargetSiteUrl(): array
    {
        $value = $this->fetchTargetConfigValue('site_url');
        $ok = $value !== '';

        return [
            'label' => 'URL principal do portal',
            'ok' => $ok,
            'status' => $ok ? 'ok' : 'warn',
            'value' => $ok ? $value : '(nao configurada)',
            'detail' => $ok
                ? 'Valor lido da tabela configuracoes do ambiente alvo.'
                : 'A configuracao site_url nao foi encontrada ou esta vazia no alvo.',
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

    private function checkPublishedPosts(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM posts WHERE status = 'publicado'");
            $count = (int) ($stmt !== false ? $stmt->fetchColumn() : 0);
            $ok = $count >= 0;
        } catch (Throwable) {
            $count = 0;
            $ok = false;
        }

        return [
            'label' => 'Posts publicados',
            'ok' => $ok,
            'status' => $ok ? 'ok' : 'warn',
            'value' => (string) $count,
            'detail' => $ok
                ? 'Contagem basica do ambiente alvo para validar leitura editorial.'
                : 'Nao foi possivel consultar a contagem de posts publicados no alvo.',
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

    private function checkUploadsProfileConfiguration(): array
    {
        $uploads = (array) ($this->targetProfile['uploads'] ?? []);
        $mode = strtolower(trim((string) ($uploads['mode'] ?? 'ftp')));
        $requiredFields = $mode === 'local'
            ? ['path']
            : ['host', 'username', 'password', 'root'];

        $missing = [];
        foreach ($requiredFields as $required) {
            if (trim((string) ($uploads[$required] ?? '')) === '') {
                $missing[] = $required;
            }
        }

        $ok = $missing === [];

        return [
            'label' => 'Perfil de uploads',
            'ok' => $ok,
            'status' => $ok ? 'ok' : 'fail',
            'value' => $mode === 'local'
                ? (string) ($uploads['path'] ?? '(sem path)')
                : (string) ($uploads['root'] ?? '(sem root)'),
            'detail' => $ok
                ? 'O storage do ambiente alvo possui caminho e credenciais configurados.'
                : 'Campos ausentes no perfil de uploads: ' . implode(', ', $missing) . '.',
        ];
    }

    private function checkUploadsTransportMode(): array
    {
        $uploads = (array) ($this->targetProfile['uploads'] ?? []);
        $mode = strtolower(trim((string) ($uploads['mode'] ?? 'ftp')));

        return [
            'label' => 'Transporte de uploads',
            'ok' => in_array($mode, ['local', 'ftp'], true),
            'status' => in_array($mode, ['local', 'ftp'], true) ? 'ok' : 'warn',
            'value' => strtoupper($mode !== '' ? $mode : 'desconhecido'),
            'detail' => $mode === 'ftp'
                ? 'O ambiente alvo usa conexao FTP para o storage remoto.'
                : ($mode === 'local'
                    ? 'O ambiente alvo usa path local para uploads.'
                    : 'Modo de uploads nao reconhecido no perfil.'),
        ];
    }

    private function checkUploadsAccess(): array
    {
        $uploads = (array) ($this->targetProfile['uploads'] ?? []);
        $mode = strtolower(trim((string) ($uploads['mode'] ?? 'ftp')));

        if ($mode === 'local') {
            $path = trim((string) ($uploads['path'] ?? ''));
            $ok = $path !== '' && is_dir($path);

            return [
                'label' => 'Acesso ao storage',
                'ok' => $ok,
                'status' => $ok ? 'ok' : 'warn',
                'value' => $path !== '' ? $path : '(nao configurado)',
                'detail' => $ok
                    ? 'A pasta de uploads configurada no alvo esta acessivel a partir do local.'
                    : 'Nao foi possivel validar a pasta de uploads configurada para esse alvo.',
            ];
        }

        if (!extension_loaded('ftp')) {
            return [
                'label' => 'Acesso ao storage',
                'ok' => false,
                'status' => 'fail',
                'value' => 'FTP indisponivel',
                'detail' => 'A extensao FTP do PHP local nao esta habilitada para validar o storage remoto.',
            ];
        }

        $host = trim((string) ($uploads['host'] ?? ''));
        $port = (int) ($uploads['port'] ?? 21);
        $username = (string) ($uploads['username'] ?? '');
        $password = (string) ($uploads['password'] ?? '');
        $root = trim((string) ($uploads['root'] ?? ''));

        if ($host === '' || $username === '' || $root === '') {
            return [
                'label' => 'Acesso ao storage',
                'ok' => false,
                'status' => 'fail',
                'value' => '(perfil incompleto)',
                'detail' => 'Nao foi possivel testar o storage remoto porque faltam host, usuario ou raiz.',
            ];
        }

        $connection = @ftp_connect($host, $port, 5);
        if ($connection === false) {
            return [
                'label' => 'Acesso ao storage',
                'ok' => false,
                'status' => 'fail',
                'value' => $host . ':' . $port,
                'detail' => 'Falha ao conectar no servidor FTP configurado para o alvo.',
            ];
        }

        try {
            $loggedIn = @ftp_login($connection, $username, $password);
            if (!$loggedIn) {
                return [
                    'label' => 'Acesso ao storage',
                    'ok' => false,
                    'status' => 'fail',
                    'value' => $host . ':' . $port,
                    'detail' => 'Falha ao autenticar no FTP configurado para o storage remoto.',
                ];
            }

            @ftp_pasv($connection, (bool) ($uploads['passive'] ?? true));
            $changed = @ftp_chdir($connection, $root);

            return [
                'label' => 'Acesso ao storage',
                'ok' => $changed,
                'status' => $changed ? 'ok' : 'warn',
                'value' => $root,
                'detail' => $changed
                    ? 'Conexao FTP aberta e raiz de uploads acessivel no ambiente alvo.'
                    : 'Conexao FTP abriu, mas a raiz de uploads nao pode ser acessada no alvo.',
            ];
        } finally {
            @ftp_close($connection);
        }
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
     * @return array<string, int>
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

    private function fetchTargetConfigValue(string $key): string
    {
        try {
            $stmt = $this->pdo->prepare('SELECT valor FROM configuracoes WHERE chave = :chave LIMIT 1');
            $stmt->execute(['chave' => $key]);
            $value = $stmt->fetchColumn();

            return is_string($value) ? trim($value) : '';
        } catch (Throwable) {
            return '';
        }
    }

    private function isRemoteTarget(): bool
    {
        return $this->targetEnvironment !== current_environment();
    }
}
