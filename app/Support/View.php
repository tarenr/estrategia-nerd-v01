<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Support/View.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Renderização de Views e Layouts
 * @description Resolve views, injeta dados e aplica layout automaticamente (admin/site).
 * @usage       View::render('site/home', [...]) ou View::render('admin/dashboard', [...]).
 * @notes       Views em "admin/*" usam "layouts/admin.php"; demais usam "layouts/site.php".
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Support;

final class View
{
    /**
     * Renderiza uma view dentro do layout apropriado.
     *
     * @param string $view  Ex.: 'site/login' ou 'admin/dashboard'
     * @param array<string,mixed> $data
     */
    public static function render(string $view, array $data = []): void
    {
        $viewFile = base_path('app/Views/' . $view . '.php');

        if (!is_file($viewFile)) {
            throw new \RuntimeException("View não encontrada: {$view}");
        }

        $layout = self::resolveLayout($view);
        $layoutFile = base_path('app/Views/' . $layout);

        if (!is_file($layoutFile)) {
            throw new \RuntimeException("Layout não encontrado: {$layout}");
        }

        $content = self::renderFile($viewFile, $data);

        $layoutData = $data;
        $layoutData['content'] = $content;

        echo self::renderFile($layoutFile, $layoutData);
    }

    /**
     * Decide o layout baseado no prefixo da view.
     */
    private static function resolveLayout(string $view): string
    {
        if (str_starts_with($view, 'admin/')) {
            return 'layouts/admin.php';
        }

        return 'layouts/site.php';
    }

    /**
     * Renderiza um PHP template e devolve HTML.
     *
     * @param array<string,mixed> $data
     */
    private static function renderFile(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);

        ob_start();
        require $file;
        return (string)ob_get_clean();
    }
}