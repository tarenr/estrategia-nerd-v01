<?php

declare(strict_types=1);

namespace App\Support;

final class SiteSections
{
    public const CONFIG_KEY = 'home_menu_sections';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function defaults(): array
    {
        return [
            'hero' => [
                'key' => 'hero',
                'name' => 'Hero inicial',
                'description' => 'Topo da home com posicionamento principal e chamadas do portal.',
                'label' => 'Home',
                'order' => 1,
                'show_home' => true,
                'show_menu' => true,
                'public_active' => true,
                'supports_home' => true,
                'supports_menu' => true,
                'path' => '#home',
                'route_type' => 'anchor',
                'menu_variant' => 'default',
                'public_note' => 'Controla o hero e o item principal da home.',
            ],
            'sobre' => [
                'key' => 'sobre',
                'name' => 'Sobre',
                'description' => 'Bloco institucional com contexto, valores e origem do portal.',
                'label' => 'Sobre',
                'order' => 2,
                'show_home' => true,
                'show_menu' => true,
                'public_active' => true,
                'supports_home' => true,
                'supports_menu' => true,
                'path' => '#sobre',
                'route_type' => 'anchor',
                'menu_variant' => 'default',
                'public_note' => 'Aparece na home e no menu quando estiver ativo.',
            ],
            'blog' => [
                'key' => 'blog',
                'name' => 'Blog',
                'description' => 'Preview na home e modulo publico com listagem e paginas de post.',
                'label' => 'Blog',
                'order' => 3,
                'show_home' => true,
                'show_menu' => true,
                'public_active' => true,
                'supports_home' => true,
                'supports_menu' => true,
                'path' => '/blog',
                'route_type' => 'route',
                'menu_variant' => 'default',
                'public_note' => 'Quando desligado, remove /blog e /post/{slug} do publico.',
            ],
            'newsletter' => [
                'key' => 'newsletter',
                'name' => 'Newsletter',
                'description' => 'Bloco de captacao e chamada publica para inscricao.',
                'label' => 'Newsletter',
                'order' => 4,
                'show_home' => true,
                'show_menu' => true,
                'public_active' => true,
                'supports_home' => true,
                'supports_menu' => true,
                'path' => '#newsletter',
                'route_type' => 'anchor',
                'menu_variant' => 'cta',
                'public_note' => 'Quando desligado, esconde o formulario e bloqueia novas inscricoes publicas.',
            ],
            'central_nerd' => [
                'key' => 'central_nerd',
                'name' => 'Central Nerd',
                'description' => 'Pagina publica de links da bio e distribuicao rapida da marca.',
                'label' => 'Central Nerd',
                'order' => 5,
                'show_home' => false,
                'show_menu' => false,
                'public_active' => true,
                'supports_home' => false,
                'supports_menu' => true,
                'path' => '/central-nerd',
                'route_type' => 'route',
                'menu_variant' => 'default',
                'public_note' => 'Quando desligado, remove /central-nerd e /link/{slug} do publico.',
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function fromStorage(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        return self::normalizeStored($decoded);
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, array<string, mixed>>
     */
    public static function normalizeStored(array $raw): array
    {
        $sections = [];
        foreach (self::defaults() as $key => $default) {
            $item = is_array($raw[$key] ?? null) ? $raw[$key] : [];
            $sections[$key] = self::mergeSection($default, $item, false);
        }

        return self::sortSections($sections);
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, array<string, mixed>>
     */
    public static function normalizeForm(array $raw): array
    {
        $sections = [];
        foreach (self::defaults() as $key => $default) {
            $item = is_array($raw[$key] ?? null) ? $raw[$key] : [];
            $sections[$key] = self::mergeSection($default, $item, true);
        }

        return self::sortSections($sections);
    }

    /**
     * @param array<string, array<string, mixed>> $sections
     */
    public static function toStorage(array $sections): string
    {
        $payload = [];
        foreach (self::defaults() as $key => $default) {
            $section = is_array($sections[$key] ?? null) ? $sections[$key] : $default;
            $payload[$key] = [
                'label' => (string) ($section['label'] ?? $default['label']),
                'order' => (int) ($section['order'] ?? $default['order']),
                'show_home' => (bool) ($section['show_home'] ?? $default['show_home']),
                'show_menu' => (bool) ($section['show_menu'] ?? $default['show_menu']),
                'public_active' => (bool) ($section['public_active'] ?? $default['public_active']),
            ];
        }

        return (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param array<string, array<string, mixed>> $sections
     * @return array<string, int>
     */
    public static function summary(array $sections): array
    {
        $customLabels = 0;
        $homeVisible = 0;
        $menuVisible = 0;
        $publicActive = 0;

        foreach ($sections as $key => $section) {
            $default = self::defaults()[$key] ?? [];
            if ((string) ($section['label'] ?? '') !== (string) ($default['label'] ?? '')) {
                $customLabels++;
            }

            if ((bool) ($section['public_active'] ?? false)) {
                $publicActive++;
            }

            if (self::isVisibleOnHome($section)) {
                $homeVisible++;
            }

            if (self::isVisibleInMenu($section)) {
                $menuVisible++;
            }
        }

        return [
            'total' => count($sections),
            'home' => $homeVisible,
            'menu' => $menuVisible,
            'public_active' => $publicActive,
            'public_disabled' => max(0, count($sections) - $publicActive),
            'custom_labels' => $customLabels,
        ];
    }

    /**
     * @param array<string, mixed> $section
     */
    public static function isVisibleOnHome(array $section): bool
    {
        return (bool) ($section['public_active'] ?? false)
            && (bool) ($section['supports_home'] ?? false)
            && (bool) ($section['show_home'] ?? false);
    }

    /**
     * @param array<string, mixed> $section
     */
    public static function isVisibleInMenu(array $section): bool
    {
        if (!(bool) ($section['public_active'] ?? false) || !(bool) ($section['supports_menu'] ?? false) || !(bool) ($section['show_menu'] ?? false)) {
            return false;
        }

        if ((string) ($section['route_type'] ?? 'anchor') === 'anchor') {
            return self::isVisibleOnHome($section);
        }

        return true;
    }

    /**
     * @param array<string, mixed> $default
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private static function mergeSection(array $default, array $item, bool $isForm): array
    {
        $labelValue = $item['label'] ?? $default['label'];
        $orderValue = $item['order'] ?? $default['order'];
        $showHomeValue = $isForm ? ($item['show_home'] ?? 0) : ($item['show_home'] ?? $default['show_home']);
        $showMenuValue = $isForm ? ($item['show_menu'] ?? 0) : ($item['show_menu'] ?? $default['show_menu']);
        $publicValue = $isForm ? ($item['public_active'] ?? 0) : ($item['public_active'] ?? $default['public_active']);

        $merged = $default;
        $merged['label'] = self::sanitizeLabel((string) $labelValue, (string) $default['label']);
        $merged['order'] = self::sanitizeOrder($orderValue, (int) $default['order']);
        $merged['show_home'] = (bool) ($default['supports_home'] ?? false) ? self::boolValue($showHomeValue, (bool) $default['show_home']) : false;
        $merged['show_menu'] = (bool) ($default['supports_menu'] ?? false) ? self::boolValue($showMenuValue, (bool) $default['show_menu']) : false;
        $merged['public_active'] = self::boolValue($publicValue, (bool) $default['public_active']);

        return $merged;
    }

    private static function sanitizeLabel(string $value, string $fallback): string
    {
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }

        if (mb_strlen($value) > 60) {
            return trim((string) mb_substr($value, 0, 60));
        }

        return $value;
    }

    private static function sanitizeOrder(mixed $value, int $fallback): int
    {
        $order = filter_var($value, FILTER_VALIDATE_INT);
        if ($order === false) {
            return $fallback;
        }

        return max(1, min(99, (int) $order));
    }

    private static function boolValue(mixed $value, bool $fallback): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
        }

        return $fallback;
    }

    /**
     * @param array<string, array<string, mixed>> $sections
     * @return array<string, array<string, mixed>>
     */
    private static function sortSections(array $sections): array
    {
        uasort($sections, static function (array $left, array $right): int {
            $order = ((int) ($left['order'] ?? 0)) <=> ((int) ($right['order'] ?? 0));
            if ($order !== 0) {
                return $order;
            }

            return strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        return $sections;
    }
}