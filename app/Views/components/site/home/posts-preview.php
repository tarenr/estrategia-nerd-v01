<?php
use App\Support\View;

if (!site_section_visible_on_home('blog')) {
    return;
}

$latestPosts = $latest_posts ?? [];
$featuredPosts = $featured_posts ?? [];
$postsSection = $posts_section ?? [];
$contextLinks = (array) ($postsSection['context_links'] ?? []);
?>

<section id="blog" class="py-32 bg-slate-900/30 relative">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-12">
          <span class="text-cyan-400 font-medium tracking-widest uppercase text-sm"><?= htmlspecialchars((string) ($postsSection['eyebrow'] ?? 'Blog Estrategia Nerd'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
          <h2 class="font-orbitron text-4xl md:text-5xl font-bold mt-2 mb-4 text-white"><?= htmlspecialchars((string) ($postsSection['title'] ?? 'Guias, reviews e comparativos do blog'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
          <p class="text-gray-300 max-w-3xl mx-auto text-lg"><?= htmlspecialchars((string) ($postsSection['description'] ?? 'Os conteudos recentes do portal.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
          <?php if ($contextLinks !== []): ?>
            <p class="mt-6 text-sm md:text-base text-slate-300 max-w-3xl mx-auto">
              Explore tambem:
              <?php foreach ($contextLinks as $index => $link): ?>
                <?php if ($index > 0): ?><?= $index === count($contextLinks) - 1 ? ' e ' : ', ' ?><?php endif; ?>
                <a href="<?= htmlspecialchars((string) ($link['url'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="text-cyan-300 hover:text-cyan-200 underline underline-offset-4 decoration-cyan-500/40">
                  <?= htmlspecialchars((string) ($link['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </a>
              <?php endforeach; ?>.
            </p>
          <?php endif; ?>
      </div>

      <?= View::component('site/home/blog-highlight-carousel-preview', [
          'featured_posts' => $featuredPosts,
      ]) ?>

      <?php if ($latestPosts !== []): ?>
          <div class="mt-16 text-center mb-10">
            <span class="text-cyan-400 font-medium tracking-widest uppercase text-sm">Posts recentes</span>
          </div>
          <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 site-preview-post-grid">
              <?php foreach (array_slice($latestPosts, 0, 3) as $index => $post): ?>
                  <?php
                      $categoryName = strtoupper((string) ($post['categoria_nome'] ?? 'BLOG'));
                      $categoryColor = trim((string) ($post['categoria_cor'] ?? '#22d3ee'));
                      if (preg_match('/^#[0-9a-fA-F]{6}$/', $categoryColor) !== 1) {
                          $categoryColor = '#22d3ee';
                      }
                      $icon = '<svg class="w-16 h-16 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>';
                  ?>
                  <article class="nerd-card site-reveal bg-slate-800/50 rounded-2xl overflow-hidden group cursor-pointer site-preview-post-card" style="--post-accent: <?= htmlspecialchars($categoryColor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-reveal>
                      <a href="<?= htmlspecialchars((string) ($post['url'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="site-preview-post-card-anchor">
                          <div class="relative h-48 overflow-hidden">
                              <div class="absolute inset-0 site-preview-post-card-fallback"></div>
                              <?php if ((string) ($post['imagem'] ?? '') !== ''): ?>
                                  <img src="<?= htmlspecialchars((string) $post['imagem'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars(public_title((string) ($post['titulo'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="absolute inset-0 h-full w-full object-cover opacity-80" loading="lazy" decoding="async">
                                  <div class="absolute inset-0 bg-slate-950/20"></div>
                              <?php else: ?>
                                  <div class="absolute inset-0 flex items-center justify-center">
                                      <?= $icon ?>
                                  </div>
                              <?php endif; ?>
                              <div class="absolute top-4 left-4 px-3 py-1 site-preview-post-card-pill text-xs font-bold rounded-full"><?= htmlspecialchars($categoryName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                          </div>
                          <div class="p-6 site-preview-post-card-body">
                              <div class="flex items-center text-sm text-gray-400 mb-3">
                                  <span><?= htmlspecialchars((string) ($post['data'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                                  <span class="mx-2">&bull;</span>
                                  <span><?= (int) ($post['tempo_leitura'] ?? 5) ?> min leitura</span>
                              </div>
                              <h3 class="font-orbitron text-xl font-bold text-white mb-3 transition-colors site-preview-post-card-title">
                                  <?= htmlspecialchars(public_title((string) ($post['titulo'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                              </h3>
                              <p class="text-gray-300 text-sm mb-4 site-preview-post-card-copy">
                                  <?= htmlspecialchars((string) ($post['resumo'] ?? 'Materia publicada no portal.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                              </p>
                              <div class="site-preview-post-card-footer">
                                  <span class="inline-flex items-center font-semibold transition-colors site-preview-post-card-link">
                                      Ler a materia
                                      <svg class="w-4 h-4 ml-2 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                                      </svg>
                                  </span>
                                  <?= View::component('site/shared/post-metrics', [
                                      'views' => (int) ($post['views'] ?? 0),
                                      'likes' => (int) ($post['curtidas'] ?? 0),
                                      'comments' => (int) ($post['comentarios_count'] ?? 0),
                                  ]) ?>
                              </div>
                          </div>
                      </a>
                  </article>
              <?php endforeach; ?>
          </div>

          <div class="text-center mt-12">
              <a href="<?= htmlspecialchars(site_section_href('blog'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-block px-8 py-4 border-2 border-cyan-500/50 text-cyan-400 font-bold text-lg rounded-full hover:bg-cyan-500/10 transition-all hover:border-cyan-400">
                  Ver todos os posts do blog
              </a>
          </div>
      <?php else: ?>
          <div class="site-empty-panel">Ainda nao ha posts publicados suficientes para compor a home publica.</div>
      <?php endif; ?>
  </div>
</section>
