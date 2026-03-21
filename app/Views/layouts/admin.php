<?php

/**
 * -----------------------------------------------------------------------------
 * @file        app/Views/layouts/admin.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.1.3
 * @purpose     Layout base do painel administrativo
 * @description Sidebar colapsável + toggle flutuante. Header usa o mesmo ícone do login (fa-brain).
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

use App\Support\Auth;
use App\Support\Csrf;
use App\Support\View;

$title = $title ?? 'Admin — Estratégia Nerd';

$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$rawPath = rtrim($rawPath, '/') ?: '/';

$isAdminDashboard = (bool)preg_match('#/admin$#', $rawPath);
?>
<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars((string)$title) ?></title>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;600;700&display=swap" rel="stylesheet">

  <!-- Font Awesome (para usar o mesmo brain do login) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <!-- Tailwind CDN -->
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

  <!-- Admin CSS -->
  <link rel="stylesheet" href="<?= url('/assets/css/admin.css') ?>">
</head>

<body class="bg-slate-950 text-slate-100 min-h-screen">
  <div class="min-h-screen flex flex-col">
    <header class="border-b border-slate-800/70 bg-slate-950/70 backdrop-blur">
      <div class="w-full px-4 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3 min-w-0">
          <!-- Mesma “caixa do cérebro” do login -->
          <div class="logo-icon w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-400 to-blue-600 flex items-center justify-center shadow-lg shadow-cyan-500/30 shrink-0" aria-hidden="true">
            <i class="fa-solid fa-brain text-white text-[18px]" aria-hidden="true"></i>
          </div>
          <span class="sr-only">Logo</span>

          <div class="min-w-0">
            <div class="font-orbitron font-black tracking-wider leading-none truncate">
              ADMIN <span class="text-cyan-400">NERD</span>
            </div>
            <div class="text-[11px] text-slate-400 tracking-widest uppercase truncate">
              Painel Administrativo
            </div>
          </div>
        </div>

        <div class="flex items-center gap-4 text-sm shrink-0">
          <span class="text-slate-400 hidden sm:inline">
            <?= htmlspecialchars((string)(Auth::user()['usuario'] ?? '')) ?>
          </span>

          <form method="POST" action="<?= url('/logout') ?>">
            <?= Csrf::field() ?>
            <button type="submit" class="px-3 py-2 rounded-lg border border-slate-700 hover:border-cyan-400/60 hover:text-cyan-300 transition">
              Sair
            </button>
          </form>
        </div>
      </div>
    </header>

    <div class="flex-1 flex w-full min-w-0">
      <div id="adminSidebarWrap" class="relative shrink-0">
        <aside
          id="adminSidebar"
          data-collapsed="0"
          class="w-[260px] border-r border-slate-800/70 bg-slate-950/60 backdrop-blur transition-[width] duration-200 ease-out overflow-visible">
          <div class="h-full p-4 overflow-y-auto" id="adminSidebarScroll">
            <?php View::component('admin/sidebar'); ?>
          </div>
        </aside>

        <button
          type="button"
          id="sidebarToggle"
          class="absolute top-6 -right-3 w-8 h-8 rounded-full border border-slate-800/70 bg-slate-950 flex items-center justify-center shadow-lg hover:border-cyan-400/60 hover:text-cyan-300 transition z-50"
          aria-controls="adminSidebar"
          aria-expanded="true"
          data-tooltip="Recolher menu">
          <span id="sidebarToggleIcon" aria-hidden="true">⟪</span>
          <span class="sr-only" id="sidebarToggleSr">Recolher menu</span>
        </button>
      </div>

      <main class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 relative z-0">
        <div class="rounded-2xl border border-slate-800/70 bg-slate-950/40 backdrop-blur p-6">
          <?= $content ?? '' ?>
        </div>
      </main>
    </div>

    <footer class="border-t border-slate-800/70 mt-10">
      <div class="w-full px-4 py-8 text-xs text-slate-500">
        © <?= date('Y') ?> Estratégia Nerd — Admin
      </div>
    </footer>

    <script src="<?= url('/assets/js/admin-layout.js') ?>" defer></script>

    <?php if ($isAdminDashboard): ?>
      <script src="<?= url('/assets/js/admin-dashboard.js') ?>" defer></script>
    <?php endif; ?>
  </div>
</body>

</html>