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

$localEmbeddedRoot = __DIR__ . '/_app_core';
$parentRoot = dirname(__DIR__);

$appRoot = is_file($localEmbeddedRoot . '/bootstrap.php')
    ? $localEmbeddedRoot
    : (is_file($parentRoot . '/bootstrap.php')
        ? $parentRoot
        : $localEmbeddedRoot);

require_once $appRoot . '/bootstrap.php';

use App\Support\Auth;
use App\Support\LocalOnlyAccess;
use App\Support\View;

$pdo = $GLOBALS['pdo'] ?? null;
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$path = rawurldecode($uri);
$path = preg_replace('#^.*?/public/index\.php#', '', $path) ?? $path;
$path = preg_replace('#^.*?/public#', '', $path) ?? $path;
$path = preg_replace('#^.*?/index\.php#', '', $path) ?? $path;

$appBasePath = parse_url((string) ($_ENV['APP_URL'] ?? ''), PHP_URL_PATH);
if (is_string($appBasePath) && $appBasePath !== '' && $appBasePath !== '/') {
    $normalizedBasePath = rtrim($appBasePath, '/');

    if ($path === $normalizedBasePath) {
        $path = '/';
    } elseif (str_starts_with($path, $normalizedBasePath . '/')) {
        $path = substr($path, strlen($normalizedBasePath));
    }
}

$path = $path === '' ? '/' : $path;
$path = rtrim($path, '/') ?: '/';

if (preg_match('#^/(local|dev)(/|$)#', $path) === 1) {
    LocalOnlyAccess::enforce();
}

$routesFile = realpath($appRoot . '/config/routes.php') ?: ($appRoot . '/config/routes.php');
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
        echo 'ROUTES MD5:  ' . md5_file($routesFile) . "\n";
        echo "ROUTES:\n";
        foreach ($routes as $r) {
            echo "- {$r[0]} {$r[1]}\n";
        }
        exit;
    }

    if (str_starts_with($path, '/admin')) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Pagina nao encontrada.';
        exit;
    }

    View::render('site/error-404', [
        'title' => 'Pagina nao encontrada | Estrategia Nerd',
        'meta_description' => 'A pagina solicitada nao foi encontrada no portal Estrategia Nerd.',
        'requested_path' => $path,
    ]);
    exit;
}

[$handler, $middleware] = $match;

if ($middleware === 'auth') {
    Auth::require(url('/login'));
}

if (is_array($handler) && count($handler) === 2) {
    [$controllerClass, $controllerMethod] = $handler;

    if (!class_exists($controllerClass)) {
        http_response_code(500);
        echo 'Controller nao encontrado.';
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
        echo 'Metodo do controller nao encontrado.';
        exit;
    }

    $controller->{$controllerMethod}(...array_values($routeParams));
    exit;
}

http_response_code(500);
echo 'Handler invalido.';
