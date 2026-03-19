<?php
/**
 * -----------------------------------------------------------------------------
 * @file        config/routes.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Definir rotas da aplicação
 * @description Mapa de rotas HTTP (método + path) para handlers e middleware.
 * @usage       Carregado pelo front controller (public/index.php).
 * @notes       O path é relativo ao base (/estrategia-nerd/public).
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

return [
    ['GET',  '/',       [\App\Controllers\Site\HomeController::class, 'index'], null],

    ['GET',  '/login',  [\App\Controllers\Site\AuthController::class, 'showLogin'], null],
    ['POST', '/login',  [\App\Controllers\Site\AuthController::class, 'login'],     null],
    ['POST', '/logout', [\App\Controllers\Site\AuthController::class, 'logout'],    null],

    ['GET',  '/admin',              [\App\Controllers\Admin\DashboardController::class, 'index'], 'auth'],
    ['GET',  '/admin/api/dashboard',[\App\Controllers\Admin\DashboardController::class, 'data'],  'auth'],

    ['GET',  '/dev',    [\App\Controllers\Site\DevController::class, 'index'], null],
];