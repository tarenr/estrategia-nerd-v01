<?php
$posts = array_values(array_filter(
    (array) ($featured_posts ?? []),
    static fn (array $post): bool => (int) ($post['destaque'] ?? 0) === 1
));
$posts = array_slice($posts, 0, 5);
if ($posts === []) {
    return;
}
?>

<div class="site-preview-blog-highlight" aria-label="Destaques do blog">
    <div class="site-preview-carousel-controls" aria-label="Controles do carrossel">
      <button type="button" class="site-preview-carousel-btn" data-preview-carousel-prev aria-label="Post anterior">‹</button>
      <button type="button" class="site-preview-carousel-btn" data-preview-carousel-next aria-label="Proximo post">›</button>
    </div>

    <div class="site-preview-carousel site-preview-about-piece" data-preview-carousel data-reveal>
      <div class="site-preview-carousel-track" data-preview-carousel-track>
        <?php foreach ($posts as $index => $post): ?>
          <?php
            $categoryName = strtoupper((string) ($post['categoria_nome'] ?? 'BLOG'));
            $image = (string) ($post['imagem'] ?? '');
            $title = public_title((string) ($post['titulo'] ?? ''));
            $summary = trim((string) ($post['resumo'] ?? 'Materia publicada no portal.'));
          ?>
          <article class="site-preview-carousel-slide<?= $index === 0 ? ' is-active' : '' ?>" data-preview-carousel-slide>
            <a href="<?= htmlspecialchars((string) ($post['url'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="site-preview-feature-card">
              <div class="site-preview-feature-media">
                <?php if ($image !== ''): ?>
                  <img src="<?= htmlspecialchars($image, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" loading="lazy" decoding="async">
                <?php else: ?>
                  <div class="site-preview-feature-fallback" aria-hidden="true">EN</div>
                <?php endif; ?>
                <span class="site-preview-feature-badge">Destaque</span>
              </div>
              <div class="site-preview-feature-body">
                <div class="site-preview-feature-meta">
                  <span><?= htmlspecialchars($categoryName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                  <span><?= htmlspecialchars((string) ($post['data'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                  <span><?= (int) ($post['tempo_leitura'] ?? 5) ?> min leitura</span>
                </div>
                <h3><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
                <p><?= htmlspecialchars($summary !== '' ? $summary : 'Materia publicada no portal.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                <span class="site-preview-feature-link">Ler artigo completo</span>
              </div>
            </a>
          </article>
        <?php endforeach; ?>
      </div>

      <div class="site-preview-carousel-dots" data-preview-carousel-dots aria-label="Indicadores do carrossel">
        <?php foreach ($posts as $index => $_post): ?>
          <button type="button" class="<?= $index === 0 ? 'is-active' : '' ?>" data-preview-carousel-dot="<?= (int) $index ?>" aria-label="Ir para destaque <?= (int) ($index + 1) ?>"></button>
        <?php endforeach; ?>
      </div>
    </div>
</div>
