<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Views/layouts/admin.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Layout base do painel administrativo
 * @description Define a estrutura HTML do Admin com menu próprio e assets do admin.
 * @usage       Usado por views do namespace admin/* (ex.: admin/dashboard).
 * @notes       Carrega admin.css global do admin e admin-dashboard.js apenas em /admin.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

use App\Support\Auth;
use App\Support\Csrf;

$title = $title ?? 'Admin — Estratégia Nerd';

$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$rawPath = rtrim($rawPath, '/') ?: '/';

/**
 * Detecta dashboard de forma robusta mesmo com base path (/projeto/public/admin).
 */
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

  <!-- Tailwind CDN (repo reference) -->
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

  <!-- Admin CSS (repo reference) -->
  <link rel="stylesheet" href="<?= url('/assets/css/admin.css') ?>">
</head>

<body class="bg-slate-950 text-slate-100">
  <header class="border-b border-slate-800/70 bg-slate-950/70 backdrop-blur">
    <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <span class="inline-flex w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-400 to-blue-600 items-center justify-center shadow-lg shadow-cyan-500/20" aria-hidden="true">
          🧠
        </span>

        <div>
          <div class="font-orbitron font-black tracking-wider leading-none">
            ADMIN <span class="text-cyan-400">NERD</span>
          </div>
          <div class="text-[11px] text-slate-400 tracking-widest uppercase">
            Painel Administrativo
          </div>
        </div>
      </div>

      <div class="flex items-center gap-4 text-sm">
        <span class="text-slate-400">
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

  <div class="max-w-7xl mx-auto px-4 py-8 grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">
    <aside class="rounded-2xl border border-slate-800/70 bg-slate-950/60 backdrop-blur p-4">
      <nav class="space-y-1 text-sm">
        <a href="<?= url('/admin') ?>" class="block px-3 py-2 rounded-xl hover:bg-slate-900/50 hover:text-cyan-300 transition">
          Dashboard
        </a>
        <a href="<?= url('/') ?>" class="block px-3 py-2 rounded-xl hover:bg-slate-900/50 hover:text-cyan-300 transition">
          Ver Site
        </a>

        <div class="pt-2 mt-2 border-t border-slate-800/70 text-[11px] uppercase tracking-widest text-slate-500">
          Em breve
        </div>
        <span class="block px-3 py-2 rounded-xl text-slate-600 cursor-not-allowed">
          Posts
        </span>
        <span class="block px-3 py-2 rounded-xl text-slate-600 cursor-not-allowed">
          Configurações
        </span>
      </nav>
    </aside>

    <main class="rounded-2xl border border-slate-800/70 bg-slate-950/40 backdrop-blur p-6">
      <?= $content ?? '' ?>
    </main>
  </div>

  <footer class="border-t border-slate-800/70 mt-10">
    <div class="max-w-7xl mx-auto px-4 py-8 text-xs text-slate-500">
      © <?= date('Y') ?> Estratégia Nerd — Admin
    </div>
  </footer>

  <?php if ($isAdminDashboard): ?>
    <!-- Admin Dashboard JS (live update) -->
    <script src="<?= url('/assets/js/admin-dashboard.js') ?>" defer></script>
  <?php endif; ?>
</body>
</html>