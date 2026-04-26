<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Views/components/admin/sidebar.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.1.1
 * @purpose     Menu lateral do Admin.
 * @description Navegacao principal padronizada com icones Font Awesome.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$currentPath = rtrim($currentPath, '/') ?: '/';

$adminFullPath = parse_url(url('/admin'), PHP_URL_PATH) ?: '/admin';
$adminFullPath = rtrim($adminFullPath, '/') ?: '/admin';
$basePath = preg_replace('#/admin$#', '', $adminFullPath) ?? '';
$basePath = rtrim((string) $basePath, '/');

function strip_base(string $path, string $base): string
{
    $normalized = rtrim($path, '/') ?: '/';

    if ($base !== '' && str_starts_with($normalized . '/', $base . '/')) {
        $normalized = substr($normalized, strlen($base));
        $normalized = rtrim($normalized, '/') ?: '/';
    }

    return $normalized;
}

function norm_sidebar_path(string $path): string
{
    return rtrim($path, '/') ?: '/';
}

function is_sidebar_active(string $current, string $target): bool
{
    $current = norm_sidebar_path($current);
    $target = norm_sidebar_path($target);

    if ($target === '/admin') {
        return $current === '/admin';
    }

    return $current === $target || str_starts_with($current . '/', $target . '/');
}

function sidebar_item_class(string $current, string $target): string
{
    $base = 'flex items-center gap-3 px-3 py-2 rounded-xl transition whitespace-nowrap';

    return is_sidebar_active($current, $target)
        ? $base . ' bg-slate-900/60 text-cyan-300 border border-cyan-500/20 shadow-[0_0_24px_rgba(34,211,238,0.08)]'
        : $base . ' border border-transparent text-slate-300 hover:bg-slate-900/50 hover:text-cyan-300 hover:border-cyan-500/10';
}

/**
 * @return array{type:string,label:string}
 */
function sidebar_section(string $label): array
{
    return [
        'type' => 'section',
        'label' => $label,
    ];
}

/**
 * @return array{type:string,href:string,icon:string,label:string,capability:?string}
 */
function sidebar_item(string $href, string $icon, string $label, ?string $capability = null): array
{
    return [
        'type' => 'item',
        'href' => $href,
        'icon' => $icon,
        'label' => $label,
        'capability' => $capability,
    ];
}

function sidebar_item_visible(array $item): bool
{
    $capability = $item['capability'] ?? null;
    if (!is_string($capability) || trim($capability) === '') {
        return true;
    }

    return environment_has_capability($capability);
}

$current = strip_base($currentPath, $basePath);

$rawItems = [
    sidebar_section('Geral'),
    sidebar_item('/admin', 'fa-solid fa-chart-line', 'Dashboard'),
    sidebar_item('/', 'fa-solid fa-globe', 'Ver Site'),
    sidebar_section('Conteudo'),
    sidebar_item('/admin/posts', 'fa-solid fa-newspaper', 'Posts'),
    sidebar_item('/admin/criar-post', 'fa-solid fa-square-plus', 'Criar Post'),
    sidebar_item('/admin/categorias', 'fa-solid fa-tags', 'Categorias'),
    sidebar_item('/admin/comentarios', 'fa-solid fa-comments', 'Comentarios'),
    sidebar_item('/admin/midia', 'fa-solid fa-photo-film', 'Midia'),
    sidebar_section('Alcance'),
    sidebar_item('/admin/newsletter', 'fa-solid fa-envelope-open-text', 'Newsletter'),
    sidebar_section('Monetizacao'),
    sidebar_item('/admin/links', 'fa-solid fa-link', 'Links'),
    sidebar_item('/admin/ofertas', 'fa-solid fa-bag-shopping', 'Ofertas'),
    sidebar_section('Sistema'),
    sidebar_item('/admin/home-e-menus', 'fa-solid fa-diagram-project', 'Home e Menus', 'multi_env_menus'),
    sidebar_item('/admin/configuracoes', 'fa-solid fa-gear', 'Configuracoes', 'multi_env_settings'),
    sidebar_item('/admin/usuarios', 'fa-solid fa-user', 'Usuarios', 'multi_env_users'),
    sidebar_item('/admin/health', 'fa-solid fa-heart-pulse', 'Health Check', 'multi_env_health'),
    sidebar_item('/admin/auditoria-geral', 'fa-solid fa-shield-heart', 'Auditoria Geral', 'audit'),
    sidebar_item('/admin/central-operacional', 'fa-solid fa-arrows-rotate', 'Central Operacional', 'content_sync'),
];

$items = [];
$pendingSection = null;

foreach ($rawItems as $item) {
    if (($item['type'] ?? 'item') === 'section') {
        $pendingSection = $item;
        continue;
    }

    if (!sidebar_item_visible($item)) {
        continue;
    }

    if ($pendingSection !== null) {
        $items[] = $pendingSection;
        $pendingSection = null;
    }

    $items[] = $item;
}
?>
<nav class="space-y-1 text-sm" aria-label="Navegacao Admin">
  <?php foreach ($items as $item): ?>
    <?php if (($item['type'] ?? 'item') === 'section'): ?>
      <div class="sidebar-section-label pt-4 pb-2 first:pt-0" data-sb-section>
        <div class="sidebar-section-text">
          <?= htmlspecialchars($item['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
      </div>
      <?php continue; ?>
    <?php endif; ?>
    <a href="<?= url($item['href']) ?>" class="<?= sidebar_item_class($current, $item['href']) ?>" data-sb-item aria-label="<?= htmlspecialchars($item['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <span class="sidebar-icon w-7 text-center shrink-0 text-[18px]" aria-hidden="true" data-tooltip="<?= htmlspecialchars($item['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <i class="<?= htmlspecialchars($item['icon'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></i>
      </span>
      <span data-sb-text><?= htmlspecialchars($item['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
    </a>
  <?php endforeach; ?>
</nav>
