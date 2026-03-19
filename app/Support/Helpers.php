<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Support/helpers.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Helpers globais do sistema
 * @description Funções utilitárias: env, config, base_path, app_url, url.
 * @usage       config('app.timezone'), env('APP_DEBUG'), base_path('app/Views'), url('/login')
 * @notes       Arquivo de funções não entra no autoload.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;

        if (!is_string($value)) {
            return $value;
        }

        $v = strtolower(trim($value));
        if ($v === 'true') return true;
        if ($v === 'false') return false;
        if ($v === 'null') return null;

        return $value;
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $data = $GLOBALS['config'] ?? [];

        foreach ($parts as $part) {
            if (!is_array($data) || !array_key_exists($part, $data)) {
                return $default;
            }
            $data = $data[$part];
        }

        return $data;
    }
}

/**
 * Caminho absoluto para a raiz do projeto.
 * helpers.php fica em: /app/Support/helpers.php
 * raiz do projeto é: dirname(__DIR__, 2)
 */
if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $base = dirname(__DIR__, 2);

        if ($path === '' || $path === '/') {
            return $base;
        }

        $path = ltrim($path, '/\\');
        return $base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}

if (!function_exists('app_url')) {
    function app_url(): string
    {
        $url = (string) env('APP_URL', '');
        return rtrim($url, '/');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = app_url();

        if ($path === '' || $path === '/') {
            return $base !== '' ? $base . '/' : '/';
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        return $base !== '' ? $base . $path : $path;
    }
}