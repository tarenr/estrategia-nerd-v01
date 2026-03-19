<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Controllers/Site/HomeController.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Controller da Home do site
 * @description Renderiza a página inicial pública do site.
 * @usage       GET /
 * @notes       Use View::render('site/home') (padrão com barra) e sem terceiro argumento.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Support\View;

final class HomeController
{
    public function index(): void
    {
        View::render('site/home', [
            'title' => 'Home — Estratégia Nerd',
        ]);
    }
}