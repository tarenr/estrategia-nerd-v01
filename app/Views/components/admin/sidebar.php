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

function sidebar_submenu_class(bool $active): string
{
    $base = 'flex items-center gap-3 px-3 py-2 rounded-xl transition whitespace-nowrap';

    return $active
        ? $base . ' bg-slate-900/60 text-cyan-300 border border-cyan-500/20 shadow-[0_0_24px_rgba(34,211,238,0.08)]'
        : $base . ' border border-transparent text-slate-300 hover:bg-slate-900/50 hover:text-cyan-300 hover:border-cyan-500/10';
}

/**
 * @return array{type:string,label:string,collapsed_label:string}
 */
function sidebar_section(string $label, string $collapsedLabel = ''): array
{
    return [
        'type' => 'section',
        'label' => $label,
        'collapsed_label' => $collapsedLabel,
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

/**
 * @param array<int, array{href:string,icon:string,label:string,capability:?string}> $children
 * @return array{type:string,icon:string,label:string,capability:?string,children:array<int, array{href:string,icon:string,label:string,capability:?string}>}
 */
function sidebar_submenu(string $icon, string $label, array $children, ?string $capability = null): array
{
    return [
        'type' => 'submenu',
        'icon' => $icon,
        'label' => $label,
        'capability' => $capability,
        'children' => $children,
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

function sidebar_submenu_active(string $current, array $children): bool
{
    foreach ($children as $child) {
        if (is_sidebar_active($current, (string) ($child['href'] ?? ''))) {
            return true;
        }
    }

    return false;
}

$current = strip_base($currentPath, $basePath);

$rawItems = [
    sidebar_section('Geral'),
    sidebar_item('/admin', 'fa-solid fa-chart-line', 'Dashboard'),
    sidebar_item('/', 'fa-solid fa-globe', 'Ver Site'),
    sidebar_section('Conteudo'),
    sidebar_item('/admin/posts', 'fa-solid fa-newspaper', 'Posts'),
    sidebar_item('/admin/categorias', 'fa-solid fa-tags', 'Categoria'),
    sidebar_item('/admin/comentarios', 'fa-solid fa-comments', 'Comentarios'),
    sidebar_item('/admin/midia', 'fa-solid fa-photo-film', 'Midia'),
    sidebar_section('Crescimento'),
    sidebar_item('/admin/newsletter', 'fa-solid fa-envelope-open-text', 'Newsletter'),
    sidebar_item('/admin/links', 'fa-solid fa-link', 'Links'),
    sidebar_section('Sistema'),
    sidebar_item('/admin/home-e-menus', 'fa-solid fa-diagram-project', 'Home e Menus', 'multi_env_menus'),
    sidebar_item('/admin/usuarios', 'fa-solid fa-user', 'Usuarios', 'multi_env_users'),
    sidebar_item('/admin/configuracoes', 'fa-solid fa-gear', 'Configuracoes', 'multi_env_settings'),
    sidebar_item('/admin/health', 'fa-solid fa-heart-pulse', 'Health Check', 'multi_env_health'),
    sidebar_section('Operacional'),
    sidebar_item('/admin/auditoria-geral', 'fa-solid fa-shield-heart', 'Auditoria Geral', 'audit'),
    sidebar_submenu('fa-solid fa-arrows-spin', 'Central Operacional', [
        sidebar_item('/admin/central-operacional-v2', 'fa-solid fa-gauge-high', 'Visão Geral', 'operations'),
        sidebar_item('/admin/central-operacional-v2/backup-sistemico', 'fa-solid fa-server', 'Backup Sistêmico', 'operations'),
        sidebar_item('/admin/central-operacional-v2/backup-editorial', 'fa-solid fa-newspaper', 'Backup Editorial', 'operations'),
        sidebar_item('/admin/central-operacional-v2/backup-em-nuvem', 'fa-solid fa-cloud-arrow-up', 'Backup em Nuvem', 'operations'),
        sidebar_item('/admin/central-operacional-v2/observabilidade', 'fa-solid fa-chart-simple', 'Observabilidade', 'operations'),
        sidebar_item('/admin/central-operacional-v2/hostinger-api', 'fa-solid fa-plug-circle-bolt', 'Hostinger API', 'operations'),
        sidebar_item('/admin/central-operacional-v2/seo-tecnico', 'fa-solid fa-magnifying-glass-chart', 'SEO Tecnico', 'operations'),
    ], 'operations'),
    sidebar_item('/admin/testes', 'fa-solid fa-vial-circle-check', 'Testes', 'operations'),
    sidebar_section('Central Tecnica', "Central\nTecnica"),
    sidebar_submenu('fa-solid fa-book-open-reader', 'Base de Conhecimento', [
        sidebar_item('/admin/central-tecnica/base-conhecimento', 'fa-solid fa-gauge-high', 'Visao Geral', 'docs'),
        sidebar_item('/admin/central-tecnica/base-conhecimento/backlog', 'fa-solid fa-list-check', 'Backlog', 'docs'),
        sidebar_item('/admin/central-tecnica/base-conhecimento/documentacao', 'fa-solid fa-book-open', 'Documentacao Tecnica', 'docs'),
        sidebar_item('/admin/central-tecnica/base-conhecimento/regras', 'fa-solid fa-scale-balanced', 'Regras de Negocio', 'docs'),
        sidebar_item('/admin/central-tecnica/base-conhecimento/mudancas', 'fa-solid fa-clock-rotate-left', 'Historico de Mudancas', 'docs'),
        sidebar_item('/admin/central-tecnica/base-conhecimento/procedimentos', 'fa-solid fa-clipboard-list', 'Procedimentos', 'docs'),
        sidebar_item('/admin/central-tecnica/base-conhecimento/padroes', 'fa-solid fa-compass-drafting', 'Padroes e Boas Praticas', 'docs'),
        sidebar_item('/admin/central-tecnica/base-conhecimento/estruturas-posts', 'fa-solid fa-file-code', 'Estruturas de Posts', 'docs'),
        sidebar_item('/admin/central-tecnica/base-conhecimento/faq', 'fa-solid fa-circle-question', 'FAQ', 'docs'),
    ], 'docs'),
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
      <?php
        $collapsedLabel = (string) ($item['collapsed_label'] ?? '');
        $hasCollapsedLabel = trim($collapsedLabel) !== '';
      ?>
      <div class="sidebar-section-label pt-4 pb-2 first:pt-0" data-sb-section<?= $hasCollapsedLabel ? ' data-collapsed-label="1"' : '' ?>>
        <div class="sidebar-section-text">
          <span class="sidebar-section-text-full"><?= htmlspecialchars($item['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
          <?php if ($hasCollapsedLabel): ?>
            <span class="sidebar-section-text-collapsed"><?= nl2br(htmlspecialchars($collapsedLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <?php continue; ?>
    <?php endif; ?>
    <?php if (($item['type'] ?? 'item') === 'submenu'): ?>
      <?php
        $children = is_array($item['children'] ?? null) ? $item['children'] : [];
        $isOpen = sidebar_submenu_active($current, $children);
        $collapsedHref = (string) ($children[0]['href'] ?? '#');
      ?>
      <details class="group/sidebar-submenu"<?= $isOpen ? ' open' : '' ?>>
        <summary class="<?= sidebar_submenu_class($isOpen) ?> cursor-pointer list-none" data-sb-item data-sb-submenu-summary data-collapsed-href="<?= htmlspecialchars(url($collapsedHref), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-label="<?= htmlspecialchars((string) ($item['label'] ?? 'Submenu'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
          <span class="sidebar-icon w-7 text-center shrink-0 text-[18px]" aria-hidden="true" data-tooltip="<?= htmlspecialchars((string) ($item['label'] ?? 'Submenu'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <i class="<?= htmlspecialchars((string) ($item['icon'] ?? 'fa-solid fa-folder'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></i>
          </span>
          <span class="flex min-w-0 flex-1 items-center justify-between gap-2" data-sb-text>
            <span class="truncate"><?= htmlspecialchars((string) ($item['label'] ?? 'Submenu'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
            <i class="fa-solid fa-chevron-down text-[10px] opacity-60 transition group-open/sidebar-submenu:rotate-180" aria-hidden="true"></i>
          </span>
        </summary>
        <div class="mt-1 space-y-1 pl-4" data-sb-text>
          <?php foreach ($children as $child): ?>
            <?php if (!sidebar_item_visible($child)) {
                continue;
            } ?>
            <a href="<?= url((string) ($child['href'] ?? '#')) ?>" class="<?= sidebar_item_class($current, (string) ($child['href'] ?? '#')) ?> text-xs" aria-label="<?= htmlspecialchars((string) ($child['label'] ?? 'Item'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
              <span class="sidebar-icon w-7 text-center shrink-0 text-[14px]" aria-hidden="true">
                <i class="<?= htmlspecialchars((string) ($child['icon'] ?? 'fa-solid fa-circle'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></i>
              </span>
              <span><?= htmlspecialchars((string) ($child['label'] ?? 'Item'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </details>
      <?php continue; ?>
    <?php endif; ?>
    <a href="<?= url($item['href']) ?>" class="<?= sidebar_item_class($current, $item['href']) ?>" data-sb-item<?= ($item['href'] === '/admin/auditoria-geral') ? ' data-admin-audit-trigger="1"' : '' ?> aria-label="<?= htmlspecialchars($item['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <span class="sidebar-icon w-7 text-center shrink-0 text-[18px]" aria-hidden="true" data-tooltip="<?= htmlspecialchars($item['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <i class="<?= htmlspecialchars($item['icon'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></i>
      </span>
      <span data-sb-text><?= htmlspecialchars($item['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
    </a>
  <?php endforeach; ?>
</nav>
