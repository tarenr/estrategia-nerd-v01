<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Views/layouts/site.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Layout base do site
 * @description Define o HTML base, inclui assets globais e assets condicionais da tela de login.
 * @usage       Usado pelo View para envolver as views (ex.: site/login) com cabeçalho/rodapé.
 * @notes       Em /login o layout fica “limpo” (sem header/nav/footer).
 * -----------------------------------------------------------------------------
 */

use App\Support\Auth;
use App\Support\Csrf;

$title = $title ?? 'Estratégia Nerd';

$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$rawPath = rtrim($rawPath, '/') ?: '/';

/**
 * Detecta login de forma robusta mesmo com base path (/projeto/public/login).
 * Exemplos aceitos:
 * - /login
 * - /login/
 * - /estrategia-nerd/public/login
 */
$isLogin = (bool)preg_match('#/login$#', $rawPath);
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars((string)$title) ?></title>

  <!-- Fonts (repo reference) -->
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

  <?php if ($isLogin): ?>
    <!-- Login CSS -->
    <link rel="stylesheet" href="<?= url('/assets/css/login.css') ?>">

    <!-- Font Awesome CDN (somente /login: ícones do formulário) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" referrerpolicy="no-referrer">
  <?php endif; ?>
</head>

<body class="bg-slate-950 text-slate-100">
  <?php if (!$isLogin): ?>
    <header class="border-b border-slate-800/70 bg-slate-950/70 backdrop-blur">
      <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
        <a href="<?= url('/') ?>" class="font-orbitron font-black tracking-wider">
          Estratégia <span class="text-cyan-400">Nerd</span>
        </a>

        <nav class="flex items-center gap-4 text-sm">
          <a href="<?= url('/') ?>" class="hover:text-cyan-300 transition">Home</a>
          <a href="<?= url('/dev') ?>" class="hover:text-cyan-300 transition">Dev</a>
          <a href="<?= url('/admin') ?>" class="hover:text-cyan-300 transition">Admin</a>

          <?php if (!Auth::check()): ?>
            <a href="<?= url('/login') ?>" class="hover:text-cyan-300 transition">Login</a>
          <?php else: ?>
            <form method="POST" action="<?= url('/logout') ?>" class="inline">
              <?= Csrf::field() ?>
              <button type="submit" class="hover:text-cyan-300 transition">
                Sair (<?= htmlspecialchars((string)(Auth::user()['usuario'] ?? '')) ?>)
              </button>
            </form>
          <?php endif; ?>
        </nav>
      </div>
    </header>
  <?php endif; ?>

  <main>
    <?= $content ?? '' ?>
  </main>

  <?php if (!$isLogin): ?>
    <footer class="border-t border-slate-800/70 mt-12">
      <div class="max-w-6xl mx-auto px-4 py-8 text-xs text-slate-400">
        © <?= date('Y') ?> Estratégia Nerd
      </div>
    </footer>
  <?php endif; ?>

  <?php if ($isLogin): ?>
    <!-- Login JS -->
    <script src="<?= url('/assets/js/login.js') ?>" defer></script>
  <?php endif; ?>
</body>
</html>