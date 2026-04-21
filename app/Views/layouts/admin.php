<?php

/**
 * -----------------------------------------------------------------------------
 * @file        app/Views/layouts/admin.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.1.4
 * @purpose     Layout base do painel administrativo
 * @description Sidebar colapsavel com navegacao padronizada do admin.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

use App\Support\Auth;
use App\Support\Csrf;
use App\Support\View;

$title = $title ?? 'Admin';

$adminCssPath = dirname(__DIR__, 2) . '/public/assets/css/admin.css';
$adminLayoutJsPath = dirname(__DIR__, 2) . '/public/assets/js/admin-layout.js';
$adminDashboardJsPath = dirname(__DIR__, 2) . '/public/assets/js/admin-dashboard.js';

$adminCssVersion = is_file($adminCssPath) ? (string) filemtime($adminCssPath) : '1';
$adminLayoutJsVersion = is_file($adminLayoutJsPath) ? (string) filemtime($adminLayoutJsPath) : '1';
$adminDashboardJsVersion = is_file($adminDashboardJsPath) ? (string) filemtime($adminDashboardJsPath) : '1';
$adminBuildVersion = max((int) $adminCssVersion, (int) $adminLayoutJsVersion, (int) $adminDashboardJsVersion);

$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$rawPath = rtrim($rawPath, '/') ?: '/';

$isAdminDashboard = (bool) preg_match('#/admin$#', $rawPath);
$user = Auth::user() ?? [];
$userName = trim((string) ($user['nome'] ?? $user['usuario'] ?? 'Equipe'));
if ($userName === '') {
    $userName = 'Equipe';
}

$userRoleKey = trim((string) ($user['papel'] ?? 'admin'));
$userRoleMap = [
    'admin' => 'Administrador',
    'editor' => 'Editor',
];
$userRole = $userRoleMap[$userRoleKey] ?? 'Equipe';

$userAvatarType = trim((string) ($user['avatar_tipo'] ?? 'icone'));
$userAvatarIcon = trim((string) ($user['avatar_icone'] ?? 'fa-solid fa-user'));
$userAvatarColor = trim((string) ($user['avatar_cor'] ?? '#38bdf8'));
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $userAvatarColor)) {
    $userAvatarColor = '#38bdf8';
}

$userAvatarImage = trim((string) ($user['avatar_imagem'] ?? ''));
$userAvatarFocalX = max(0.0, min(100.0, (float) ($user['avatar_focal_x'] ?? 50.0)));
$userAvatarFocalY = max(0.0, min(100.0, (float) ($user['avatar_focal_y'] ?? 50.0)));
$userAvatarObjectPosition = 'object-position: ' . number_format($userAvatarFocalX, 2, '.', '') . '% ' . number_format($userAvatarFocalY, 2, '.', '') . '%;';
$userAvatarUrl = '';
if ($userAvatarType === 'foto' && $userAvatarImage !== '') {
    $userAvatarUrl = preg_match('~^https?://~i', $userAvatarImage)
        ? $userAvatarImage
        : url('/' . ltrim($userAvatarImage, '/'));
}

$userAvatarStyle = $userAvatarUrl === ''
    ? 'background: linear-gradient(135deg, ' . $userAvatarColor . ', rgba(15, 23, 42, 0.92));'
    : '';

$adminFavicon = (string) portal_config('favicon_url', '');
$adminFavicon = $adminFavicon !== ''
    ? (preg_match('~^https?://~i', $adminFavicon) ? $adminFavicon : url('/' . ltrim($adminFavicon, '/')))
    : url('/assets/brand/favicon.ico');
?>
<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title><?= htmlspecialchars((string) $title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($adminFavicon, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            orbitron: ['Orbitron', 'ui-sans-serif', 'system-ui'],
            rajdhani: ['Rajdhani', 'ui-sans-serif', 'system-ui'],
          }
        }
      }
    };
  </script>

  <link rel="stylesheet" href="<?= url('/assets/css/admin.css?v=' . $adminCssVersion) ?>">
</head>

<body class="bg-slate-950 text-slate-100 min-h-screen" data-admin-build="<?= htmlspecialchars((string) $adminBuildVersion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
  <div class="min-h-screen flex w-full min-w-0">
      <div id="adminSidebarWrap" class="relative shrink-0">
        <aside id="adminSidebar" data-collapsed="0" class="w-[260px] border-r border-slate-800/70 bg-slate-950/60 backdrop-blur transition-[width] duration-200 ease-out overflow-visible">
          <div class="h-full p-4 overflow-y-auto overflow-x-hidden" id="adminSidebarScroll">
            <div class="admin-sidebar-shell">
              <div class="admin-sidebar-top">
                <div class="admin-sidebar-brand">
                  <div class="admin-sidebar-brand-icon" aria-hidden="true">
                    <i class="fa-solid fa-brain"></i>
                  </div>
                  <div class="min-w-0" data-sb-text>
                    <div class="admin-sidebar-brand-title">ADMIN <span class="text-cyan-400">NERD</span></div>
                    <div class="admin-sidebar-brand-subtitle">Painel Administrativo</div>
                  </div>
                </div>

                <div class="mt-6">
                  <?php View::component('admin/sidebar'); ?>
                </div>
              </div>

              <div class="admin-sidebar-footer">
                <div class="admin-sidebar-user-card">
                  <div class="admin-sidebar-user-thumb<?= $userAvatarUrl !== '' ? ' has-photo' : '' ?>" aria-hidden="true"<?php if ($userAvatarStyle !== ''): ?> style="<?= htmlspecialchars($userAvatarStyle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?php endif; ?>>
                    <?php if ($userAvatarUrl !== ''): ?>
                      <img src="<?= htmlspecialchars($userAvatarUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($userName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" style="<?= htmlspecialchars($userAvatarObjectPosition, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <?php else: ?>
                      <i class="<?= htmlspecialchars($userAvatarIcon, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></i>
                    <?php endif; ?>
                  </div>
                  <div class="min-w-0" data-sb-text>
                    <div class="admin-sidebar-user-name"><?= htmlspecialchars($userName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                    <div class="admin-sidebar-user-role"><?= htmlspecialchars($userRole, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  </div>
                </div>

                <form method="POST" action="<?= url('/logout') ?>" class="admin-sidebar-logout-form">
                  <?= Csrf::field() ?>
                  <button type="submit" class="admin-sidebar-logout" data-tooltip="Sair">
                    <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                    <span data-sb-text>Sair</span>
                  </button>
                </form>
              </div>
            </div>
          </div>
        </aside>

        <button type="button" id="sidebarToggle" class="sidebar-toggle-btn sidebar-toggle-edge" aria-controls="adminSidebar" aria-expanded="true" data-tooltip="Recolher menu">
          <i id="sidebarToggleIcon" class="fa-solid fa-chevron-left text-[12px] transition-transform duration-200 ease-out" aria-hidden="true"></i>
          <span class="sr-only" id="sidebarToggleSr">Recolher menu</span>
        </button>
      </div>

      <main class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 relative z-0 flex flex-col">
        <div class="rounded-2xl border border-slate-800/70 bg-slate-950/40 backdrop-blur p-6 flex-1">
          <?= $content ?? '' ?>
        </div>

        <footer class="mt-6">
          <div class="w-full px-2 py-2 text-xs text-slate-500 flex flex-wrap items-center justify-between gap-3">
            <span>&copy; <?= date('Y') ?> Estrategia Nerd - Admin</span>
            <span class="inline-flex flex-wrap items-center gap-3">
              <span>build <?= htmlspecialchars((string) $adminBuildVersion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
              <button type="button" class="rounded-lg border border-slate-700 px-2 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-300 hover:border-cyan-400 hover:text-cyan-200" data-admin-hard-refresh>Atualizar sem cache</button>
            </span>
          </div>
        </footer>
      </main>

      <script src="<?= url('/assets/js/admin-layout.js?v=' . $adminLayoutJsVersion) ?>" defer></script>
      <?php if ($isAdminDashboard): ?>
        <script src="<?= url('/assets/js/admin-dashboard.js?v=' . $adminDashboardJsVersion) ?>" defer></script>
      <?php endif; ?>
    </div>
</body>

</html>
