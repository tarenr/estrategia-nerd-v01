<?php
declare(strict_types=1);

use App\Support\Csrf;
use App\Support\View;

$post = is_array($post ?? null) ? $post : [];
$comments = is_array($post_comments ?? null) ? $post_comments : [];
$commentsTotal = (int) ($post_comments_total ?? count($comments));
$related = is_array($post_related ?? null) ? $post_related : [];
$previous = is_array($post_previous ?? null) ? $post_previous : null;
$next = is_array($post_next ?? null) ? $post_next : null;
$commentState = is_array($comment_state ?? null) ? $comment_state : [];
$siteMeta = is_array($site_meta ?? null) ? $site_meta : [];

$siteName = (string) ($siteMeta['name'] ?? 'Estrategia Nerd');
$siteDescription = (string) ($siteMeta['description'] ?? '');
$siteKicker = (string) ($siteMeta['kicker'] ?? 'Portal geek estrategico');
$siteEmail = (string) ($siteMeta['email'] ?? '');
$footerText = (string) ($siteMeta['footer'] ?? $siteName);
$brandSymbol = (string) ($siteMeta['brand_symbol'] ?? '');
$brandSymbol = $brandSymbol !== '' ? $brandSymbol : url('/assets/brand/logo-symbol.png');
$brandWordPrimary = 'ESTRATEGIA';
$brandWordAccent = 'NERD';

$socialLinks = [
    'instagram' => [
        'url' => (string) ($siteMeta['instagram'] ?? ''),
        'label' => 'Instagram',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5" ry="5" fill="none" stroke="currentColor" stroke-width="1.9"/><circle cx="12" cy="12" r="4.2" fill="none" stroke="currentColor" stroke-width="1.9"/><circle cx="17.3" cy="6.7" r="1.1" fill="currentColor"/></svg>',
    ],
    'tiktok' => [
        'url' => (string) ($siteMeta['tiktok'] ?? ''),
        'label' => 'TikTok',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.5 4c.7 2 2.1 3.3 4.2 3.7v2.7a7.3 7.3 0 0 1-3.8-1.1v5.2a5.5 5.5 0 1 1-4.6-5.4v2.8a2.7 2.7 0 1 0 1.8 2.6V4h2.4Z" fill="currentColor"/></svg>',
    ],
    'kwai' => [
        'url' => (string) ($siteMeta['kwai'] ?? ''),
        'label' => 'Kwai',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8.2 5.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Zm7.6 0a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6ZM6.3 12.3h11.4a3.3 3.3 0 0 1 3.3 3.3v.3a3.9 3.9 0 0 1-3.9 3.9H6.9A3.9 3.9 0 0 1 3 15.9v-.3a3.3 3.3 0 0 1 3.3-3.3Zm2.2 2.2a1.7 1.7 0 1 0 0 3.4 1.7 1.7 0 0 0 0-3.4Zm7 0a1.7 1.7 0 1 0 0 3.4 1.7 1.7 0 0 0 0-3.4Z" fill="currentColor"/></svg>',
    ],
    'youtube' => [
        'url' => (string) ($siteMeta['youtube'] ?? ''),
        'label' => 'YouTube',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.6 8.2a3 3 0 0 0-2.1-2.1C17.7 5.6 12 5.6 12 5.6s-5.7 0-7.5.5A3 3 0 0 0 2.4 8.2 31.6 31.6 0 0 0 2 12a31.6 31.6 0 0 0 .4 3.8 3 3 0 0 0 2.1 2.1c1.8.5 7.5.5 7.5.5s5.7 0 7.5-.5a3 3 0 0 0 2.1-2.1A31.6 31.6 0 0 0 22 12a31.6 31.6 0 0 0-.4-3.8ZM10 15.4V8.6l5.2 3.4L10 15.4Z" fill="currentColor"/></svg>',
    ],
    'telegram' => [
        'url' => (string) ($siteMeta['telegram'] ?? ''),
        'label' => 'Telegram',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m20.7 4.3-2.5 14.1c-.2 1-.8 1.2-1.7.7l-4.5-3.3-2.2 2.1c-.2.3-.5.5-1 .5l.3-4.6 8.4-7.6c.4-.3-.1-.5-.6-.2L6.5 12.6l-4.4-1.4c-.9-.3-1-.9.2-1.4L19 3.4c.8-.3 1.5.2 1.3.9Z" fill="currentColor"/></svg>',
    ],
    'whatsapp' => [
        'url' => (string) ($siteMeta['whatsapp'] ?? ''),
        'label' => 'WhatsApp',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.2a8.7 8.7 0 0 0-7.5 13.1L3 21l4.9-1.3A8.8 8.8 0 1 0 12 3.2Zm0 15.8c-1.3 0-2.6-.4-3.6-1.1l-.3-.2-2.9.8.8-2.8-.2-.3A6.8 6.8 0 1 1 12 19Zm3.7-5.1c-.2-.1-1.3-.6-1.5-.7-.2-.1-.4-.1-.6.1l-.4.6c-.1.2-.3.2-.5.1a5.6 5.6 0 0 1-2.8-2.5c-.1-.2 0-.4.1-.5l.4-.5c.1-.2.2-.4 0-.6l-.7-1.6c-.1-.2-.3-.4-.6-.4h-.5c-.2 0-.5.1-.7.3-.7.6-1 1.4-.9 2.3.2 1.3.8 2.5 1.7 3.5a8 8 0 0 0 4.8 2.8c1 .1 2-.1 2.8-.7.4-.3.7-.9.8-1.4.1-.3 0-.5-.2-.6Z" fill="currentColor"/></svg>',
    ],
];

$hasSocialLinks = array_reduce(
    $socialLinks,
    static fn (bool $carry, array $social): bool => $carry || trim((string) ($social['url'] ?? '')) !== '',
    false
);
$contactHref = $siteEmail !== '' ? 'mailto:' . $siteEmail : url('/') . '#newsletter';
$commentMessage = trim((string) ($commentState['message'] ?? ''));
$commentStatus = trim((string) ($commentState['status'] ?? ''));
$commentOld = is_array($commentState['old'] ?? null) ? $commentState['old'] : [];
$rawTitle = (string) ($post['titulo'] ?? '');
$plainTitle = preg_replace('/\[\[(.*?)\]\]/u', '$1', $rawTitle) ?? $rawTitle;
$renderHighlightedTitle = static function (string $value): string {
    $parts = preg_split('/(\[\[.*?\]\])/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    if (!is_array($parts) || $parts === []) {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    $html = '';
    foreach ($parts as $part) {
        if (preg_match('/^\[\[(.*?)\]\]$/u', $part, $matches)) {
            $html .= '<span class="post-title-accent">' . htmlspecialchars((string) ($matches[1] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
            continue;
        }

        $html .= htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    return $html;
};
?>

<div class="site-home-page site-post-page">
  <div class="progress-bar" id="postProgressBar"></div>
  <div class="circuit-bg"></div>
  <div class="scanline"></div>
  <div id="siteToast" class="site-toast" aria-live="polite"></div>

  <?= View::component('site/shared/nav', [
      'site_name' => $siteName,
      'site_kicker' => $siteKicker,
      'brand_symbol' => $brandSymbol,
      'brand_word_primary' => $brandWordPrimary,
      'brand_word_accent' => $brandWordAccent,
      'active_page' => 'blog',
  ]) ?>

  <article class="relative pt-32 pb-16 overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-b from-cyan-500/10 via-transparent to-slate-950/60"></div>

    <header class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
      <div class="mb-5 inline-flex items-center gap-3 flex-wrap justify-center">
        <span class="post-category-pill"><?= htmlspecialchars((string) ($post['categoria_nome'] ?? 'Blog'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
        <span class="reading-badge">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21a9 9 0 100-18 9 9 0 000 18z"/></svg>
          <?= (int) ($post['tempo_leitura'] ?? 5) ?> min leitura
        </span>
      </div>

      <h1 class="font-orbitron text-4xl md:text-5xl lg:text-6xl font-black text-white leading-tight mb-6">
        <?= $renderHighlightedTitle($rawTitle) ?>
      </h1>

      <?php if (trim((string) ($post['resumo'] ?? '')) !== ''): ?>
        <p class="post-lead max-w-3xl mx-auto">
          <?= htmlspecialchars((string) ($post['resumo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </p>
      <?php endif; ?>

      <div class="mt-8 flex flex-wrap items-center justify-center gap-4 text-sm text-slate-400">
        <span><?= htmlspecialchars((string) ($post['data'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
        <span>&bull;</span>
        <span><?= number_format((int) ($post['views'] ?? 0), 0, ',', '.') ?> visualizacoes</span>
        <span>&bull;</span>
        <span><span data-like-count><?= number_format((int) ($post['curtidas'] ?? 0), 0, ',', '.') ?></span> curtidas</span>
        <span>&bull;</span>
        <span><span data-comments-total><?= $commentsTotal ?></span> comentarios</span>
      </div>

      <div class="flex justify-center gap-3 mt-8">
        <button type="button" class="share-btn" data-share="twitter" title="Compartilhar no X/Twitter" aria-label="Compartilhar no X/Twitter">
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.9 2H22l-6.8 7.8L23 22h-6.1l-4.8-6.5L6.5 22H3.3l7.2-8.2L1 2h6.3l4.3 6 5.3-6Z"/></svg>
        </button>
        <button type="button" class="share-btn" data-share="facebook" title="Compartilhar no Facebook" aria-label="Compartilhar no Facebook">
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12S0 5.446 0 12.073c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
        </button>
        <button type="button" class="share-btn" data-share="linkedin" title="Compartilhar no LinkedIn" aria-label="Compartilhar no LinkedIn">
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.065 2.065 0 110-4.13 2.065 2.065 0 010 4.13ZM7.119 20.452H3.555V9h3.564v11.452Z"/></svg>
        </button>
        <button type="button" class="share-btn" data-copy-link title="Copiar link" aria-label="Copiar link">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
        </button>
      </div>
    </header>
  </article>

  <section class="pb-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid lg:grid-cols-12 gap-10">
        <aside class="lg:col-span-3 hidden lg:block">
          <?php if (($post['toc'] ?? []) !== []): ?>
            <div class="toc">
              <h3>Indice</h3>
              <ul>
                <?php foreach (($post['toc'] ?? []) as $tocItem): ?>
                  <li>
                    <a href="#<?= htmlspecialchars((string) ($tocItem['id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="toc-link">
                      <?= htmlspecialchars((string) ($tocItem['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        </aside>

        <div class="lg:col-span-9">
          <div class="article-content max-w-3xl mx-auto">
            <?php if ((string) ($post['imagem'] ?? '') !== ''): ?>
              <div class="rounded-2xl overflow-hidden mb-8 article-featured-media">
                <img src="<?= htmlspecialchars((string) ($post['imagem'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($plainTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
              </div>
            <?php endif; ?>

            <?= (string) ($post['conteudo_html'] ?? '') ?>

            <?php if (($post['tags'] ?? []) !== []): ?>
              <div class="post-tag-list">
                <?php foreach (($post['tags'] ?? []) as $tag): ?>
                  <span class="post-tag-item"><?= htmlspecialchars((string) $tag, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div class="mt-8 flex justify-center">
              <form method="POST" action="<?= htmlspecialchars(url('/post/' . (string) ($post['slug'] ?? '') . '/curtir'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-like-form class="inline-flex">
                <?= Csrf::field() ?>
                <button type="submit" class="share-btn share-btn-like" data-like-button title="Curtir este artigo" aria-label="Curtir este artigo">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12.1 21.35l-1.1-1.02C5.14 14.88 2 12 2 8.5 2 5.91 3.99 4 6.5 4c1.74 0 3.41.81 4.5 2.09C12.09 4.81 13.76 4 15.5 4 18.01 4 20 5.91 20 8.5c0 3.5-3.14 6.38-8.9 11.83l-1 .92z"/></svg>
                  <span>Curtir este artigo</span>
                  <span class="text-cyan-300">(<span data-like-count><?= number_format((int) ($post['curtidas'] ?? 0), 0, ',', '.') ?></span>)</span>
                </button>
              </form>
            </div>

            <?php if (is_array($previous) || is_array($next)): ?>
              <div class="post-nav">
                <?php if (is_array($previous)): ?>
                  <a href="<?= htmlspecialchars((string) ($previous['url'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="post-nav-item">
                    <span class="text-gray-400 text-sm mb-2 block">← Post anterior</span>
                    <h4 class="font-orbitron font-bold text-white hover:text-cyan-400 transition-colors">
                      <?= htmlspecialchars((string) ($previous['titulo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </h4>
                  </a>
                <?php else: ?>
                  <div></div>
                <?php endif; ?>

                <?php if (is_array($next)): ?>
                  <a href="<?= htmlspecialchars((string) ($next['url'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="post-nav-item next">
                    <span class="text-gray-400 text-sm mb-2 block">Proximo post →</span>
                    <h4 class="font-orbitron font-bold text-white hover:text-cyan-400 transition-colors">
                      <?= htmlspecialchars((string) ($next['titulo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </h4>
                  </a>
                <?php else: ?>
                  <div></div>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="comentarios" class="py-16 bg-slate-900/30 border-t border-cyan-500/10">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
      <h2 class="font-orbitron text-3xl font-bold text-white mb-8 flex items-center gap-3">
        Comentarios
        <span class="text-cyan-400 text-xl">(<span data-comments-total><?= $commentsTotal ?></span>)</span>
      </h2>

      <?php if ($commentMessage !== ''): ?>
        <div class="comment-feedback <?= $commentStatus === 'success' ? 'is-success' : 'is-error' ?>">
          <?= htmlspecialchars($commentMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="<?= htmlspecialchars(url('/post/' . (string) ($post['slug'] ?? '') . '/comentarios'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="comment-form-card">
        <?= Csrf::field() ?>
        <div class="grid md:grid-cols-2 gap-4 mb-4">
          <input type="text" name="nome" placeholder="Seu nome" value="<?= htmlspecialchars((string) ($commentOld['nome'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="site-form-input">
          <input type="email" name="email" placeholder="seu@email.com" value="<?= htmlspecialchars((string) ($commentOld['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="site-form-input">
        </div>
        <textarea name="comentario" rows="5" placeholder="Escreva seu comentario..." class="site-form-textarea"><?= htmlspecialchars((string) ($commentOld['comentario'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
        <div class="mt-5 flex items-center justify-between gap-4 flex-wrap">
          <p class="text-sm text-slate-400">Seu comentario sera enviado para aprovacao antes de aparecer no post.</p>
          <button type="submit" class="site-nav-cta">Enviar comentario</button>
        </div>
      </form>

      <div class="space-y-6 mt-10" data-comments-list>
        <?php if ($comments !== []): ?>
            <?php foreach ($comments as $index => $comment): ?>
            <?php View::component('site/post/comment-item', [
                'comment' => $comment,
                'level' => 0,
                'post_slug' => (string) ($post['slug'] ?? ''),
                'is_hidden' => $index >= 3,
                'brand_symbol' => $brandSymbol,
            ]); ?>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="site-empty-panel">Ainda nao ha comentarios aprovados neste post. Seja o primeiro a participar.</div>
        <?php endif; ?>
      </div>
      <?php if ($commentsTotal > 3): ?>
        <div class="mt-8 flex justify-center">
          <button type="button" class="site-blog-all-posts" data-show-more-comments>Mostrar mais comentarios</button>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($related !== []): ?>
    <section class="py-16">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="font-orbitron text-3xl font-bold text-white mb-8 text-center">
          Posts <span class="text-cyan-400">Relacionados</span>
        </h2>

        <div class="grid md:grid-cols-3 gap-8">
          <?php foreach ($related as $index => $item): ?>
            <?php
              $tone = match ($index % 3) {
                  0 => 'from-purple-600 to-pink-800',
                  1 => 'from-green-600 to-teal-800',
                  default => 'from-cyan-600 to-blue-800',
              };
            ?>
            <a href="<?= htmlspecialchars((string) ($item['url'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="related-card bg-slate-800/50 rounded-2xl overflow-hidden group">
              <div class="h-48 bg-gradient-to-br <?= $tone ?> flex items-center justify-center relative overflow-hidden">
                <?php if ((string) ($item['imagem'] ?? '') !== ''): ?>
                  <img src="<?= htmlspecialchars((string) ($item['imagem'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($item['titulo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="absolute inset-0 h-full w-full object-cover opacity-80">
                  <div class="absolute inset-0 bg-slate-950/20"></div>
                <?php else: ?>
                  <svg class="w-16 h-16 text-white/30 relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                  </svg>
                <?php endif; ?>
              </div>
              <div class="p-6">
                <div class="text-cyan-300 text-xs font-semibold tracking-[0.16em] uppercase mb-3"><?= htmlspecialchars((string) ($item['categoria_nome'] ?? 'Blog'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <h3 class="font-orbitron text-xl font-bold text-white mb-3 group-hover:text-cyan-400 transition-colors">
                  <?= htmlspecialchars((string) ($item['titulo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </h3>
                <p class="text-gray-400 text-sm">
                  <?= htmlspecialchars((string) ($item['resumo'] ?? 'Conteudo relacionado do portal.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </p>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <?= View::component('site/shared/footer', [
      'site_name' => $siteName,
      'site_description' => $siteDescription,
      'site_kicker' => $siteKicker,
      'footer_text' => $footerText,
      'site_email' => $siteEmail,
      'brand_symbol' => $brandSymbol,
      'social_links' => $socialLinks,
      'has_social_links' => $hasSocialLinks,
      'contact_href' => $contactHref,
      'brand_word_primary' => $brandWordPrimary,
      'brand_word_accent' => $brandWordAccent,
  ]) ?>
</div>
