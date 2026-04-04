<?php
declare(strict_types=1);

$posts = $blog_posts ?? [];
$siteMeta = $site_meta ?? [];
$siteName = (string) ($siteMeta['name'] ?? 'Estrategia Nerd');
$siteDescription = (string) ($siteMeta['description'] ?? '');
$siteKicker = (string) ($siteMeta['kicker'] ?? 'Portal geek estrategico');
$brandSymbol = (string) ($siteMeta['brand_symbol'] ?? '');
$brandSymbol = $brandSymbol !== '' ? $brandSymbol : url('/assets/brand/logo-symbol.png');
?>

<section class="site-about-section min-h-screen py-28 relative overflow-hidden">
  <div class="site-about-backdrop" aria-hidden="true"></div>
  <div class="site-about-grid" aria-hidden="true"></div>

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
    <div class="flex items-center gap-3 mb-10">
      <div class="site-brand-orb">
        <img src="<?= htmlspecialchars($brandSymbol, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($siteName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="site-brand-mark">
      </div>
      <div>
        <div class="site-brand-title">
          <span class="site-brand-title-main">ESTRATEGIA</span><span class="site-brand-title-accent">NERD</span>
        </div>
        <div class="site-brand-kicker"><?= htmlspecialchars($siteKicker, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      </div>
    </div>

    <header class="max-w-3xl mb-14">
      <span class="site-section-kicker">Blog</span>
      <h1 class="font-orbitron text-4xl md:text-5xl font-black text-white mt-3">Todas as publicacoes do portal</h1>
      <p class="text-slate-400 text-lg mt-4"><?= htmlspecialchars($siteDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
    </header>

    <?php if ($posts !== []): ?>
      <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-8">
        <?php foreach ($posts as $post): ?>
          <article class="nerd-card bg-slate-800/50 rounded-2xl overflow-hidden group">
            <a href="<?= htmlspecialchars((string) ($post['url'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="block">
              <div class="relative h-56 overflow-hidden bg-gradient-to-br from-cyan-700 to-blue-900">
                <?php if ((string) ($post['imagem'] ?? '') !== ''): ?>
                  <img src="<?= htmlspecialchars((string) $post['imagem'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($post['titulo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="absolute inset-0 h-full w-full object-cover opacity-85">
                <?php endif; ?>
                <div class="absolute top-4 left-4 px-3 py-1 bg-cyan-500 text-slate-900 text-xs font-bold rounded-full">
                  <?= htmlspecialchars(strtoupper((string) ($post['categoria_nome'] ?? 'BLOG')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </div>
              </div>

              <div class="p-6">
                <div class="flex items-center text-sm text-gray-400 mb-3">
                  <span><?= htmlspecialchars((string) ($post['data'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                  <span class="mx-2">&bull;</span>
                  <span><?= (int) ($post['tempo_leitura'] ?? 5) ?> min leitura</span>
                </div>
                <h2 class="font-orbitron text-xl font-bold text-white mb-3 group-hover:text-cyan-400 transition-colors">
                  <?= htmlspecialchars((string) ($post['titulo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </h2>
                <p class="text-gray-400 text-sm leading-7">
                  <?= htmlspecialchars((string) ($post['resumo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </p>
              </div>
            </a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="site-empty-panel">Ainda nao ha posts publicados para exibir no blog.</div>
    <?php endif; ?>
  </div>
</section>
