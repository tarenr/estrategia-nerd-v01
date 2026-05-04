<?php
use App\Support\View;

$siteName = (string) ($site_name ?? 'Estrategia Nerd');
$siteDescription = (string) ($site_description ?? '');
$siteKicker = (string) ($site_kicker ?? '');
$brandSymbol = (string) ($brand_symbol ?? '');
$brandWordPrimary = (string) ($brand_word_primary ?? 'ESTRATEGIA');
$brandWordAccent = (string) ($brand_word_accent ?? 'NERD');
$hero = $hero ?? [];
$eyebrow = (string) ($hero['eyebrow'] ?? 'Portal geek estrategico');
$descriptor = (string) ($hero['descriptor'] ?? 'tecnologia, games, gadgets e cultura geek para descobrir, comparar e decidir melhor');
$heroCopy = (string) ($hero['description'] ?? $siteDescription);
$primaryCta = is_array($hero['primary_cta'] ?? null) ? $hero['primary_cta'] : ['href' => url('/blog'), 'label' => 'Explorar o blog'];
$secondaryCta = is_array($hero['secondary_cta'] ?? null) ? $hero['secondary_cta'] : null;
$supportPoints = is_array($hero['support_points'] ?? null) ? $hero['support_points'] : [];
?>

<?= View::component('site/shared/nav', [
    'site_name' => $siteName,
    'site_kicker' => $siteKicker,
    'brand_symbol' => $brandSymbol,
    'brand_word_primary' => $brandWordPrimary,
    'brand_word_accent' => $brandWordAccent,
    'active_page' => 'home',
]) ?>

<section id="home" class="site-hero-section site-hero-preview-section relative min-h-screen flex items-center justify-center pt-24 overflow-hidden">
  <canvas id="siteHeroPreviewCanvas" class="site-hero-preview-canvas" aria-hidden="true"></canvas>
  <div class="site-hero-preview-shade" aria-hidden="true"></div>
  <div class="site-hero-preview-vignette" aria-hidden="true"></div>

  <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full text-center">
    <div class="mb-8 inline-flex items-center gap-3 px-4 py-2 rounded-full border border-cyan-500/30 bg-cyan-500/10 text-cyan-300 text-sm font-medium tracking-widest uppercase">
      <span class="w-2 h-2 rounded-full bg-cyan-300"></span>
      <?= htmlspecialchars($eyebrow, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </div>

    <h1 class="font-orbitron text-4xl md:text-6xl lg:text-7xl font-black mb-6 leading-tight max-w-5xl mx-auto">
      <span class="block text-white glitch" data-text="ESTRATEGIA">ESTRATEGIA</span>
      <span class="block text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 via-blue-500 to-purple-600 neon-text">NERD</span>
      <span class="mt-5 block font-rajdhani text-xl md:text-2xl lg:text-3xl font-semibold tracking-normal text-slate-200 max-w-4xl mx-auto">
        <?= htmlspecialchars($descriptor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
      </span>
    </h1>

    <p class="text-lg md:text-xl text-slate-300 max-w-3xl mx-auto mb-8 font-medium site-hero-copy leading-relaxed">
      <?= htmlspecialchars($heroCopy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </p>

    <?php if ($supportPoints !== []): ?>
      <div class="mb-10 flex flex-wrap justify-center gap-3 text-sm text-slate-200">
        <?php foreach ($supportPoints as $point): ?>
          <span class="px-4 py-2 rounded-full border border-slate-700/70 bg-slate-900/60"><?= htmlspecialchars((string) $point, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
      <a href="<?= htmlspecialchars((string) ($primaryCta['href'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="site-primary-btn"><?= htmlspecialchars((string) ($primaryCta['label'] ?? 'Explorar'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
      <?php if (is_array($secondaryCta)): ?>
        <a href="<?= htmlspecialchars((string) ($secondaryCta['href'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="site-secondary-btn"><?= htmlspecialchars((string) ($secondaryCta['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
      <?php endif; ?>
    </div>

    <div class="mt-5">
      <a href="#blog" class="site-hero-recent-link">Ver posts recentes</a>
    </div>
  </div>
</section>
