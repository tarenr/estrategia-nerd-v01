<?php
declare(strict_types=1);

$group = is_array($group ?? null) ? $group : [];
$items = is_array($group['items'] ?? null) ? $group['items'] : [];
$groupSlug = (string) ($group['slug'] ?? 'grupo');
$groupLabel = (string) ($group['label'] ?? 'Grupo');
$groupSubtitle = (string) ($group['subtitle'] ?? '');
$groupTone = (string) ($group['tone'] ?? 'promo');
$groupOpen = (bool) ($group['open'] ?? false);
?>
<details class="central-accordion central-accordion-<?= htmlspecialchars($groupTone, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-central-accordion <?= $groupOpen ? 'open' : '' ?>>
  <summary class="central-accordion-summary">
    <span class="central-accordion-summary-copy">
      <span class="central-accordion-title"><?= htmlspecialchars($groupLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
      <?php if ($groupSubtitle !== ''): ?><span class="central-accordion-meta"><?= htmlspecialchars($groupSubtitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span><?php endif; ?>
    </span>
    <span class="central-accordion-chevron" aria-hidden="true">
      <svg viewBox="0 0 20 20" fill="none">
        <path d="m5 7.5 5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </span>
  </summary>

  <div class="central-accordion-panel" id="central-<?= htmlspecialchars($groupSlug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <div class="central-link-list">
      <?php foreach ($items as $item): ?>
        <?php
        $title = trim((string) ($item['titulo'] ?? ''));
        $description = trim((string) ($item['descricao'] ?? ''));
        $image = trim((string) ($item['imagem'] ?? ''));
        $url = trim((string) ($item['url'] ?? '#'));
        $badge = trim((string) ($item['selo'] ?? ''));
        $isCoupon = (string) ($item['tipo'] ?? '') === 'cupom';
        $couponCode = trim((string) ($item['coupon_code'] ?? ''));
        $discount = trim((string) ($item['discount'] ?? ''));
        $discountContext = trim((string) ($item['discount_context'] ?? ''));
        $copyValue = $couponCode !== '' ? $couponCode : ($title !== '' ? $title : 'CUPOM');
        $hasExternalUrl = $url !== '' && $url !== '#';
        $trackedUrl = trim((string) ($item['slug'] ?? '')) !== ''
            ? url('/link/' . rawurlencode((string) $item['slug']) . '?origem=central-' . rawurlencode($groupSlug))
            : $url;
        ?>
        <article class="central-link-card central-link-card-<?= htmlspecialchars((string) ($item['tone'] ?? 'product'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> <?= $isCoupon ? 'central-link-card-coupon-mode' : '' ?>" data-reveal>
          <?php if ($isCoupon): ?>
            <div class="central-bio-card central-bio-card-coupon">
              <span class="central-bio-media <?= $image === '' ? 'central-bio-media-fallback' : '' ?>">
                <?php if ($image !== ''): ?>
                  <img src="<?= htmlspecialchars($image, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <?php else: ?>
                  <span><?= htmlspecialchars(mb_substr($title !== '' ? $title : 'C', 0, 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <?php endif; ?>
              </span>
              <span class="central-bio-copy">
                <?php if ($badge !== ''): ?><span class="central-bio-badge"><?= htmlspecialchars($badge, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span><?php endif; ?>
                <span class="central-bio-title"><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <?php if ($description !== ''): ?><span class="central-bio-description"><?= htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span><?php endif; ?>
                <?php if ($discount !== '' || $discountContext !== ''): ?>
                  <span class="central-link-extra central-link-extra-inline">
                    <?php if ($discount !== ''): ?><span class="central-link-discount"><?= htmlspecialchars($discount, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> OFF</span><?php endif; ?>
                    <?php if ($discountContext !== ''): ?><span class="central-link-context"><?= htmlspecialchars($discountContext, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span><?php endif; ?>
                  </span>
                <?php endif; ?>

                <span class="central-coupon-actions">
                  <button
                    type="button"
                    class="central-coupon-copy-button"
                    data-copy-coupon="<?= htmlspecialchars($copyValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                    data-copy-coupon-trigger
                    aria-label="Copiar cupom <?= htmlspecialchars($copyValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                  >
                    <span class="central-coupon-copy-code"><?= htmlspecialchars($copyValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    <span class="central-coupon-copy-meta" data-copy-coupon-feedback>Toque para copiar</span>
                    <span class="central-coupon-copy-state" aria-hidden="true">Copiado</span>
                  </button>

                  <?php if ($hasExternalUrl): ?>
                    <a href="<?= htmlspecialchars($trackedUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="central-coupon-site-button" aria-label="Abrir site do cupom <?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                      <span>Ir ao site</span>
                      <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M6.5 10h7m0 0-2.8-2.8M13.5 10l-2.8 2.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                      </svg>
                    </a>
                  <?php endif; ?>
                </span>
              </span>
            </div>
          <?php else: ?>
            <a href="<?= htmlspecialchars($trackedUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="central-bio-card central-bio-card-link">
              <span class="central-bio-media <?= $image === '' ? 'central-bio-media-fallback' : '' ?>">
                <?php if ($image !== ''): ?>
                  <img src="<?= htmlspecialchars($image, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <?php else: ?>
                  <span><?= htmlspecialchars(mb_substr($title !== '' ? $title : 'L', 0, 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <?php endif; ?>
              </span>
              <span class="central-bio-copy">
                <?php if ($badge !== ''): ?><span class="central-bio-badge"><?= htmlspecialchars($badge, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span><?php endif; ?>
                <span class="central-bio-title"><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <?php if ($description !== ''): ?><span class="central-bio-description"><?= htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span><?php endif; ?>
                <?php if ($discount !== ''): ?><span class="central-link-discount"><?= htmlspecialchars($discount, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> OFF</span><?php endif; ?>
              </span>
              <span class="central-bio-arrow" aria-hidden="true">
                <svg viewBox="0 0 20 20" fill="none">
                  <path d="M6.5 10h7m0 0-2.8-2.8M13.5 10l-2.8 2.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </span>
            </a>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</details>