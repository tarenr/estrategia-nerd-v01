<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Views/components/admin/sidebar.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.1.0
 * @purpose     Menu lateral do Admin (sidebar).
 * @description Menu flat com separadores. Tooltip no ícone. Ativo robusto (com base path).
 * @usage       Renderizado dentro do <aside id="adminSidebar">.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$currentPath = rtrim($currentPath, '/') ?: '/';

/**
 * Resolve basePath a partir da URL real do admin.
 * Ex.: url('/admin') => /estrategia-nerd/public/admin -> base = /estrategia-nerd/public
 */
$adminFullPath = parse_url(url('/admin'), PHP_URL_PATH) ?: '/admin';
$adminFullPath = rtrim($adminFullPath, '/') ?: '/admin';
$basePath = preg_replace('#/admin$#', '', $adminFullPath) ?? '';
$basePath = rtrim((string)$basePath, '/');

function strip_base(string $path, string $base): string
{
    $p = rtrim($path, '/') ?: '/';
    if ($base !== '' && str_starts_with($p . '/', $base . '/')) {
        $p = substr($p, strlen($base));
        $p = rtrim($p, '/') ?: '/';
    }
    return $p;
}

$current = strip_base($currentPath, $basePath);

function norm(string $p): string { return rtrim($p, '/') ?: '/'; }

function is_active(string $current, string $target): bool
{
    $c = norm($current);
    $t = norm($target);

    // Dashboard
    if ($t === '/admin') {
        return $c === '/admin';
    }

    return $c === $t || str_starts_with($c . '/', $t . '/');
}

function item_class(string $current, string $target): string
{
    $base = 'flex items-center gap-3 px-3 py-2 rounded-xl transition whitespace-nowrap';
    return is_active($current, $target)
        ? $base . ' bg-slate-900/60 text-cyan-300'
        : $base . ' hover:bg-slate-900/50 hover:text-cyan-300';
}

/**
 * @return array{href:string,icon:string,label:string,separator_before:bool}
 */
function item(string $href, string $icon, string $label, bool $sep = false): array
{
    return ['href' => $href, 'icon' => $icon, 'label' => $label, 'separator_before' => $sep];
}

$items = [
    item('/admin', '📊', 'Dashboard'),
    item('/', '🌐', 'Ver Site'),

    item('/admin/posts', '📝', 'Posts', true),
    item('/admin/criar-post', '✨', 'Criar Post'),
    item('/admin/categorias', '🏷️', 'Categorias'),
    item('/admin/comentarios', '💬', 'Comentários'),
    item('/admin/midia', '🖼️', 'Mídia'),

    item('/admin/inscritos', '👥', 'Inscritos', true),
    item('/admin/campanhas', '📨', 'Campanhas'),
    item('/admin/newsletter-stats', '📈', 'Estatísticas'),

    item('/admin/configuracoes', '⚙️', 'Configurações', true),
    item('/admin/usuarios', '👤', 'Usuários'),
    item('/admin/permissoes', '🔒', 'Permissões'),
    item('/admin/health', '🩺', 'Health Check'),
];

?>
<nav class="space-y-1 text-sm" aria-label="Navegação Admin">
  <?php foreach ($items as $it): ?>
    <?php if ($it['separator_before']): ?>
      <hr data-sb-sep class="my-2 border-slate-800/70">
    <?php endif; ?>

    <a
      href="<?= url($it['href']) ?>"
      class="<?= item_class($current, $it['href']) ?>"
      data-sb-item
      aria-label="<?= htmlspecialchars($it['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
    >
      <span
        class="w-7 text-center shrink-0 text-[20px]"
        aria-hidden="true"
        data-tooltip="<?= htmlspecialchars($it['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
      ><?= $it['icon'] ?></span>

      <span data-sb-text><?= htmlspecialchars($it['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
    </a>
  <?php endforeach; ?>
</nav>