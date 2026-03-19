<?php
declare(strict_types=1);

/**
 * @file config/env.php
 * @project Estrategia Nerd
 * @purpose Carregar variáveis do .env e expor env().
 * @usage require_once __DIR__.'/env.php'; env('DB_HOST');
 */

function project_root(): string
{
    return dirname(__DIR__);
}

/**
 * @return array<string, string>
 */
function env_load(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $path = project_root() . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($path)) {
        http_response_code(500);
        die('❌ .env não encontrado. Crie a partir de .env.example na raiz do projeto.');
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        http_response_code(500);
        die('❌ Não foi possível ler o .env.');
    }

    $env = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($key !== '') {
            $env[$key] = $value;
        }
    }

    $cache = $env;
    return $cache;
}

function env(string $key, string $default = ''): string
{
    $env = env_load();

    if (array_key_exists($key, $env)) {
        return $env[$key];
    }

    $server = getenv($key);
    if ($server !== false) {
        return (string) $server;
    }

    return $default;
}