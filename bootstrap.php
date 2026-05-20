<?php
/**
 * -----------------------------------------------------------------------------
 * @file        bootstrap.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Inicializar a base tecnica da aplicacao.
 * @description Carrega variaveis de ambiente, configuracoes, sessao, helpers,
 *              tratamento de erros e conexao com o banco de dados.
 * @usage       Deve ser carregado pelo front controller e pelos pontos centrais
 *              da aplicacao antes de qualquer processamento.
 * @notes       Este arquivo prepara o sistema, mas nao deve conter regra
 *              de negocio.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 0. Autoload (App\*)
|--------------------------------------------------------------------------
*/
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/app/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

/*
|--------------------------------------------------------------------------
| 1. Carregar variaveis do .env
|--------------------------------------------------------------------------
*/
$envPath = __DIR__ . '/.env';

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);

        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

/*
|--------------------------------------------------------------------------
| 2. Carregar configuracoes
|--------------------------------------------------------------------------
*/
$GLOBALS['config'] = [
    'app' => require __DIR__ . '/config/app.php',
    'database' => require __DIR__ . '/config/database.php',
    'content_sync' => require __DIR__ . '/config/content-sync.php',
    'environment_capabilities' => require __DIR__ . '/config/environment-capabilities.php',
];

/*
|--------------------------------------------------------------------------
| 3. Carregar helpers e suporte base
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/app/Support/Helpers.php';
require_once __DIR__ . '/app/Support/Session.php';
require_once __DIR__ . '/app/Support/Csrf.php';
require_once __DIR__ . '/app/Support/View.php';
require_once __DIR__ . '/app/Support/Auth.php';

/*
|--------------------------------------------------------------------------
| 4. Definir timezone
|--------------------------------------------------------------------------
*/
date_default_timezone_set(config('app.timezone', 'America/Sao_Paulo'));

/*
|--------------------------------------------------------------------------
| 5. Configurar erros
|--------------------------------------------------------------------------
*/
if (config('app.debug', false)) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

/*
|--------------------------------------------------------------------------
| 6. Iniciar sessao
|--------------------------------------------------------------------------
*/
if (session_status() === PHP_SESSION_NONE) {
    session_name(config('app.session_name', 'estrategia_nerd_session'));

    $sessionSavePath = trim((string) config('app.session_save_path', ''));
    $appEnvironment = strtolower(trim((string) config('app.env', env('APP_ENV', 'production'))));
    if ($sessionSavePath === '' && in_array($appEnvironment, ['local', 'development', 'dev'], true)) {
        $sessionSavePath = base_path('storage/sessions');
    }

    if ($sessionSavePath !== '') {
        if (!is_dir($sessionSavePath)) {
            @mkdir($sessionSavePath, 0775, true);
        }

        if (is_dir($sessionSavePath) && is_writable($sessionSavePath)) {
            session_save_path($sessionSavePath);
        } else {
            error_log('Session save path unavailable: ' . $sessionSavePath);
        }
    }

    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $httpsValue = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    $serverPort = (string) ($_SERVER['SERVER_PORT'] ?? '');
    $isSecureRequest = ($httpsValue !== '' && $httpsValue !== 'off')
        || $forwardedProto === 'https'
        || $serverPort === '443';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecureRequest,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/*
|--------------------------------------------------------------------------
| 7. Criar conexao PDO
|--------------------------------------------------------------------------
*/
$db = $GLOBALS['config']['database'];

$dsn = sprintf(
    '%s:host=%s;port=%s;dbname=%s;charset=%s',
    $db['driver'],
    $db['host'],
    $db['port'],
    $db['database'],
    $db['charset']
);

if (!function_exists('en_render_database_connection_error')) {
    function en_render_database_connection_error(PDOException $exception, bool $debug = false): never
    {
        http_response_code(503);

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Retry-After: 300');
        }

        $requestId = bin2hex(random_bytes(4));
        $rawEnvironment = strtolower(trim((string) config('app.env', env('APP_ENV', 'production'))));
        $environment = htmlspecialchars($rawEnvironment !== '' ? $rawEnvironment : 'production', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $detail = $debug ? htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';
        $copy = match ($rawEnvironment) {
            'stage' => [
                'badge' => 'Ambiente indisponível',
                'title' => 'Acesso temporariamente pausado',
                'message' => 'Este ambiente está passando por uma verificação técnica e deve voltar em breve.',
                'action' => 'aguardar a normalização do ambiente.',
            ],
            'production' => [
                'badge' => 'Acesso temporário',
                'title' => 'Voltamos em breve',
                'message' => 'Estamos realizando uma verificação rápida para manter o Estratégia Nerd estável. Tente acessar novamente em alguns minutos.',
                'action' => 'aguardar a normalização do acesso.',
            ],
            default => [
                'badge' => 'Serviço indisponível',
                'title' => 'Falha temporária no painel',
                'message' => 'Não foi possível conectar ao banco deste ambiente. A aplicação está protegida e o detalhe técnico foi registrado para verificação operacional.',
                'action' => 'conferir DB_* no .env, permissão do usuário MySQL e disponibilidade do servidor.',
            ],
        };
        $showOperationalMeta = !in_array($rawEnvironment, ['stage', 'production'], true);
        $badge = htmlspecialchars($copy['badge'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $title = htmlspecialchars($copy['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $message = htmlspecialchars($copy['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $action = htmlspecialchars($copy['action'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        echo <<<HTML
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Serviço temporariamente indisponível | Estratégia Nerd</title>
  <style>
    :root {
      color-scheme: dark;
      --bg: #020617;
      --panel: rgba(15, 23, 42, .92);
      --border: rgba(34, 211, 238, .22);
      --cyan: #22d3ee;
      --blue: #2563eb;
      --text: #e2e8f0;
      --muted: #94a3b8;
      --warn: #fbbf24;
    }
    * { box-sizing: border-box; }
    body {
      min-height: 100vh;
      margin: 0;
      display: grid;
      place-items: center;
      padding: 24px;
      background:
        radial-gradient(circle at 20% 15%, rgba(34, 211, 238, .14), transparent 28%),
        radial-gradient(circle at 80% 85%, rgba(37, 99, 235, .16), transparent 32%),
        linear-gradient(135deg, #020617 0%, #0f172a 55%, #020617 100%);
      color: var(--text);
      font-family: Rajdhani, Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }
    body::before {
      content: "";
      position: fixed;
      inset: 0;
      pointer-events: none;
      opacity: .08;
      background-image:
        linear-gradient(rgba(34, 211, 238, .45) 1px, transparent 1px),
        linear-gradient(90deg, rgba(34, 211, 238, .45) 1px, transparent 1px);
      background-size: 42px 42px;
    }
    main {
      position: relative;
      width: min(100%, 560px);
      overflow: hidden;
      border: 1px solid var(--border);
      border-radius: 18px;
      background: var(--panel);
      box-shadow: 0 24px 80px rgba(8, 47, 73, .35);
    }
    .bar {
      height: 4px;
      background: linear-gradient(90deg, var(--cyan), var(--blue));
    }
    .content {
      padding: 34px;
    }
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border: 1px solid rgba(251, 191, 36, .35);
      border-radius: 999px;
      padding: 7px 11px;
      color: #fde68a;
      background: rgba(251, 191, 36, .08);
      font-size: 12px;
      font-weight: 800;
      letter-spacing: .12em;
      text-transform: uppercase;
    }
    h1 {
      margin: 18px 0 10px;
      font-family: Orbitron, Rajdhani, ui-sans-serif, system-ui;
      font-size: clamp(26px, 5vw, 38px);
      line-height: 1.05;
      letter-spacing: 0;
    }
    p {
      margin: 0;
      color: var(--muted);
      font-size: 17px;
      line-height: 1.6;
    }
    .meta {
      display: grid;
      gap: 10px;
      margin-top: 24px;
      padding-top: 20px;
      border-top: 1px solid rgba(148, 163, 184, .16);
      color: var(--muted);
      font-size: 14px;
    }
    .meta strong {
      color: var(--text);
      font-weight: 800;
    }
    .detail {
      margin-top: 18px;
      overflow-wrap: anywhere;
      border: 1px solid rgba(248, 113, 113, .25);
      border-radius: 12px;
      padding: 12px;
      color: #fecaca;
      background: rgba(127, 29, 29, .22);
      font: 13px/1.5 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    }
  </style>
</head>
<body>
  <main role="alert" aria-live="assertive">
    <div class="bar"></div>
    <section class="content">
      <div class="badge"><span aria-hidden="true">!</span> {$badge}</div>
      <h1>{$title}</h1>
      <p>{$message}</p>
      <div class="meta">
        <div><strong>Código de suporte:</strong> DB-{$requestId}</div>
HTML;

        if ($showOperationalMeta) {
            echo <<<HTML
        <div><strong>Ambiente:</strong> {$environment}</div>
        <div><strong>Próxima ação:</strong> {$action}</div>
HTML;
        }

        echo <<<HTML
      </div>
HTML;

        if ($detail !== '') {
            echo '<div class="detail">' . $detail . '</div>';
        }

        echo <<<HTML
    </section>
  </main>
</body>
</html>
HTML;
        exit;
    }
}

try {
    $GLOBALS['pdo'] = new PDO(
        $dsn,
        $db['username'],
        $db['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    en_render_database_connection_error($exception, (bool) config('app.debug', false));
}
