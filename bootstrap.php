<?php
/**
 * -----------------------------------------------------------------------------
 * @file        bootstrap.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Inicializar a base técnica da aplicação.
 * @description Carrega variáveis de ambiente, configurações, sessão, helpers,
 *              tratamento de erros e conexão com o banco de dados.
 * @usage       Deve ser carregado pelo front controller e pelos pontos centrais
 *              da aplicação antes de qualquer processamento.
 * @notes       Este arquivo prepara o sistema, mas não deve conter regra
 *              de negócio.
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
| 1. Carregar variáveis do .env
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
| 2. Carregar configurações
|--------------------------------------------------------------------------
*/
$GLOBALS['config'] = [
    'app' => require __DIR__ . '/config/app.php',
    'database' => require __DIR__ . '/config/database.php',
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
| 6. Iniciar sessão
|--------------------------------------------------------------------------
*/
if (session_status() === PHP_SESSION_NONE) {
    session_name(config('app.session_name', 'estrategia_nerd_session'));

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/*
|--------------------------------------------------------------------------
| 7. Criar conexão PDO
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
    if (config('app.debug', false)) {
        die('Erro ao conectar com o banco: ' . $exception->getMessage());
    }

    error_log($exception->getMessage());
    die('Erro interno ao conectar com o banco.');
}