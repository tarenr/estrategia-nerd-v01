<?php

declare(strict_types=1);

use App\Support\Auth;
use App\Support\Csrf;

$title = (string) ($title ?? (string) portal_config('meta_title_padrao', 'Estrategia Nerd'));
$metaDescription = (string) ($meta_description ?? (string) portal_config('meta_description_padrao', portal_config('descricao_site', 'Estrategia Nerd')));
$bodyClass = (string) ($body_class ?? '');

$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$rawPath = rtrim($rawPath, '/') ?: '/';
$isLogin = (bool) preg_match('#/login$#', $rawPath);
$showSiteChrome = !$isLogin && (($site_chrome ?? true) === true);
$homePage = (bool) ($home_page ?? false);

$siteCssPath = dirname(__DIR__, 3) . '/public/assets/css/site.css';
$siteHomeJsPath = dirname(__DIR__, 3) . '/public/assets/js/site-home.js';
$siteCssVersion = is_file($siteCssPath) ? (string) filemtime($siteCssPath) : '1';
$siteHomeJsVersion = is_file($siteHomeJsPath) ? (string) filemtime($siteHomeJsPath) : '1';
$brandLogo = (string) portal_config('logo_url', '');
$brandSymbol = (string) portal_config('brand_symbol_url', '');
$brandFavicon = (string) portal_config('favicon_url', '');
$brandLogo = $brandLogo !== '' ? (preg_match('~^https?://~i', $brandLogo) ? $brandLogo : url('/' . ltrim($brandLogo, '/'))) : url('/assets/brand/logo-main.png');
$brandSymbol = $brandSymbol !== '' ? (preg_match('~^https?://~i', $brandSymbol) ? $brandSymbol : url('/' . ltrim($brandSymbol, '/'))) : url('/assets/brand/logo-symbol.png');
$brandFavicon = $brandFavicon !== '' ? (preg_match('~^https?://~i', $brandFavicon) ? $brandFavicon : url('/' . ltrim($brandFavicon, '/'))) : url('/assets/brand/favicon.ico');
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
  <meta name="description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($brandFavicon, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet">

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
    <link rel="stylesheet" href="<?= url('/assets/css/login.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" referrerpolicy="no-referrer">
  <?php else: ?>
    <link rel="stylesheet" href="<?= url('/assets/css/site.css?v=' . $siteCssVersion) ?>">
  <?php endif; ?>
</head>

<body class="bg-slate-950 text-slate-100 <?= htmlspecialchars($bodyClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
  <?php if ($showSiteChrome): ?>
    <header class="border-b border-slate-800/70 bg-slate-950/70 backdrop-blur">
      <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
        <a href="<?= url('/') ?>" class="font-orbitron font-black tracking-wider">
          <?= htmlspecialchars((string) portal_config('nome_site', 'Estrategia Nerd'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </a>

        <nav class="flex items-center gap-4 text-sm">
          <a href="<?= url('/') ?>" class="hover:text-cyan-300 transition">Home</a>
          <a href="<?= url('/admin') ?>" class="hover:text-cyan-300 transition">Admin</a>
          <?php if (!Auth::check()): ?>
            <a href="<?= url('/login') ?>" class="hover:text-cyan-300 transition">Login</a>
          <?php else: ?>
            <form method="POST" action="<?= url('/logout') ?>" class="inline">
              <?= Csrf::field() ?>
              <button type="submit" class="hover:text-cyan-300 transition">Sair</button>
            </form>
          <?php endif; ?>
        </nav>
      </div>
    </header>
  <?php endif; ?>

  <main>
    <?= $content ?? '' ?>
  </main>

  <?php if ($showSiteChrome): ?>
    <footer class="border-t border-slate-800/70 mt-12">
      <div class="max-w-6xl mx-auto px-4 py-8 text-xs text-slate-400">
        <?= htmlspecialchars((string) portal_config('footer_texto', 'Estrategia Nerd'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
      </div>
    </footer>
  <?php endif; ?>

  <?php if ($isLogin): ?>
    <script src="<?= url('/assets/js/login.js') ?>" defer></script>
  <?php endif; ?>
  <?php if (!$isLogin): ?>
    <script src="<?= url('/assets/js/site-home.js?v=' . $siteHomeJsVersion) ?>" defer></script>
  <?php endif; ?>
</body>
</html>
