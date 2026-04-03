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
 * @return array{href:string,icon:string,label:string,separator_before:bool}
 */
function sidebar_item(string $href, string $icon, string $label, bool $separator = false): array
{
    return [
        'href' => $href,
        'icon' => $icon,
        'label' => $label,
        'separator_before' => $separator,
    ];
}

$current = strip_base($currentPath, $basePath);

$items = [
    sidebar_item('/admin', 'fa-solid fa-chart-line', 'Dashboard'),
    sidebar_item('/', 'fa-solid fa-globe', 'Ver Site'),
    sidebar_item('/admin/posts', 'fa-solid fa-newspaper', 'Posts', true),
    sidebar_item('/admin/criar-post', 'fa-solid fa-square-plus', 'Criar Post'),
    sidebar_item('/admin/categorias', 'fa-solid fa-tags', 'Categorias'),
    sidebar_item('/admin/comentarios', 'fa-solid fa-comments', 'Comentarios'),
    sidebar_item('/admin/midia', 'fa-solid fa-photo-film', 'Midia'),
    sidebar_item('/admin/inscritos', 'fa-solid fa-users', 'Inscritos', true),
    sidebar_item('/admin/campanhas', 'fa-solid fa-bullhorn', 'Campanhas'),
    sidebar_item('/admin/newsletter-stats', 'fa-solid fa-chart-column', 'Estatisticas'),
    sidebar_item('/admin/configuracoes', 'fa-solid fa-gear', 'Configuracoes', true),
    sidebar_item('/admin/usuarios', 'fa-solid fa-user', 'Usuarios'),
    sidebar_item('/admin/permissoes', 'fa-solid fa-lock', 'Permissoes'),
    sidebar_item('/admin/health', 'fa-solid fa-heart-pulse', 'Health Check'),
];
?>
<nav class="space-y-1 text-sm" aria-label="Navegacao Admin">
  <?php foreach ($items as $item): ?>
    <?php if ($item['separator_before']): ?>
      <hr data-sb-sep class="my-2 border-slate-800/70">
    <?php endif; ?>

    <a href="<?= url($item['href']) ?>" class="<?= sidebar_item_class($current, $item['href']) ?>" data-sb-item aria-label="<?= htmlspecialchars($item['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <span class="sidebar-icon w-7 text-center shrink-0 text-[18px]" aria-hidden="true" data-tooltip="<?= htmlspecialchars($item['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <i class="<?= htmlspecialchars($item['icon'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></i>
      </span>
      <span data-sb-text><?= htmlspecialchars($item['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
    </a>
  <?php endforeach; ?>
</nav>