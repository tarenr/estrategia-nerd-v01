<?php
$siteName = (string) ($site_name ?? 'Estrategia Nerd');
$siteDescription = (string) ($site_description ?? '');
$siteKicker = (string) ($site_kicker ?? '');
$footerText = (string) ($footer_text ?? $siteName);
$siteEmail = (string) ($site_email ?? '');
$brandSymbol = (string) ($brand_symbol ?? '');
$socialLinks = is_array($social_links ?? null) ? $social_links : [];
$brandWordPrimary = (string) ($brand_word_primary ?? 'ESTRATEGIA');
$brandWordAccent = (string) ($brand_word_accent ?? 'NERD');
$hasSocialLinks = (bool) ($has_social_links ?? false);
$contactHref = (string) ($contact_href ?? '#newsletter');
?>

<footer class="site-footer">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid md:grid-cols-4 gap-12 mb-12">
      <div class="md:col-span-2">
        <div class="flex items-center gap-3 mb-4">
          <div class="site-footer-orb">
            <img src="<?= htmlspecialchars($brandSymbol, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($siteName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="site-brand-mark">
          </div>
          <span class="site-brand-title site-brand-title-footer">
            <span class="site-brand-title-main"><?= htmlspecialchars($brandWordPrimary, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span><span class="site-brand-title-accent"><?= htmlspecialchars($brandWordAccent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
          </span>
        </div>
        <p class="text-gray-400 mb-6 max-w-sm">
          <?= htmlspecialchars($siteDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </p>
        <?php if ($siteEmail !== ''): ?><div class="mb-6 text-sm text-cyan-300"><?= htmlspecialchars($siteEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($hasSocialLinks): ?>
          <div class="site-footer-badges">
            <?php foreach ($socialLinks as $social): ?>
              <?php if (trim((string) ($social['url'] ?? '')) === '') { continue; } ?>
              <a href="<?= htmlspecialchars((string) $social['url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="site-footer-social-badge" aria-label="<?= htmlspecialchars((string) $social['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="<?= htmlspecialchars((string) $social['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <?= $social['icon'] ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div>
        <h3 class="site-footer-title">Links rapidos</h3>
        <ul class="site-footer-links">
          <li><a href="<?= htmlspecialchars(url('/'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>#home">Home</a></li>
          <li><a href="<?= htmlspecialchars(url('/'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>#sobre">Sobre</a></li>
          <li><a href="<?= htmlspecialchars(url('/blog'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Blog</a></li>
          <li><a href="<?= htmlspecialchars(url('/'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>#newsletter">Newsletter</a></li>
        </ul>
      </div>

      <div>
        <h3 class="site-footer-title">Institucional</h3>
        <ul class="site-footer-links">
          <li><a href="<?= htmlspecialchars(url('/politica-de-privacidade'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Politica de privacidade</a></li>
          <li><a href="<?= htmlspecialchars(url('/termos-de-uso'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Termos de uso</a></li>
          <li><a href="<?= htmlspecialchars($contactHref, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= $siteEmail !== '' ? 'Contato' : 'Newsletter' ?></a></li>
        </ul>
      </div>
    </div>

    <div class="site-footer-bottom">
      <div><?= htmlspecialchars($footerText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <div class="site-footer-socials">
        <span><?= htmlspecialchars($siteKicker, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
      </div>
    </div>
  </div>
</footer>
