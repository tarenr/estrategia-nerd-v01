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
            <i class="fa-brands fa-instagram"></i>
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
              <i class="fa-brands fa-instagram text-3xl"></i>
            </div>
          <?php endif; ?>

          <!-- Indicador de Tipo de Mídia no canto superior -->
          <div class="absolute top-2.5 right-2.5 z-10 w-6 h-6 rounded-lg bg-slate-950/70 backdrop-blur-xs flex items-center justify-center text-white/80 text-[10px] shadow">
            <?php if ($tipo === 'carrossel'): ?>
              <i class="fa-solid fa-clone"></i>
            <?php elseif ($tipo === 'reels'): ?>
              <i class="fa-solid fa-play"></i>
            <?php else: ?>
              <i class="fa-brands fa-instagram"></i>
            <?php endif; ?>
          </div>

          <!-- Overlay Cyberpunk ao Hover -->
          <div class="absolute inset-0 bg-slate-950/85 backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-between p-3.5 z-20">
            <div class="flex items-center justify-between text-xs text-pink-400 font-mono">
              <span class="truncate">@<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></span>
              <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
            </div>

            <!-- Curtidas e Comentários -->
            <div class="flex items-center justify-center gap-4 text-white text-sm font-bold">
              <span class="inline-flex items-center gap-1.5 text-pink-300">
                <i class="fa-solid fa-heart text-xs text-pink-500"></i>
                <?= number_format($curtidas, 0, ',', '.') ?>
              </span>
              <span class="inline-flex items-center gap-1.5 text-cyan-300">
                <i class="fa-solid fa-comment text-xs text-cyan-400"></i>
                <?= number_format($comentarios, 0, ',', '.') ?>
              </span>
            </div>

            <!-- Preview da Legenda -->
            <p class="text-[11px] text-slate-300 line-clamp-2 leading-relaxed">
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
        <i class="fa-solid fa-chevron-right text-[10px]"></i>
      </a>
    </div>
  </div>
</section>
