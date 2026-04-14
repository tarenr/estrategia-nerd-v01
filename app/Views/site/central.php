<?php
declare(strict_types=1);

use App\Support\View;

$siteMeta = $site_meta ?? [];
$featuredLink = is_array($central_featured_link ?? null) ? $central_featured_link : null;
$groups = is_array($central_groups ?? null) ? $central_groups : [];
$siteName = public_text((string) ($siteMeta['name'] ?? 'Estratégia Nerd'));
$bioTitle = public_text((string) ($siteMeta['bio_title'] ?? 'Central Nerd'));
$bioDescription = public_text((string) ($siteMeta['bio_description'] ?? 'Ofertas, descontos e novidades para geeks e tech lovers!'));
$brandLogo = (string) ($siteMeta['logo'] ?? '');
$brandSymbol = (string) ($siteMeta['brand_symbol'] ?? '');
$avatar = (string) ($siteMeta['avatar'] ?? '');
$brandSymbol = $brandSymbol !== '' ? $brandSymbol : url('/assets/brand/logo-symbol.png');
$avatar = $avatar !== '' ? $avatar : $brandSymbol;
$brandLogo = $brandLogo !== '' ? $brandLogo : url('/assets/brand/logo-main.png');

$socialLinks = [
    'tiktok' => ['url' => (string) ($siteMeta['tiktok'] ?? ''), 'label' => 'TikTok', 'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.5 4c.7 2 2.1 3.3 4.2 3.7v2.7a7.3 7.3 0 0 1-3.8-1.1v5.2a5.5 5.5 0 1 1-4.6-5.4v2.8a2.7 2.7 0 1 0 1.8 2.6V4h2.4Z" fill="currentColor"/></svg>'],
    'instagram' => ['url' => (string) ($siteMeta['instagram'] ?? ''), 'label' => 'Instagram', 'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5" ry="5" fill="none" stroke="currentColor" stroke-width="1.9"/><circle cx="12" cy="12" r="4.2" fill="none" stroke="currentColor" stroke-width="1.9"/><circle cx="17.3" cy="6.7" r="1.1" fill="currentColor"/></svg>'],
    'youtube' => ['url' => (string) ($siteMeta['youtube'] ?? ''), 'label' => 'YouTube', 'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.6 8.2a3 3 0 0 0-2.1-2.1C17.7 5.6 12 5.6 12 5.6s-5.7 0-7.5.5A3 3 0 0 0 2.4 8.2 31.6 31.6 0 0 0 2 12a31.6 31.6 0 0 0 .4 3.8 3 3 0 0 0 2.1 2.1c1.8.5 7.5.5 7.5.5s5.7 0 7.5-.5a3 3 0 0 0 2.1-2.1A31.6 31.6 0 0 0 22 12a31.6 31.6 0 0 0-.4-3.8ZM10 15.4V8.6l5.2 3.4L10 15.4Z" fill="currentColor"/></svg>'],
    'telegram' => ['url' => (string) ($siteMeta['telegram'] ?? ''), 'label' => 'Telegram', 'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m20.7 4.3-2.5 14.1c-.2 1-.8 1.2-1.7.7l-4.5-3.3-2.2 2.1c-.2.3-.5.5-1 .5l.3-4.6 8.4-7.6c.4-.3-.1-.5-.6-.2L6.5 12.6l-4.4-1.4c-.9-.3-1-.9.2-1.4L19 3.4c.8-.3 1.5.2 1.3.9Z" fill="currentColor"/></svg>'],
    'kwai' => ['url' => (string) ($siteMeta['kwai'] ?? ''), 'label' => 'Kwai', 'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8.2 5.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Zm7.6 0a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6ZM6.3 12.3h11.4a3.3 3.3 0 0 1 3.3 3.3v.3a3.9 3.9 0 0 1-3.9 3.9H6.9A3.9 3.9 0 0 1 3 15.9v-.3a3.3 3.3 0 0 1 3.3-3.3Zm2.2 2.2a1.7 1.7 0 1 0 0 3.4 1.7 1.7 0 0 0 0-3.4Zm7 0a1.7 1.7 0 1 0 0 3.4 1.7 1.7 0 0 0 0-3.4Z" fill="currentColor"/></svg>'],
    'whatsapp' => ['url' => (string) ($siteMeta['whatsapp'] ?? ''), 'label' => 'WhatsApp', 'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.2a8.7 8.7 0 0 0-7.5 13.1L3 21l4.9-1.3A8.8 8.8 0 1 0 12 3.2Zm0 15.8c-1.3 0-2.6-.4-3.6-1.1l-.3-.2-2.9.8.8-2.8-.2-.3A6.8 6.8 0 1 1 12 19Zm3.7-5.1c-.2-.1-1.3-.6-1.5-.7-.2-.1-.4-.1-.6.1l-.4.6c-.1.2-.3.2-.5.1a5.6 5.6 0 0 1-2.8-2.5c-.1-.2 0-.4.1-.5l.4-.5c.1-.2.2-.4 0-.6l-.7-1.6c-.1-.2-.3-.4-.6-.4h-.5c-.2 0-.5.1-.7.3-.7.6-1 1.4-.9 2.3.2 1.3.8 2.5 1.7 3.5a8 8 0 0 0 4.8 2.8c1 .1 2-.1 2.8-.7.4-.3.7-.9.8-1.4.1-.3 0-.5-.2-.6Z" fill="currentColor"/></svg>'],
];

$hasSocialLinks = array_reduce($socialLinks, static fn (bool $carry, array $social): bool => $carry || trim((string) ($social['url'] ?? '')) !== '', false);
$featuredTone = (string) ($featuredLink['tone'] ?? 'product');
$featuredType = (string) ($featuredLink['tipo'] ?? '');
$featuredIsCoupon = $featuredType === 'cupom';
$featuredDiscount = trim((string) ($featuredLink['discount_text'] ?? ($featuredLink['discount'] ?? '')));
$featuredDiscountContext = trim((string) ($featuredLink['discount_context'] ?? ''));
$featuredCouponCode = trim((string) ($featuredLink['coupon_code'] ?? ''));
$featuredTrackedUrl = $featuredLink !== null && trim((string) ($featuredLink['slug'] ?? '')) !== ''
    ? url('/link/' . rawurlencode((string) $featuredLink['slug']) . '?origem=central-destaque')
    : trim((string) ($featuredLink['url'] ?? '#'));
$quickLinks = is_array($central_quick_links ?? null) ? $central_quick_links : [];
?>

<div class="site-home-page central-nerd-page">
  <div class="circuit-bg"></div>
  <div class="scanline"></div>

  <main class="central-shell">
    <section class="central-hero">
      <div class="central-hero-backdrop" aria-hidden="true"></div>
      <div class="central-hero-panel" data-reveal>
        <div class="central-hero-logo-wrap">
          <img src="<?= htmlspecialchars($brandLogo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($siteName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="central-hero-logo">
        </div>

        <div class="central-hero-copy">
          <div class="central-hero-avatar">
            <img src="<?= htmlspecialchars($avatar, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($siteName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
          </div>
          <h1><?= htmlspecialchars($bioTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
          <p><?= htmlspecialchars($bioDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>

          <?php if ($hasSocialLinks): ?>
            <div class="central-social-row">
              <?php foreach ($socialLinks as $social): ?>
                <?php if (trim((string) ($social['url'] ?? '')) === '') { continue; } ?>
                <a href="<?= htmlspecialchars((string) $social['url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="central-social-link" aria-label="<?= htmlspecialchars((string) $social['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="<?= htmlspecialchars((string) $social['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                  <?= $social['icon'] ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if ($quickLinks !== []): ?>
            <div class="mt-4 flex flex-wrap justify-center gap-2">
              <?php foreach ($quickLinks as $quickLink): ?>
                <a href="<?= htmlspecialchars((string) ($quickLink['url'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-flex items-center rounded-full border border-cyan-500/25 bg-slate-900/65 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] text-cyan-200 hover:border-cyan-300/40 hover:text-cyan-100 transition">
                  <?= htmlspecialchars((string) ($quickLink['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <?php if ($featuredLink !== null): ?>
      <section class="central-featured" data-reveal>
        <div class="central-featured-header">
          <span class="central-featured-kicker"><?= htmlspecialchars(($featuredLink['promocao'] ?? false) ? 'Promoções' : 'Destaque da Central', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
        </div>

        <article class="central-featured-card central-link-card-<?= htmlspecialchars($featuredTone, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
          <div class="central-featured-icon">
            <?php if (trim((string) ($featuredLink['imagem'] ?? '')) !== ''): ?>
              <img src="<?= htmlspecialchars((string) $featuredLink['imagem'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($featuredLink['titulo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <?php else: ?>
              <span><?= htmlspecialchars(mb_substr((string) ($featuredLink['titulo'] ?? 'L'), 0, 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
            <?php endif; ?>
          </div>

          <div class="central-featured-copy">

            <h2><?= htmlspecialchars((string) ($featuredLink['titulo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
            <?php if (trim((string) ($featuredLink['descricao'] ?? '')) !== ''): ?>
              <p><?= htmlspecialchars((string) $featuredLink['descricao'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <?php endif; ?>

            <?php if ($featuredIsCoupon && ($featuredDiscount !== '' || $featuredDiscountContext !== '')): ?>
              <span class="central-link-extra central-link-extra-inline mt-2">
                <?php if ($featuredDiscount !== ''): ?><span class="central-link-discount"><?= htmlspecialchars($featuredDiscount, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span><?php endif; ?>
                <?php if ($featuredDiscountContext !== ''): ?><span class="central-link-context"><?= htmlspecialchars($featuredDiscountContext, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span><?php endif; ?>
              </span>
            <?php endif; ?>

            <?php if ($featuredIsCoupon): ?>
              <span class="central-coupon-actions mt-2">
                <?php if ($featuredCouponCode !== ''): ?>
                  <button
                    type="button"
                    class="central-coupon-copy-button"
                    data-copy-coupon="<?= htmlspecialchars($featuredCouponCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                    data-copy-coupon-trigger
                    aria-label="Copiar cupom <?= htmlspecialchars($featuredCouponCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                  >
                    <span class="central-coupon-copy-code"><?= htmlspecialchars($featuredCouponCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    <span class="central-coupon-copy-meta" data-copy-coupon-feedback>Toque para copiar</span>
                    <span class="central-coupon-copy-state" aria-hidden="true">Copiado</span>
                  </button>
                <?php else: ?>
                  <span class="central-coupon-no-code">Oferta sem código (acesso por link).</span>
                <?php endif; ?>
              </span>
            <?php endif; ?>
          </div>

          <a href="<?= htmlspecialchars((string) $featuredTrackedUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="central-featured-button">
            <span>Abrir</span>
          </a>
        </article>
      </section>
    <?php endif; ?>

    <section class="central-groups">
      <header class="mx-auto mb-6 max-w-3xl text-center">
        <h2 class="font-orbitron text-2xl font-bold text-white md:text-3xl">Arsenal Nerd</h2>
        <p class="mt-2 text-sm leading-7 text-slate-300 md:text-base">Escolha uma seção e acesse direto o que importa: ofertas, cupons, conteúdos e links oficiais.</p>
      </header>
      <?php if ($groups === []): ?>
        <article class="central-empty-state" data-reveal>
          <h2>Central em montagem</h2>
          <p>Os links oficiais ainda estão sendo organizados. Em breve esta área vai reunir ofertas, conteúdos e atalhos da marca em um único lugar.</p>
        </article>
      <?php else: ?>
        <?php foreach ($groups as $group): ?>
          <?= View::component('site/central/group', ['group' => $group]) ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
  </main>
</div>
