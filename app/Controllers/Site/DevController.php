<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Controllers/Site/DevController.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Controller do painel Dev
 * @description Renderiza a tela de diagnóstico/dev do projeto.
 * @usage       GET /dev
 * @notes       View usa padrão com barra (site/dev) e sem terceiro argumento.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Support\View;

final class DevController
{
    public function index(): void
    {
        // mantenha toda a sua lógica atual acima/abaixo disso (se houver)
        View::render('site/dev', [
            'title' => 'Dev — Estratégia Nerd',
        ]);
    }
}