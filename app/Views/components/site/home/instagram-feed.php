<?php
declare(strict_types=1);

$feed = is_array($instagram_feed ?? null) ? $instagram_feed : null;
if ($feed === null || empty($feed['posts'])) {
    return;
}

$username = (string) ($feed['username'] ?? 'estrategia_nerd');
$profilePic = (string) ($feed['profile_picture'] ?? '');
$profileUrl = (string) ($feed['profile_url'] ?? 'https://www.instagram.com/' . $username);
$followers = (int) ($feed['followers_count'] ?? 0);
$posts = (array) ($feed['posts'] ?? []);
?>

<section id="instagram-feed" class="py-20 relative overflow-hidden">
  <div class="absolute inset-0 bg-gradient-to-b from-transparent via-cyan-950/10 to-transparent pointer-events-none"></div>

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
    <!-- Header do Feed -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10 pb-6 border-b border-slate-800/80">
      <div>
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-cyan-500/30 bg-cyan-500/10 text-cyan-400 text-xs font-mono font-bold tracking-widest uppercase mb-3">
          <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-ping"></span>
          Conecte-se // Redes Sociais
        </div>
        <h2 class="font-orbitron text-2xl sm:text-3xl md:text-4xl font-bold text-white tracking-tight">
          Direto do <span class="bg-gradient-to-r from-pink-500 via-purple-400 to-cyan-400 bg-clip-text text-transparent">Instagram</span>
        </h2>
        <p class="text-slate-400 mt-2 text-sm sm:text-base max-w-xl">
          Acompanhe nossos bastidores, setups, notícias rápidas e destaques do universo tech e geek.
        </p>
      </div>

      <!-- Badge de Perfil e Botão Seguir -->
      <div class="flex items-center gap-4 shrink-0 bg-slate-900/60 border border-slate-800 p-2.5 sm:p-3 rounded-2xl backdrop-blur-sm">
        <?php if ($profilePic !== ''): ?>
          <img src="<?= htmlspecialchars($profilePic, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
               alt="@<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
               class="w-12 h-12 rounded-full border-2 border-pink-500/50 object-cover shadow-[0_0_15px_rgba(236,72,153,0.3)]"
               loading="lazy">
        <?php else: ?>
          <div class="w-12 h-12 rounded-full bg-gradient-to-tr from-yellow-500 via-pink-500 to-purple-600 flex items-center justify-center text-white text-lg font-bold shadow-md">
            <svg class="w-6 h-6 text-white fill-current" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
          </div>
        <?php endif; ?>

        <div class="min-w-0 pr-2">
          <div class="font-bold text-white text-sm tracking-wide truncate">@<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></div>
          <?php if ($followers > 0): ?>
            <div class="text-xs text-slate-400 font-mono"><?= number_format($followers, 0, ',', '.') ?> seguidores</div>
          <?php endif; ?>
        </div>

        <a href="<?= htmlspecialchars($profileUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
           target="_blank"
           rel="noopener noreferrer"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-pink-500 to-purple-600 text-white text-xs font-bold rounded-xl hover:shadow-lg hover:shadow-pink-500/25 transition-all transform hover:scale-105">
          <span>Seguir</span>
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
          </svg>
        </a>
      </div>
    </div>

    <!-- Grid de Posts -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5 sm:gap-4">
      <?php foreach ($posts as $item): ?>
        <?php
          $mediaUrl = (string) ($item['media_url'] ?? '');
          $permalink = (string) ($item['permalink'] ?? $profileUrl);
          $tipo = (string) ($item['tipo'] ?? 'imagem');
          $curtidas = (int) ($item['curtidas'] ?? 0);
          $comentarios = (int) ($item['comentarios_count'] ?? 0);
          $legenda = (string) ($item['legenda'] ?? '');
        ?>
        <a href="<?= htmlspecialchars($permalink, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
           target="_blank"
           rel="noopener noreferrer"
           class="group relative aspect-square rounded-2xl overflow-hidden border border-slate-800 bg-slate-900/80 shadow-md transition-all duration-300 hover:border-pink-500/50 hover:shadow-[0_0_20px_rgba(236,72,153,0.25)] hover:-translate-y-1 block">
          
          <?php if ($mediaUrl !== ''): ?>
            <img src="<?= htmlspecialchars($mediaUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                 alt="Instagram post"
                 class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
                 loading="lazy"
                 onerror="this.style.opacity='0.3';">
          <?php else: ?>
            <div class="w-full h-full flex items-center justify-center bg-slate-900 text-slate-700">
              <svg class="w-10 h-10 text-slate-700 fill-current" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
            </div>
          <?php endif; ?>

          <!-- Indicador de Tipo de Mídia no canto superior -->
          <div class="absolute top-2.5 right-2.5 z-10 w-7 h-7 rounded-lg bg-slate-950/80 backdrop-blur-xs flex items-center justify-center text-white/90 shadow border border-white/10">
            <?php if ($tipo === 'carrossel'): ?>
              <!-- Ícone Carrossel SVG -->
              <svg class="w-3.5 h-3.5 text-white/90 fill-current" viewBox="0 0 24 24"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H8V4h12v12z"/></svg>
            <?php elseif ($tipo === 'reels'): ?>
              <!-- Ícone Play/Reels SVG -->
              <svg class="w-3.5 h-3.5 text-white/90 fill-current" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            <?php else: ?>
              <!-- Ícone Instagram Câmera SVG -->
              <svg class="w-3.5 h-3.5 text-white/90 fill-current" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
            <?php endif; ?>
          </div>

          <!-- Overlay Cyberpunk ao Hover / Touch Mobile -->
          <div class="absolute inset-0 bg-slate-950/90 backdrop-blur-xs opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-between p-3.5 z-20">
            <div class="flex items-center justify-between text-xs text-pink-400 font-mono">
              <span class="truncate">@<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></span>
              <svg class="w-3.5 h-3.5 text-pink-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            </div>

            <!-- Curtidas e Comentários com Badges Claras e Legíveis -->
            <div class="flex flex-col gap-1.5 my-auto">
              <div class="inline-flex items-center justify-center gap-1.5 px-2.5 py-1 rounded-lg bg-pink-500/20 border border-pink-500/30 text-pink-200 text-xs font-bold">
                <svg class="w-3.5 h-3.5 text-pink-400 fill-current shrink-0" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                <span><?= number_format($curtidas, 0, ',', '.') ?> <?= $curtidas === 1 ? 'curtida' : 'curtidas' ?></span>
              </div>
              <div class="inline-flex items-center justify-center gap-1.5 px-2.5 py-1 rounded-lg bg-cyan-500/20 border border-cyan-500/30 text-cyan-200 text-xs font-bold">
                <svg class="w-3.5 h-3.5 text-cyan-400 fill-current shrink-0" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 9h12v2H6V9zm8 5H6v-2h8v2zm4-6H6V6h12v2z"/></svg>
                <span><?= number_format($comentarios, 0, ',', '.') ?> <?= $comentarios === 1 ? 'comentário' : 'comentários' ?></span>
              </div>
            </div>

            <!-- Preview da Legenda -->
            <p class="text-[11px] text-slate-300 line-clamp-2 leading-relaxed text-center">
              <?= htmlspecialchars($legenda !== '' ? $legenda : 'Ver no Instagram', ENT_QUOTES, 'UTF-8') ?>
            </p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Botão Inferior Centralizado -->
    <div class="mt-8 text-center">
      <a href="<?= htmlspecialchars($profileUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
         target="_blank"
         rel="noopener noreferrer"
         class="inline-flex items-center gap-2 text-xs sm:text-sm font-semibold text-slate-400 hover:text-cyan-300 transition-colors">
        <span>Acompanhe mais publicações em @<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></span>
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
      </a>
    </div>
  </div>
</section>
