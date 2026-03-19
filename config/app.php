<?php
/**
 * -----------------------------------------------------------------------------
 * @file        config/app.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Centralizar as configurações principais da aplicação.
 * @description Define nome do projeto, ambiente, modo debug, URL base,
 *              timezone e nome da sessão da aplicação.
 * @usage       Este arquivo é carregado pelo bootstrap principal.
 * @notes       Não deve conter regra de negócio.
 * -----------------------------------------------------------------------------
 */

return [
    'name' => $_ENV['APP_NAME'] ?? 'Estrategia Nerd',
    'env' => $_ENV['APP_ENV'] ?? 'development',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOL),
    'url' => rtrim($_ENV['APP_URL'] ?? 'http://localhost/estrategia-nerd/public', '/'),
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'America/Sao_Paulo',
    'session_name' => $_ENV['SESSION_NAME'] ?? 'estrategia_nerd_session',
];