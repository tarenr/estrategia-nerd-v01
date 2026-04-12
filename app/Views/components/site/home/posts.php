<?php
if (!site_section_visible_on_home('blog')) {
    return;
}

$latestPosts = $latest_posts ?? [];
?>

<section id="blog" class="py-32 bg-slate-900/30 relative">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-16">
          <span class="text-cyan-400 font-medium tracking-widest uppercase text-sm">Informações nerds</span>
          <h2 class="font-orbitron text-4xl md:text-5xl font-bold mt-2 mb-4 text-white">Últimas do <span class="text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 to-purple-500">blog</span></h2>
          <p class="text-gray-400 max-w-2xl mx-auto">Novidades, reviews, curiosidades e análises do universo tech e geek.</p>
      </div>

      <?php if ($latestPosts !== []): ?>
          <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
              <?php foreach (array_slice($latestPosts, 0, 3) as $index => $post): ?>
                  <?php
                      $categoryName = strtoupper((string) ($post['categoria_nome'] ?? 'BLOG'));
                      $cardTone = match ($index % 3) {
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
                  <article class="nerd-card site-reveal bg-slate-800/50 rounded-2xl overflow-hidden group cursor-pointer" data-reveal>
                      <a href="<?= htmlspecialchars((string) ($post['url'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="block">
                          <div class="relative h-48 overflow-hidden">
                              <div class="absolute inset-0 bg-gradient-to-br <?= $cardTone['hero'] ?>"></div>
                              <?php if ((string) ($post['imagem'] ?? '') !== ''): ?>
                                  <img src="<?= htmlspecialchars((string) $post['imagem'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars(public_title((string) ($post['titulo'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="absolute inset-0 h-full w-full object-cover opacity-80">
                                  <div class="absolute inset-0 bg-slate-950/20"></div>
                              <?php else: ?>
                                  <div class="absolute inset-0 flex items-center justify-center">
                                      <?= $cardTone['icon'] ?>
                                  </div>
                              <?php endif; ?>
                              <div class="absolute top-4 left-4 px-3 py-1 <?= $cardTone['pill'] ?> text-xs font-bold rounded-full"><?= htmlspecialchars($categoryName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                          </div>
                          <div class="p-6">
                              <div class="flex items-center text-sm text-gray-400 mb-3">
                                  <span><?= htmlspecialchars((string) ($post['data'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                                  <span class="mx-2">&bull;</span>
                                  <span><?= (int) ($post['tempo_leitura'] ?? 5) ?> min leitura</span>
                              </div>
                              <h3 class="font-orbitron text-xl font-bold text-white mb-3 transition-colors <?= $cardTone['title'] ?>">
                                  <?= htmlspecialchars(public_title((string) ($post['titulo'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                              </h3>
                              <p class="text-gray-400 text-sm mb-4">
                                  <?= htmlspecialchars((string) ($post['resumo'] ?? 'Materia publicada no portal.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                              </p>
                              <span class="inline-flex items-center font-semibold transition-colors <?= $cardTone['link'] ?>">
                                  Ler mais
                                  <svg class="w-4 h-4 ml-2 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                                  </svg>
                              </span>
                          </div>
                      </a>
                  </article>
              <?php endforeach; ?>
          </div>

          <div class="text-center mt-12">
              <a href="<?= htmlspecialchars(site_section_href('blog'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-block px-8 py-4 border-2 border-cyan-500/50 text-cyan-400 font-bold text-lg rounded-full hover:bg-cyan-500/10 transition-all hover:border-cyan-400">
                  Ver todos os posts
              </a>
          </div>
      <?php else: ?>
          <div class="site-empty-panel">Ainda não há posts publicados suficientes para compor a home pública.</div>
      <?php endif; ?>
  </div>
</section>