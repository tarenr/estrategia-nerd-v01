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
$hideEnvironmentSwitcher = (bool) preg_match('#/admin/(base-tecnica|central-operacional)$#', $rawPath);
$adminFlash = \App\Support\Session::pull('admin_flash');
$adminFlashType = is_array($adminFlash) ? trim((string) ($adminFlash['type'] ?? 'info')) : '';
$adminFlashMessage = is_array($adminFlash) ? trim((string) ($adminFlash['message'] ?? '')) : '';
$adminFlashClasses = [
    'warning' => 'border-amber-500/30 bg-amber-500/10 text-amber-100',
    'error' => 'border-rose-500/30 bg-rose-500/10 text-rose-100',
    'success' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-100',
    'info' => 'border-cyan-500/30 bg-cyan-500/10 text-cyan-100',
];
$adminFlashClass = $adminFlashClasses[$adminFlashType] ?? $adminFlashClasses['info'];
$currentEnvironment = current_environment();
$targetEnvironment = target_environment();
$showEnvironmentSwitcher = is_local_environment() && !$hideEnvironmentSwitcher;
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
  <div id="adminAuditLoadingOverlay" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-950/85 px-4 backdrop-blur-sm" aria-hidden="true">
    <div class="w-full max-w-lg rounded-[28px] border border-cyan-500/20 bg-slate-950/95 p-6 shadow-[0_0_60px_rgba(8,145,178,0.25)]">
      <div class="flex items-center gap-4">
        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border border-cyan-500/25 bg-cyan-500/10 text-cyan-200">
          <span class="inline-flex h-7 w-7 animate-spin rounded-full border-2 border-cyan-300/35 border-t-cyan-300"></span>
        </div>
        <div class="min-w-0">
          <div class="font-orbitron text-lg font-black text-white">Executando auditoria geral</div>
          <div id="adminAuditLoadingStep" class="mt-1 text-sm font-semibold text-cyan-100">Preparando a leitura dos ambientes.</div>
        </div>
      </div>
      <div class="mt-5 overflow-hidden rounded-full border border-slate-800 bg-slate-900/80">
        <div class="h-2 w-full origin-left bg-gradient-to-r from-cyan-400 via-sky-400 to-cyan-300 animate-pulse"></div>
      </div>
      <div id="adminAuditLoadingDetail" class="mt-4 text-sm leading-6 text-slate-300">
        Consultando banco, storage, rotas criticas e consistencia editorial de local, stage e producao.
      </div>
      <div class="mt-4 rounded-2xl border border-slate-800 bg-slate-900/70 px-4 py-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">
        A tela fica bloqueada ate a resposta voltar com o resultado atualizado.
      </div>
    </div>
  </div>
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
        <?php if ($adminFlashMessage !== ''): ?>
          <div class="mb-4 rounded-2xl border px-4 py-3 text-sm <?= htmlspecialchars($adminFlashClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <?= htmlspecialchars($adminFlashMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
          </div>
        <?php endif; ?>

        <?php if ($showEnvironmentSwitcher): ?>
          <section class="mb-4 rounded-2xl border border-cyan-500/20 bg-slate-950/50 backdrop-blur px-4 py-4">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
              <div class="space-y-2">
                <div class="text-xs font-bold uppercase tracking-[0.18em] text-cyan-300">Contexto multiambiente</div>
                <div class="flex flex-wrap items-center gap-2 text-sm text-slate-300">
                  <span class="inline-flex items-center gap-2 rounded-full border border-slate-700 bg-slate-900/70 px-3 py-1">
                    <span class="text-slate-400">Execucao:</span>
                    <strong class="text-white"><?= htmlspecialchars(environment_label($currentEnvironment), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
                  </span>
                  <span class="inline-flex items-center gap-2 rounded-full border border-cyan-500/20 bg-cyan-500/10 px-3 py-1">
                    <span class="text-cyan-200">Alvo:</span>
                    <strong class="text-white"><?= htmlspecialchars(environment_label($targetEnvironment), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
                  </span>
                </div>
                <div class="text-xs text-slate-400">O admin esta rodando no local. Os modulos multiambiente vao usar o alvo selecionado abaixo.</div>
              </div>

              <form method="POST" action="<?= htmlspecialchars(url('/admin/ambiente-alvo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <?= Csrf::field() ?>
                <input type="hidden" name="redirect_to" value="<?= htmlspecialchars((string) ($_SERVER['REQUEST_URI'] ?? url('/admin')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <label class="flex min-w-[180px] flex-col gap-2 text-xs font-bold uppercase tracking-[0.16em] text-slate-400">
                  <span>Ambiente alvo</span>
                  <select name="target_environment" class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm font-semibold normal-case tracking-normal text-white focus:border-cyan-400 focus:outline-none">
                    <?php foreach (\App\Support\EnvironmentManager::allowedTargets() as $environmentOption): ?>
                      <option value="<?= htmlspecialchars($environmentOption, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= $environmentOption === $targetEnvironment ? ' selected' : '' ?>>
                        <?= htmlspecialchars(environment_label($environmentOption), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <button type="submit" class="admin-btn admin-btn-secondary whitespace-nowrap">Trocar ambiente</button>
              </form>
            </div>
          </section>
        <?php endif; ?>

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

      <button
        type="button"
        id="adminBackToTop"
        class="fixed bottom-5 right-5 z-40 hidden h-11 w-11 items-center justify-center rounded-full border border-cyan-500/30 bg-slate-950/90 text-cyan-200 shadow-[0_0_24px_rgba(6,182,212,0.16)] transition hover:border-cyan-300 hover:bg-cyan-500/10 hover:text-white"
        aria-label="Voltar ao inicio da tela"
        title="Voltar ao inicio"
      >
        <i class="fa-solid fa-arrow-up text-sm" aria-hidden="true"></i>
      </button>

      <script src="<?= url('/assets/js/admin-layout.js?v=' . $adminLayoutJsVersion) ?>" defer></script>
      <?php if ($isAdminDashboard): ?>
        <script src="<?= url('/assets/js/admin-dashboard.js?v=' . $adminDashboardJsVersion) ?>" defer></script>
      <?php endif; ?>
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          var overlay = document.getElementById('adminAuditLoadingOverlay');
          if (!overlay) {
            return;
          }

          var stepNode = document.getElementById('adminAuditLoadingStep');
          var detailNode = document.getElementById('adminAuditLoadingDetail');
          var phaseTimer = null;
          var phases = [
            {
              title: 'Preparando a leitura dos ambientes.',
              detail: 'Reunindo a configuracao base para comparar local, stage e producao com a mesma regua.'
            },
            {
              title: 'Consultando banco e configuracoes.',
              detail: 'Lendo posts, categorias, links e os dados editoriais que entram na auditoria.'
            },
            {
              title: 'Validando storage e midias.',
              detail: 'Conferindo capa, thumb, referencias do HTML e a disponibilidade dos uploads principais.'
            },
            {
              title: 'Checando rotas e consolidando achados.',
              detail: 'Testando rotas criticas e preparando o resumo final da leitura.'
            }
          ];

          var showOverlay = function () {
            overlay.classList.remove('hidden');
            overlay.classList.add('flex');
            document.body.classList.add('overflow-hidden');

            var index = 0;
            if (stepNode) {
              stepNode.textContent = phases[0].title;
            }
            if (detailNode) {
              detailNode.textContent = phases[0].detail;
            }

            if (phaseTimer !== null) {
              window.clearInterval(phaseTimer);
            }

            phaseTimer = window.setInterval(function () {
              index = (index + 1) % phases.length;
              if (stepNode) {
                stepNode.textContent = phases[index].title;
              }
              if (detailNode) {
                detailNode.textContent = phases[index].detail;
              }
            }, 1200);
          };

        document.addEventListener('click', function (event) {
          var trigger = event.target.closest('[data-admin-audit-trigger]');
          if (!trigger) {
            return;
          }

          showOverlay();
        });

        var backToTopButton = document.getElementById('adminBackToTop');
        if (backToTopButton) {
          var toggleBackToTop = function () {
            var shouldShow = window.scrollY > 320;
            backToTopButton.classList.toggle('hidden', !shouldShow);
            backToTopButton.classList.toggle('flex', shouldShow);
          };

          backToTopButton.addEventListener('click', function () {
            window.scrollTo({
              top: 0,
              behavior: 'smooth'
            });
          });

          window.addEventListener('scroll', toggleBackToTop, { passive: true });
          toggleBackToTop();
        }
      });
      </script>
    </div>
</body>

</html>
