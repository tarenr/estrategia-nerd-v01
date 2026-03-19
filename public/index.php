<?php
/**
 * -----------------------------------------------------------------------------
 * @file        public/index.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Front controller
 * @description Resolve rotas via config/routes.php e despacha para controllers.
 * @usage       Local usa APP_URL com /estrategia-nerd/public; produção pode ser raiz.
 * @notes       Faz strip automático do base path a partir do APP_URL.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Support\Auth;

$pdo = $GLOBALS['pdo'] ?? null;

$routes = require __DIR__ . '/../config/routes.php';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

/**
 * Base path detectado pelo APP_URL.
 * Ex.: APP_URL=http://localhost/estrategia-nerd/public => basePath=/estrategia-nerd/public
 * Ex.: APP_URL=https://seudominio.com => basePath=''
 */
$appUrl = (string) env('APP_URL', '');
$basePath = (string) (parse_url($appUrl, PHP_URL_PATH) ?: '');
$basePath = rtrim($basePath, '/');

/** Remove basePath do início da URI para casar com routes.php (que usa /login, /admin...) */
$path = $uri;

if ($basePath !== '' && str_starts_with($path, $basePath)) {
    $path = substr($path, strlen($basePath));
    $path = $path === '' ? '/' : $path;
}

/** Match */
$match = null;
foreach ($routes as $route) {
    [$m, $p, $handler, $middleware] = $route;
    if ($m === $method && $p === $path) {
        $match = [$handler, $middleware];
        break;
    }
}

if (!$match) {
    http_response_code(404);
    echo '404';
    exit;
}

[$handler, $middleware] = $match;

/** Middleware */
if ($middleware === 'auth') {
    // redirect absoluto (com APP_URL) para funcionar em qualquer ambiente
    Auth::require(url('/login'));
}

/** Dispatch */
if (is_array($handler) && count($handler) === 2) {
    [$controllerClass, $controllerMethod] = $handler;

    if (!class_exists($controllerClass)) {
        http_response_code(500);
        echo 'Controller não encontrado.';
        exit;
    }

    try {
        $ref = new ReflectionClass($controllerClass);
        $ctor = $ref->getConstructor();

        if ($ctor && $ctor->getNumberOfParameters() >= 1) {
            $controller = $ref->newInstance($pdo);
        } else {
            $controller = $ref->newInstance();
        }
    } catch (Throwable) {
        $controller = new $controllerClass($pdo);
    }

    if (!method_exists($controller, $controllerMethod)) {
        http_response_code(500);
        echo 'Método do controller não encontrado.';
        exit;
    }

    $controller->{$controllerMethod}();
    exit;
}

http_response_code(500);
echo 'Handler inválido.';