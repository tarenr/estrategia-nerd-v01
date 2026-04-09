<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Support/helpers.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Helpers globais do sistema
 * @description Funcoes utilitarias: env, config, base_path, app_url, url.
 * @usage       config('app.timezone'), env('APP_DEBUG'), base_path('app/Views'), url('/login')
 * @notes       Arquivo de funcoes nao entra no autoload.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

use App\Support\SiteSections;

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
        $url = trim((string) env('APP_URL', ''));
        if ($url !== '') {
            return rtrim($url, '/');
        }

        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($scriptName === '') {
            return '';
        }

        $base = preg_replace('~/index\.php$~', '', $scriptName) ?? $scriptName;
        $base = rtrim($base, '/');

        return $base === '' ? '' : $base;
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

if (!function_exists('portal_configs')) {
    /**
     * @return array<string, string>
     */
    function portal_configs(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $cache = [];

        $pdo = $GLOBALS['pdo'] ?? null;
        if (!$pdo instanceof \PDO) {
            return $cache;
        }

        try {
            $stmt = $pdo->query('SELECT chave, valor FROM configuracoes');
            $rows = $stmt !== false ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            foreach ($rows as $row) {
                $key = (string) ($row['chave'] ?? '');
                if ($key === '') {
                    continue;
                }

                $cache[$key] = (string) ($row['valor'] ?? '');
            }
        } catch (\Throwable) {
            return $cache;
        }

        return $cache;
    }
}

if (!function_exists('portal_config')) {
    function portal_config(string $key, mixed $default = null): mixed
    {
        $configs = portal_configs();
        return $configs[$key] ?? $default;
    }
}

if (!function_exists('site_sections')) {
    /**
     * @return array<string, array<string, mixed>>
     */
    function site_sections(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $cache = SiteSections::fromStorage((string) portal_config(SiteSections::CONFIG_KEY, ''));
        return $cache;
    }
}

if (!function_exists('site_section')) {
    /**
     * @return array<string, mixed>
     */
    function site_section(string $key): array
    {
        $sections = site_sections();
        if (isset($sections[$key]) && is_array($sections[$key])) {
            return $sections[$key];
        }

        return SiteSections::defaults()[$key] ?? [];
    }
}

if (!function_exists('site_section_public_active')) {
    function site_section_public_active(string $key): bool
    {
        return (bool) (site_section($key)['public_active'] ?? false);
    }
}

if (!function_exists('site_section_visible_on_home')) {
    function site_section_visible_on_home(string $key): bool
    {
        return SiteSections::isVisibleOnHome(site_section($key));
    }
}

if (!function_exists('site_section_visible_in_menu')) {
    function site_section_visible_in_menu(string $key): bool
    {
        return SiteSections::isVisibleInMenu(site_section($key));
    }
}

if (!function_exists('site_section_href')) {
    function site_section_href(string $key): string
    {
        $section = site_section($key);
        $path = (string) ($section['path'] ?? '/');
        $routeType = (string) ($section['route_type'] ?? 'route');

        if ($routeType === 'anchor') {
            return rtrim(url('/'), '/') . $path;
        }

        return url($path);
    }
}

if (!function_exists('site_menu_items')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function site_menu_items(): array
    {
        $items = [];
        foreach (site_sections() as $key => $section) {
            if (!SiteSections::isVisibleInMenu($section)) {
                continue;
            }

            $items[] = [
                'key' => $key,
                'label' => (string) ($section['label'] ?? ''),
                'href' => site_section_href($key),
                'is_cta' => (string) ($section['menu_variant'] ?? 'default') === 'cta',
            ];
        }

        return $items;
    }
}

if (!function_exists('site_footer_items')) {
    /**
     * @return array<int, array<string, string>>
     */
    function site_footer_items(): array
    {
        $items = [];
        foreach (site_sections() as $key => $section) {
            $routeType = (string) ($section['route_type'] ?? 'route');
            $visible = $routeType === 'anchor'
                ? SiteSections::isVisibleOnHome($section)
                : (bool) ($section['public_active'] ?? false);

            if (!$visible) {
                continue;
            }

            $items[] = [
                'key' => $key,
                'label' => (string) ($section['label'] ?? ''),
                'href' => site_section_href($key),
            ];
        }

        return $items;
    }
}

if (!function_exists('site_contact_fallback_href')) {
    function site_contact_fallback_href(string $email = ''): string
    {
        $email = trim($email);
        if ($email !== '') {
            return 'mailto:' . $email;
        }

        if (site_section_visible_on_home('newsletter')) {
            return site_section_href('newsletter');
        }

        if (site_section_public_active('central_nerd')) {
            return site_section_href('central_nerd');
        }

        if (site_section_public_active('blog')) {
            return site_section_href('blog');
        }

        return url('/');
    }
}