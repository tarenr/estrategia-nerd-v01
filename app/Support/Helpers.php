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

if (!function_exists('current_environment')) {
    function current_environment(): string
    {
        return \App\Support\EnvironmentManager::current();
    }
}

if (!function_exists('target_environment')) {
    function target_environment(): string
    {
        return \App\Support\EnvironmentManager::target();
    }
}

if (!function_exists('set_target_environment')) {
    function set_target_environment(string $environment): void
    {
        \App\Support\EnvironmentManager::setTarget($environment);
    }
}

if (!function_exists('is_local_environment')) {
    function is_local_environment(): bool
    {
        return \App\Support\EnvironmentManager::isLocal();
    }
}

if (!function_exists('can_target_environment')) {
    function can_target_environment(string $environment): bool
    {
        return \App\Support\EnvironmentManager::canTarget($environment);
    }
}

if (!function_exists('environment_label')) {
    function environment_label(string $environment): string
    {
        return \App\Support\EnvironmentManager::label($environment);
    }
}

if (!function_exists('environment_capabilities')) {
    /**
     * @return array<int, string>
     */
    function environment_capabilities(?string $environment = null): array
    {
        return \App\Support\EnvironmentCapabilities::all($environment);
    }
}

if (!function_exists('environment_has_capability')) {
    function environment_has_capability(string $capability, ?string $environment = null): bool
    {
        return \App\Support\EnvironmentCapabilities::has($capability, $environment);
    }
}

if (!function_exists('require_environment_capability')) {
    function require_environment_capability(string $capability): void
    {
        \App\Support\EnvironmentGuard::requireCapability($capability);
    }
}

if (!function_exists('requires_production_confirmation')) {
    function requires_production_confirmation(?string $targetEnvironment = null): bool
    {
        return \App\Support\ProductionChangeGuard::requiresConfirmation($targetEnvironment);
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

                $cache[$key] = public_text((string) ($row['valor'] ?? ''));
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
if (!function_exists('public_title')) {
    function public_title(string $title): string
    {
        $clean = preg_replace('/\[\[(.*?)\]\]/u', '$1', public_text($title));
        return is_string($clean) ? $clean : public_text($title);
    }
}

if (!function_exists('public_text')) {
    function public_text(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $text = str_replace("\u{FEFF}", '', $value);

        if (!mb_check_encoding($text, 'UTF-8')) {
            $converted = @mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
            if (is_string($converted) && $converted !== '' && mb_check_encoding($converted, 'UTF-8')) {
                $text = $converted;
            } else {
                $converted = @mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
                if (is_string($converted) && $converted !== '' && mb_check_encoding($converted, 'UTF-8')) {
                    $text = $converted;
                }
            }
        }

        $mojibakeMap = [
            "Ã¡" => "á",
            "Ãà" => "à",
            "Ã¢" => "â",
            "Ãã" => "ã",
            "Ãé" => "é",
            "Ãê" => "ê",
            "Ãí" => "í",
            "Ãó" => "ó",
            "Ãô" => "ô",
            "Ãõ" => "õ",
            "Ãú" => "ú",
            "Ãç" => "ç",
            "ÃÁ" => "Á",
            "ÃÉ" => "É",
            "ÃÍ" => "Í",
            "ÃÓ" => "Ó",
            "ÃÔ" => "Ô",
            "ÃÕ" => "Õ",
            "ÃÚ" => "Ú",
            "ÃÇ" => "Ç",
            "Âº" => "º",
            "Âª" => "ª",
            "Â " => " ",
            "â€“" => "-",
            "â€”" => "-",
            "â€˜" => "'",
            "â€™" => "'",
            "â€œ" => '"',
            "â€" => '"',
        ];

        $wordMap = [
            'Estrat?gia' => 'Estratégia',
            'estrat?gia' => 'estratégia',
            'estrat?gico' => 'estratégico',
            'Estrat?gico' => 'Estratégico',
            're?ne' => 'reúne',
            'pr?ticas' => 'práticas',
            'pr?tica' => 'prática',
            'pr?ticos' => 'práticos',
            'pr?ximo' => 'próximo',
            'ru?do' => 'ruído',
            'perif?ricos' => 'periféricos',
            'conte?do' => 'conteúdo',
            'conte?dos' => 'conteúdos',
            'n?o' => 'não',
            'voc?' => 'você',
            'op??es' => 'opções',
            'recomenda??o' => 'recomendação',
            'fa?a' => 'faça',
            'An?lises' => 'Análises',
            'an?lises' => 'análises',
            'seguran?a' => 'segurança',
            'd?vidas' => 'dúvidas',
            'sele??es' => 'seleções',
            'utilidade ?til' => 'utilidade útil',
            '?ndice' => 'Índice',
            'visualiza??es' => 'visualizações',
            'coment?rios' => 'comentários',
            'Informa??es' => 'Informações',
            'mat?ria' => 'matéria',
            'Pol?tica' => 'Política',
            'bot?es' => 'botões',
            'at?' => 'até',
            'p?gina' => 'página',
            'configura??es' => 'configurações',
            'p?blicas' => 'públicas',
            'Exporta??o' => 'Exportação',
            'Verifica??o' => 'Verificação',
            'produ??o' => 'produção',
            'solicita??o' => 'solicitação',
            '?ltimo' => 'último',
            '?teis' => 'úteis',
        ];

        $text = str_replace(array_keys($mojibakeMap), array_values($mojibakeMap), $text);
        $text = str_replace(array_keys($wordMap), array_values($wordMap), $text);
        $text = str_replace("\u{FFFD}", '', $text);

        return $text;
    }
}
