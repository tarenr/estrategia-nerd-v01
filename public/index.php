<?php
/**
 * -----------------------------------------------------------------------------
 * @file        public/index.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.5
 * @purpose     Front controller
 * @description Resolve rotas via config/routes.php e despacha para controllers.
 * @usage       Local usa /estrategia-nerd/public; producao pode ser raiz.
 * @notes       Contorna OPcache no dev ao carregar routes.php.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Support\Auth;
use App\Support\View;

$pdo = $GLOBALS['pdo'] ?? null;

/** Request */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

/** Normaliza path (XAMPP) */
$path = rawurldecode($uri);
$path = preg_replace('#^.*?/public/index\.php#', '', $path) ?? $path;
$path = preg_replace('#^.*?/public#', '', $path) ?? $path;
$path = preg_replace('#^.*?/index\.php#', '', $path) ?? $path;
$path = $path === '' ? '/' : $path;
$path = rtrim($path, '/') ?: '/';

/** Load routes (anti-opcache) */
$routesFile = realpath(__DIR__ . '/../config/routes.php') ?: (__DIR__ . '/../config/routes.php');
clearstatcache(true, $routesFile);

if (function_exists('opcache_invalidate')) {
    @opcache_invalidate($routesFile, true);
}
if (function_exists('opcache_compile_file')) {
    @opcache_compile_file($routesFile);
}

$routes = include $routesFile;
if (!is_array($routes)) {
    http_response_code(500);
    echo 'Routes invalidas.';
    exit;
}

/** Match */
$match = null;
$routeParams = [];
foreach ($routes as $route) {
    [$m, $p, $handler, $middleware] = $route;

    $p = rtrim((string) $p, '/') ?: '/';
    if (strtoupper((string) $m) !== $method) {
        continue;
    }

    if (strpos($p, '{') === false) {
        if ($p === $path) {
            $match = [$handler, $middleware];
            break;
        }

        continue;
    }

    $routeSegments = $p === '/' ? [] : explode('/', trim($p, '/'));
    $pathSegments = $path === '/' ? [] : explode('/', trim($path, '/'));
    if (count($routeSegments) !== count($pathSegments)) {
        continue;
    }

    $currentParams = [];
    $matched = true;
    foreach ($routeSegments as $index => $segment) {
        $currentValue = (string) ($pathSegments[$index] ?? '');
        if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $segment, $paramMatch) === 1) {
            $currentParams[$paramMatch[1]] = rawurldecode($currentValue);
            continue;
        }

        if ($segment !== $currentValue) {
            $matched = false;
            break;
        }
    }

    if (!$matched) {
        continue;
    }

    $routeParams = $currentParams;
    $match = [$handler, $middleware];
    break;
}

if (!$match) {
    http_response_code(404);

    if (config('app.debug', false)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "404\n";
        echo "URI: {$uri}\n";
        echo "PATH: {$path}\n";
        echo "METHOD: {$method}\n";
        echo "ROUTES FILE: {$routesFile}\n";
        echo "ROUTES MD5:  " . md5_file($routesFile) . "\n";
        echo "ROUTES:\n";
        foreach ($routes as $r) {
            echo "- {$r[0]} {$r[1]}\n";
        }
        exit;
    }

    if (str_starts_with($path, '/admin')) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Página não encontrada.';
        exit;
    }

    View::render('site/error-404', [
        'title' => 'Página não encontrada | Estratégia Nerd',
        'meta_description' => 'A página solicitada não foi encontrada no portal Estratégia Nerd.',
        'requested_path' => $path,
    ]);
    exit;
}

[$handler, $middleware] = $match;

/** Middleware */
if ($middleware === 'auth') {
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

    $controller->{$controllerMethod}(...array_values($routeParams));
    exit;
}

http_response_code(500);
echo 'Handler invalido.';