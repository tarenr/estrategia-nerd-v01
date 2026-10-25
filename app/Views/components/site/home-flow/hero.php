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

<script>document.documentElement.classList.add('en-flow-js');</script>
<section id="home" class="site-hero-section en-flow-hero" data-flow-hero>
  <div class="en-flow-hero__stage" aria-hidden="true">
    <div class="en-flow-hero__shade"></div>
  </div>

  <canvas class="en-flow-portal" data-flow-portal aria-hidden="true" hidden></canvas>
  <canvas class="en-flow-echo" data-flow-echo aria-hidden="true" hidden></canvas>

  <div class="en-flow-hero__content">
    <div class="en-flow-hero__eyebrow" data-hero-animate="eyebrow">
      <span class="en-flow-hero__eyebrow-dot"></span>
      <?= htmlspecialchars($eyebrow, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </div>

    <h1 class="en-flow-hero__title font-orbitron">
      <span class="en-flow-hero__title-main glitch" data-text="<?= htmlspecialchars($brandWordPrimary, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-hero-animate="title-main"><?= htmlspecialchars($brandWordPrimary, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
      <span class="en-flow-hero__title-accent" data-hero-animate="title-accent"><?= htmlspecialchars($brandWordAccent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
      <span class="en-flow-hero__descriptor font-rajdhani" data-hero-animate="descriptor">
        <?= htmlspecialchars($descriptor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
      </span>
    </h1>

    <p class="en-flow-hero__copy" data-hero-animate="copy">
      <?= htmlspecialchars($heroCopy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </p>

    <?php if ($supportPoints !== []): ?>
      <div class="en-flow-hero__points">
        <?php foreach ($supportPoints as $point): ?>
          <span data-hero-animate="support"><?= htmlspecialchars((string) $point, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="en-flow-hero__actions" data-hero-animate="actions">
      <a href="<?= htmlspecialchars((string) ($primaryCta['href'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="site-primary-btn" data-hero-button><?= htmlspecialchars((string) ($primaryCta['label'] ?? 'Explorar'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
      <?php if (is_array($secondaryCta)): ?>
        <a href="<?= htmlspecialchars((string) ($secondaryCta['href'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="site-secondary-btn" data-hero-button><?= htmlspecialchars((string) ($secondaryCta['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
      <?php endif; ?>
    </div>

    <div class="en-flow-hero__recent" data-hero-animate="recent-link">
      <a href="#blog" class="site-hero-recent-link">Ver posts recentes</a>
    </div>
  </div>

  <div class="en-flow-hero__bus-exit" aria-hidden="true" data-flow-exit></div>
</section>
