<?php
/**
 * -----------------------------------------------------------------------------
 * @file        config/routes.php
 * @project     Estrategia Nerd
 * @purpose     Definir rotas da aplicacao
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

return [
    ['GET',  '/',       [\App\Controllers\Site\HomeController::class, 'index'], null],

    ['GET',  '/login',  [\App\Controllers\Site\AuthController::class, 'showLogin'], null],
    ['POST', '/login',  [\App\Controllers\Site\AuthController::class, 'login'], null],
    ['POST', '/logout', [\App\Controllers\Site\AuthController::class, 'logout'], null],

    ['GET',  '/admin',               [\App\Controllers\Admin\DashboardController::class, 'index'], 'auth'],
    ['GET',  '/admin/api/dashboard', [\App\Controllers\Admin\DashboardController::class, 'data'], 'auth'],

    ['GET',  '/admin/posts',         [\App\Controllers\Admin\PostsController::class, 'index'], 'auth'],
    ['GET',  '/admin/criar-post',    [\App\Controllers\Admin\PostsController::class, 'create'], 'auth'],
    ['POST', '/admin/criar-post',    [\App\Controllers\Admin\PostsController::class, 'store'], 'auth'],

    ['GET',  '/dev',                 [\App\Controllers\Site\DevController::class, 'index'], null],
];
