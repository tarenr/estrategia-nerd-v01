<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Support/View.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.1
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
     * @param array<string,mixed> $data
     */
    public static function render(string $view, array $data = []): void
    {
        if (str_starts_with($view, 'admin/')) {
            self::sendNoStoreHeaders();
        }

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
     * Renderiza um componente (partial) dentro de views/layouts.
     *
     * @param array<string,mixed> $data
     */
    public static function component(string $component, array $data = []): void
    {
        $component = ltrim($component, '/');
        $file = base_path('app/Views/components/' . $component . '.php');

        if (!is_file($file)) {
            throw new \RuntimeException("Component não encontrado: {$component} ({$file})");
        }

        echo self::renderFile($file, $data);
    }

    private static function resolveLayout(string $view): string
    {
        return str_starts_with($view, 'admin/') ? 'layouts/admin.php' : 'layouts/site.php';
    }

    private static function sendNoStoreHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
        header('X-Admin-No-Cache: 1');
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function renderFile(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);

        ob_start();
        require $file;

        return (string) ob_get_clean();
    }
}
