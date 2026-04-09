<?php
$siteName = (string) ($site_name ?? 'Estrategia Nerd');
$siteKicker = (string) ($site_kicker ?? '');
$brandSymbol = (string) ($brand_symbol ?? '');
$brandWordPrimary = (string) ($brand_word_primary ?? 'ESTRATEGIA');
$brandWordAccent = (string) ($brand_word_accent ?? 'NERD');
$activePage = (string) ($active_page ?? '');
$menuItems = site_menu_items();
$brandHref = site_section_visible_on_home('hero')
    ? site_section_href('hero')
    : (site_section_visible_on_home('sobre') ? site_section_href('sobre') : url('/'));

$getDesktopClass = static function (array $item) use ($activePage): string {
    $isActive = ($activePage === 'home' && (string) ($item['key'] ?? '') === 'hero')
        || $activePage === (string) ($item['key'] ?? '');

    if (!empty($item['is_cta'])) {
        return 'site-nav-cta';
    }

    return $isActive
        ? 'text-cyan-400 border-b-2 border-cyan-400'
        : 'hover:text-cyan-300 transition';
};
?>

<nav class="site-nav fixed top-0 inset-x-0 z-50">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between h-20">
      <a href="<?= htmlspecialchars($brandHref, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="flex items-center gap-3">
        <div class="site-brand-orb pulse-glow">
          <img src="<?= htmlspecialchars($brandSymbol, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($siteName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="site-brand-mark">
        </div>
        <div>
          <div class="site-brand-title">
            <span class="site-brand-title-main"><?= htmlspecialchars($brandWordPrimary, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span><span class="site-brand-title-accent"><?= htmlspecialchars($brandWordAccent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
          </div>
          <div class="site-brand-kicker"><?= htmlspecialchars($siteKicker, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        </div>
      </a>

      <div class="hidden md:flex items-center gap-8 text-sm font-semibold tracking-[0.16em] uppercase text-slate-300">
        <?php foreach ($menuItems as $item): ?>
          <a href="<?= htmlspecialchars((string) ($item['href'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="<?= htmlspecialchars($getDesktopClass($item), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <?= htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
          </a>
        <?php endforeach; ?>
      </div>

      <button type="button" class="md:hidden site-mobile-toggle" data-site-menu-toggle aria-expanded="false" aria-controls="siteMobileMenu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>

  <div id="siteMobileMenu" class="site-mobile-menu md:hidden" data-site-mobile-menu hidden>
    <div class="px-4 pb-4 flex flex-col gap-3 text-sm font-semibold tracking-[0.12em] uppercase text-slate-300">
      <?php foreach ($menuItems as $item): ?>
        <a href="<?= htmlspecialchars((string) ($item['href'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="site-mobile-link">
          <?= htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</nav>