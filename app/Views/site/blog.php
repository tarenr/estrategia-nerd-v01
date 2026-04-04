<?php
declare(strict_types=1);

use App\Support\View;

$posts = $blog_posts ?? [];
$featured = $blog_featured ?? null;
$categories = $blog_categories ?? [];
$filters = $blog_filters ?? [];
$pagination = $blog_pagination ?? ['page' => 1, 'pages' => 1, 'total' => 0];
$siteMeta = $site_meta ?? [];

$siteName = (string) ($siteMeta['name'] ?? 'Estrategia Nerd');
$siteDescription = (string) ($siteMeta['description'] ?? '');
$siteKicker = (string) ($siteMeta['kicker'] ?? 'Portal geek estrategico');
$siteEmail = (string) ($siteMeta['email'] ?? '');
$footerText = (string) ($siteMeta['footer'] ?? $siteName);
$brandSymbol = (string) ($siteMeta['brand_symbol'] ?? '');
$brandSymbol = $brandSymbol !== '' ? $brandSymbol : url('/assets/brand/logo-symbol.png');
$brandWordPrimary = 'ESTRATEGIA';
$brandWordAccent = 'NERD';
$q = trim((string) ($filters['q'] ?? ''));
$activeCategory = trim((string) ($filters['categoria'] ?? ''));
$currentPage = (int) ($pagination['page'] ?? 1);
$totalPages = (int) ($pagination['pages'] ?? 1);

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

$buildBlogUrl = static function (array $extra = []) use ($q, $activeCategory): string {
    $params = [];
    if ($q !== '') {
        $params['q'] = $q;
    }
    if ($activeCategory !== '' && $activeCategory !== 'all') {
        $params['categoria'] = $activeCategory;
    }
    foreach ($extra as $key => $value) {
        if ($value === null || $value === '' || $value === 'all') {
            unset($params[$key]);
            continue;
        }
        $params[$key] = $value;
    }
    $query = http_build_query($params);
    return url('/blog' . ($query !== '' ? '?' . $query : ''));
};
?>

<div class="site-home-page">
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

  <section class="relative pt-32 pb-20 overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-b from-cyan-500/10 via-transparent to-slate-900/50"></div>

    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
      <div class="mb-6 inline-block">
        <span class="px-4 py-2 rounded-full border border-cyan-500/30 text-cyan-400 text-sm font-medium tracking-widest uppercase bg-cyan-500/10">
          Informacoes Nerds
        </span>
      </div>

      <h1 class="font-orbitron text-5xl md:text-6xl lg:text-7xl font-black mb-6 leading-tight">
        <span class="block text-white glitch" data-text="BLOG">BLOG</span>
        <span class="block text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 via-blue-500 to-purple-600 neon-text text-3xl md:text-4xl lg:text-5xl mt-2">
          Estrategia Nerd
        </span>
      </h1>

      <p class="text-xl text-gray-400 max-w-3xl mx-auto mb-8">
        Novidades, reviews, curiosidades e analises do universo tech e geek. Conteudo de qualidade para quem vive um nivel a frente.
      </p>

      <form method="GET" action="<?= htmlspecialchars(url('/blog'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="max-w-2xl mx-auto relative">
        <?php if ($activeCategory !== '' && $activeCategory !== 'all'): ?>
          <input type="hidden" name="categoria" value="<?= htmlspecialchars($activeCategory, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <?php endif; ?>
        <input
          type="text"
          id="search-input"
          name="q"
          value="<?= htmlspecialchars($q, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
          placeholder="Buscar posts..."
          class="w-full px-6 py-4 pl-14 search-input rounded-full text-white placeholder-gray-500"
        >
        <svg class="w-6 h-6 text-cyan-400 absolute left-5 top-1/2 transform -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
      </form>
    </div>
  </section>

  <section class="py-8 border-y border-cyan-500/10 bg-slate-900/30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex flex-wrap justify-center gap-3 blog-filter-list" id="category-filters">
        <a href="<?= htmlspecialchars($buildBlogUrl(['categoria' => null, 'page' => null]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="filter-btn <?= $activeCategory === '' || $activeCategory === 'all' ? 'active bg-cyan-500/20 text-white' : 'text-gray-300' ?> px-6 py-2 rounded-full text-sm font-medium">Todos</a>
        <?php foreach ($categories as $category): ?>
          <?php $isActive = $activeCategory === (string) ($category['slug'] ?? ''); ?>
          <a href="<?= htmlspecialchars($buildBlogUrl(['categoria' => (string) ($category['slug'] ?? ''), 'page' => null]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="filter-btn <?= $isActive ? 'active bg-cyan-500/20 text-white' : 'text-gray-300' ?> px-6 py-2 rounded-full text-sm font-medium">
            <?= htmlspecialchars((string) ($category['nome'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <div id="blogDynamicContent">
  <?php if (is_array($featured)): ?>
    <section class="py-16">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <article class="featured-post site-reveal rounded-3xl overflow-hidden bg-slate-800/50 border border-cyan-500/20" data-reveal>
          <div class="grid lg:grid-cols-2">
            <div class="h-64 lg:h-auto bg-gradient-to-br from-cyan-600 via-blue-700 to-purple-800 relative">
              <?php if ((string) ($featured['imagem'] ?? '') !== ''): ?>
                <img src="<?= htmlspecialchars((string) $featured['imagem'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($featured['titulo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="absolute inset-0 h-full w-full object-cover opacity-80">
              <?php else: ?>
                <div class="absolute inset-0 flex items-center justify-center">
                  <svg class="w-32 h-32 text-white/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                  </svg>
                </div>
              <?php endif; ?>
              <div class="absolute top-4 left-4">
                <span class="px-3 py-1 bg-cyan-500 text-slate-900 text-xs font-bold rounded-full">Destaque</span>
              </div>
            </div>
            <div class="p-8 lg:p-12 featured-content flex flex-col justify-center">
              <div class="flex items-center gap-4 text-sm text-gray-400 mb-4 flex-wrap">
                <span class="text-cyan-400 font-semibold"><?= htmlspecialchars(strtoupper((string) ($featured['categoria_nome'] ?? 'BLOG')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <span>&bull;</span>
                <span><?= htmlspecialchars((string) ($featured['data'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <span>&bull;</span>
                <span><?= (int) ($featured['tempo_leitura'] ?? 5) ?> min leitura</span>
              </div>
              <h2 class="font-orbitron text-3xl lg:text-4xl font-bold text-white mb-4 hover:text-cyan-400 transition-colors">
                <a href="<?= htmlspecialchars((string) ($featured['url'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars((string) ($featured['titulo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
              </h2>
              <p class="text-gray-400 text-lg mb-6 leading-relaxed">
                <?= htmlspecialchars((string) ($featured['resumo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
              </p>
              <a href="<?= htmlspecialchars((string) ($featured['url'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-flex items-center text-cyan-400 font-bold text-lg hover:text-cyan-300 transition-colors group">
                Ler artigo completo
                <svg class="w-5 h-5 ml-2 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                </svg>
              </a>
            </div>
          </div>
        </article>
      </div>
    </section>
  <?php endif; ?>

  <section class="py-16 bg-slate-900/30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <?php if ($posts !== []): ?>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8" id="posts-grid">
          <?php foreach ($posts as $index => $post): ?>
            <?php
              $tone = match ($index % 3) {
                  0 => [
                      'hero' => 'from-cyan-600 to-blue-800',
                      'pill' => 'bg-cyan-500 text-slate-900',
                      'title' => 'group-hover:text-cyan-400',
                      'link' => 'text-cyan-400 hover:text-cyan-300',
                      'icon' => '<svg class="w-16 h-16 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>',
                  ],
                  1 => [
                      'hero' => 'from-purple-600 to-pink-800',
                      'pill' => 'bg-purple-500 text-white',
                      'title' => 'group-hover:text-purple-400',
                      'link' => 'text-purple-400 hover:text-purple-300',
                      'icon' => '<svg class="w-16 h-16 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
                  ],
                  default => [
                      'hero' => 'from-green-600 to-teal-800',
                      'pill' => 'bg-green-500 text-slate-900',
                      'title' => 'group-hover:text-green-400',
                      'link' => 'text-green-400 hover:text-green-300',
                      'icon' => '<svg class="w-16 h-16 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>',
                  ],
              };
            ?>
            <article class="nerd-card site-reveal bg-slate-800/50 rounded-2xl overflow-hidden group cursor-pointer post-item" data-category="<?= htmlspecialchars((string) ($post['categoria_slug'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-reveal>
              <a href="<?= htmlspecialchars((string) ($post['url'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="block">
                <div class="relative h-48 overflow-hidden">
                  <div class="absolute inset-0 bg-gradient-to-br <?= $tone['hero'] ?>"></div>
                  <?php if ((string) ($post['imagem'] ?? '') !== ''): ?>
                    <img src="<?= htmlspecialchars((string) $post['imagem'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($post['titulo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="absolute inset-0 h-full w-full object-cover opacity-80">
                    <div class="absolute inset-0 bg-slate-950/20"></div>
                  <?php else: ?>
                    <div class="absolute inset-0 flex items-center justify-center">
                      <?= $tone['icon'] ?>
                    </div>
                  <?php endif; ?>
                  <div class="absolute top-4 left-4 px-3 py-1 <?= $tone['pill'] ?> text-xs font-bold rounded-full">
                    <?= htmlspecialchars((string) ($post['categoria_nome'] ?? 'Blog'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                  </div>
                </div>
                <div class="p-6">
                  <div class="flex items-center text-sm text-gray-400 mb-3">
                    <span><?= htmlspecialchars((string) ($post['data'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    <span class="mx-2">&bull;</span>
                    <span><?= (int) ($post['tempo_leitura'] ?? 5) ?> min leitura</span>
                  </div>
                  <h3 class="font-orbitron text-xl font-bold text-white mb-3 transition-colors line-clamp-2 <?= $tone['title'] ?>">
                    <?= htmlspecialchars((string) ($post['titulo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                  </h3>
                  <p class="text-gray-400 text-sm mb-4">
                    <?= htmlspecialchars((string) ($post['resumo'] ?? 'Materia publicada no portal.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                  </p>
                  <div class="flex items-center justify-between">
                    <span class="inline-flex items-center font-semibold transition-colors <?= $tone['link'] ?>">
                      Ler mais
                      <svg class="w-4 h-4 ml-2 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                      </svg>
                    </span>
                    <div class="flex items-center text-gray-500 text-sm">
                      <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                      </svg>
                      <?= number_format((int) ($post['views'] ?? 0), 0, ',', '.') ?>
                    </div>
                  </div>
                </div>
              </a>
            </article>
          <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
          <div class="mt-16 flex justify-center items-center gap-2 flex-wrap">
            <?php $prevPage = max(1, $currentPage - 1); ?>
            <a href="<?= htmlspecialchars($buildBlogUrl(['page' => $prevPage]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="page-btn px-4 py-2 rounded-lg text-gray-400 hover:text-white <?= $currentPage <= 1 ? 'pointer-events-none opacity-40' : '' ?>">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
              </svg>
            </a>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <?php if ($i === 1 || $i === $totalPages || abs($i - $currentPage) <= 1): ?>
                <a href="<?= htmlspecialchars($buildBlogUrl(['page' => $i]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="page-btn px-4 py-2 rounded-lg text-sm font-bold <?= $i === $currentPage ? 'active' : 'text-gray-400' ?>"><?= $i ?></a>
              <?php elseif ($i === 2 && $currentPage > 4): ?>
                <span class="text-gray-500 px-2">...</span>
              <?php elseif ($i === $totalPages - 1 && $currentPage < $totalPages - 3): ?>
                <span class="text-gray-500 px-2">...</span>
              <?php endif; ?>
            <?php endfor; ?>
            <?php $nextPage = min($totalPages, $currentPage + 1); ?>
            <a href="<?= htmlspecialchars($buildBlogUrl(['page' => $nextPage]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="page-btn px-4 py-2 rounded-lg text-gray-400 hover:text-white <?= $currentPage >= $totalPages ? 'pointer-events-none opacity-40' : '' ?>">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </a>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="site-empty-panel">Nenhum post encontrado com os filtros atuais.</div>
      <?php endif; ?>
    </div>
  </section>
  </div>

  <?= View::component('site/home/newsletter') ?>

  <?= View::component('site/shared/footer', [
      'site_name' => $siteName,
      'site_description' => $siteDescription,
      'site_kicker' => $siteKicker,
      'footer_text' => $footerText,
      'site_email' => $siteEmail,
      'brand_symbol' => $brandSymbol,
      'social_links' => $socialLinks,
      'brand_word_primary' => $brandWordPrimary,
      'brand_word_accent' => $brandWordAccent,
      'has_social_links' => $hasSocialLinks,
      'contact_href' => $contactHref,
  ]) ?>
</div>
