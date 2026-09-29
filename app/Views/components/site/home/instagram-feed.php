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
$allPosts = (array) ($feed['posts'] ?? []);

// Exatamente 3 páginas de 6 posts (18 posts no máximo)
$pages = array_chunk(array_slice($allPosts, 0, 18), 6);
$totalPages = count($pages);
if ($totalPages === 0) {
    return;
}

// Formatador de data em português
$formatDatePt = static function (?string $dateStr): string {
    if (empty($dateStr)) {
        return '';
    }
    $time = strtotime($dateStr);
    if (!$time) {
        return '';
    }
    $months = [
        1 => 'jan.', 2 => 'fev.', 3 => 'mar.', 4 => 'abr.', 5 => 'mai.', 6 => 'jun.',
        7 => 'jul.', 8 => 'ago.', 9 => 'set.', 10 => 'out.', 11 => 'nov.', 12 => 'dez.'
    ];
    $day = (int) date('j', $time);
    $month = $months[(int) date('n', $time)] ?? date('M', $time);
    $year = date('Y', $time);
    return "{$day} de {$month} de {$year}";
};

// Extrator de Título e Subtítulo da legenda
$extractHeadingAndSnippet = static function (string $raw): array {
    $clean = trim($raw);
    if ($clean === '') {
        return ['Publicação no Instagram', 'Confira os detalhes e novidades no @estrategia_nerd'];
    }
    $lines = array_values(array_filter(
        array_map('trim', preg_split('/\r\n|\r|\n/', $clean) ?: []),
        static fn ($l) => $l !== ''
    ));
    if (empty($lines)) {
        return ['Publicação no Instagram', 'Confira os detalhes e novidades no @estrategia_nerd'];
    }
    $title = $lines[0];
    $subtitle = $lines[1] ?? 'Confira os detalhes e comente no Instagram oficial.';
    if (str_starts_with($subtitle, '#')) {
        $subtitle = 'Confira os bastidores e análises no Instagram.';
    }
    return [$title, $subtitle];
};
?>

<section id="instagram-feed" class="py-16 sm:py-24 relative overflow-hidden bg-slate-950/60 scroll-mt-24">
  <!-- Efeitos de Grid e Glow de Fundo Cyberpunk -->
  <div class="absolute inset-0 bg-[linear-gradient(to_right,#0f172a_1px,transparent_1px),linear-gradient(to_bottom,#0f172a_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_50%,#000_70%,transparent_100%)] opacity-30 pointer-events-none"></div>
  <div class="absolute -top-32 left-1/4 w-96 h-96 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"></div>
  <div class="absolute -bottom-32 right-1/4 w-96 h-96 bg-pink-500/10 rounded-full blur-3xl pointer-events-none"></div>

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
    <!-- Header em 2 Colunas (Design do Mockup) -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-8 mb-12">
      <!-- Coluna Esquerda: Chamada Editorial -->
      <div class="max-w-2xl">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-cyan-500/30 bg-cyan-950/40 text-cyan-400 text-xs font-mono font-bold tracking-widest uppercase mb-4 shadow-[0_0_10px_rgba(6,182,212,0.15)]">
          <span class="w-1.5 h-1.5 rounded-full bg-cyan-400 animate-ping"></span>
          Conecte-se // Redes Sociais
        </div>
        <h2 class="font-orbitron text-2xl sm:text-3xl lg:text-4xl font-extrabold text-white tracking-tight leading-tight">
          O universo nerd continua <br class="hidden sm:inline">
          no <span class="bg-gradient-to-r from-pink-500 via-purple-400 to-cyan-400 bg-clip-text text-transparent drop-shadow-[0_0_20px_rgba(236,72,153,0.3)]">Instagram</span>
        </h2>
        <p class="text-slate-400 mt-3 text-sm sm:text-base leading-relaxed">
          Acompanhe nossos bastidores, setups, notícias rápidas e destaques do universo tech e geek.
        </p>
      </div>

      <!-- Coluna Direita: Card de Perfil Rico e Expandido -->
      <div class="w-full lg:max-w-md bg-slate-900/80 border border-slate-800/90 rounded-2xl p-5 backdrop-blur-md shadow-2xl relative group hover:border-slate-700/80 transition-all duration-300">
        <!-- Topo do Card: Avatar, @, Seguidores e Botão Seguir -->
        <div class="flex items-center justify-between gap-3 mb-3.5">
          <div class="flex items-center gap-3 min-w-0">
            <div class="relative shrink-0 p-0.5 rounded-full bg-gradient-to-tr from-pink-500 via-purple-500 to-cyan-400 shadow-[0_0_15px_rgba(236,72,153,0.35)]">
              <?php if ($profilePic !== ''): ?>
                <img src="<?= htmlspecialchars($profilePic, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                     alt="@<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                     class="w-12 h-12 rounded-full object-cover bg-slate-950"
                     loading="lazy">
              <?php else: ?>
                <div class="w-12 h-12 rounded-full bg-slate-950 flex items-center justify-center text-pink-400">
                  <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                </div>
              <?php endif; ?>
            </div>
            <div class="min-w-0">
              <h3 class="font-bold text-white text-sm tracking-wide truncate">@<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></h3>
              <p class="text-xs text-slate-400 font-mono"><?= number_format($followers, 0, ',', '.') ?> seguidores</p>
            </div>
          </div>

          <a href="<?= htmlspecialchars($profileUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
             target="_blank"
             rel="noopener noreferrer"
             class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-gradient-to-r from-pink-500 to-purple-600 hover:from-pink-600 hover:to-purple-700 text-white text-xs font-bold shadow-md shadow-pink-500/25 transition-all transform hover:scale-105 shrink-0">
            <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
            <span>Seguir no Instagram</span>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
          </a>
        </div>

        <!-- Descrição da Bio -->
        <p class="text-xs text-slate-300 leading-relaxed mb-3">
          Conteúdo diário sobre tecnologia, games, animes, filmes e muito mais no nosso Instagram!
        </p>

        <!-- Tags de Pilares Editoriais (Mockup) -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-2 border-t border-slate-800/80 text-[11px] text-slate-400">
          <div class="flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-cyan-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span class="truncate">Bastidores</span>
          </div>
          <div class="flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-pink-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/></svg>
            <span class="truncate">Setups</span>
          </div>
          <div class="flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-yellow-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            <span class="truncate">Notícias</span>
          </div>
          <div class="flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-purple-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            <span class="truncate">Comunidade</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Sub-cabeçalho de Publicações com Controles de Paginação (Mockup) -->
    <div class="flex items-center justify-between gap-4 mb-6">
      <div class="flex items-center gap-2 text-xs sm:text-sm font-orbitron font-bold tracking-wider text-slate-300 uppercase">
        <span class="text-cyan-400 font-mono tracking-widest font-extrabold">///</span>
        <span>Publicações Recentes</span>
      </div>

      <!-- Controles de Paginação (1 / 3 e Botões < >) -->
      <div class="flex items-center gap-3">
        <?php if ($totalPages > 1): ?>
          <span id="ig-page-indicator" class="text-xs font-mono text-cyan-300 font-bold bg-slate-900 border border-slate-800 px-2.5 py-1 rounded-lg select-none">
            Página 1 de <?= (int) $totalPages ?>
          </span>
          <div class="flex items-center gap-1.5">
            <button type="button"
                    id="ig-btn-prev"
                    class="w-8 h-8 rounded-lg bg-slate-900 border border-slate-800 text-slate-600 opacity-40 cursor-not-allowed flex items-center justify-center transition-all shadow-sm"
                    disabled
                    aria-label="Página anterior">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button type="button"
                    id="ig-btn-next"
                    class="w-8 h-8 rounded-lg bg-slate-900 border border-slate-800 text-slate-400 hover:text-cyan-300 hover:border-cyan-500/50 flex items-center justify-center transition-all shadow-sm"
                    aria-label="Próxima página">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Contêiner de Páginas de Posts (3x2 Widescreen Estruturados) -->
    <div id="ig-posts-container" class="relative">
      <?php foreach ($pages as $pageIndex => $pagePosts): ?>
        <?php $pageNumber = $pageIndex + 1; ?>
        <div class="ig-page grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 sm:gap-6 transition-opacity duration-300 <?= $pageNumber === 1 ? '' : 'hidden' ?>"
             data-page="<?= $pageNumber ?>">
          <?php foreach ($pagePosts as $itemIndex => $item): ?>
            <?php
              $mediaUrl = (string) ($item['media_url'] ?? '');
              $permalink = (string) ($item['permalink'] ?? $profileUrl);
              $tipo = (string) ($item['tipo'] ?? 'imagem');
              $curtidas = (int) ($item['curtidas'] ?? 0);
              $comentarios = (int) ($item['comentarios_count'] ?? 0);
              $legenda = (string) ($item['legenda'] ?? '');
              $dateFormatted = $formatDatePt((string) ($item['publicado_em'] ?? ''));
              [$postTitle, $postSubtitle] = $extractHeadingAndSnippet($legenda);
              $isHighlighted = ($pageNumber === 1 && $itemIndex === 0);
            ?>
            <article class="group relative flex flex-col bg-slate-900/90 border <?= $isHighlighted ? 'border-pink-500/60 shadow-[0_0_20px_rgba(236,72,153,0.2)]' : 'border-slate-800/90' ?> rounded-2xl overflow-hidden hover:border-pink-500/60 hover:shadow-[0_0_25px_rgba(236,72,153,0.25)] transition-all duration-300 transform hover:-translate-y-1">
              <!-- Área da Mídia Retangular (Superior) -->
              <a href="<?= htmlspecialchars($permalink, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                 target="_blank"
                 rel="noopener noreferrer"
                 class="block relative aspect-[16/10] sm:aspect-[16/9] w-full overflow-hidden bg-slate-950">
                <?php if ($mediaUrl !== ''): ?>
                  <img src="<?= htmlspecialchars($mediaUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                       alt="<?= htmlspecialchars($postTitle, ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                       loading="lazy"
                       onerror="this.style.opacity='0.4';">
                <?php else: ?>
                  <div class="w-full h-full flex items-center justify-center bg-slate-950 text-slate-800">
                    <svg class="w-12 h-12 fill-current" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                  </div>
                <?php endif; ?>

                <!-- Tag de Tipo de Mídia (Mockup) -->
                <div class="absolute top-2.5 right-2.5 z-10 w-7 h-7 rounded-lg bg-slate-950/80 backdrop-blur-xs flex items-center justify-center text-white/90 shadow border border-white/10">
                  <?php if ($tipo === 'carrossel'): ?>
                    <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H8V4h12v12z"/></svg>
                  <?php elseif ($tipo === 'reels'): ?>
                    <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                  <?php else: ?>
                    <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                  <?php endif; ?>
                </div>
              </a>

              <!-- Área Textual do Card (Sempre Visível) -->
              <div class="p-4 flex-1 flex flex-col justify-between gap-3">
                <div>
                  <h4 class="font-bold text-white text-sm sm:text-base leading-snug line-clamp-1 group-hover:text-pink-300 transition-colors">
                    <?= htmlspecialchars($postTitle, ENT_QUOTES, 'UTF-8') ?>
                  </h4>
                  <p class="text-xs text-slate-400 line-clamp-1 leading-normal mt-1">
                    <?= htmlspecialchars($postSubtitle, ENT_QUOTES, 'UTF-8') ?>
                  </p>
                </div>

                <!-- Rodapé do Card: Data, Métricas e Links Rápidos -->
                <div class="border-t border-slate-800/90 pt-3 flex items-center justify-between text-xs text-slate-400">
                  <!-- Data -->
                  <span class="font-mono text-[11px] text-slate-500"><?= htmlspecialchars($dateFormatted !== '' ? $dateFormatted : 'Instagram', ENT_QUOTES, 'UTF-8') ?></span>

                  <!-- Métricas: Curtidas & Comentários -->
                  <div class="flex items-center gap-3">
                    <span class="inline-flex items-center gap-1 text-pink-400 text-xs font-semibold">
                      <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                      <span><?= (int) $curtidas ?></span>
                    </span>
                    <span class="inline-flex items-center gap-1 text-cyan-400 text-xs font-semibold">
                      <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 9h12v2H6V9zm8 5H6v-2h8v2zm4-6H6V6h12v2z"/></svg>
                      <span><?= (int) $comentarios ?></span>
                    </span>
                  </div>

                  <!-- Ações -->
                  <div class="flex items-center gap-2 text-slate-500">
                    <a href="<?= htmlspecialchars($permalink, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="text-pink-400/80 hover:text-pink-300 transition-colors"
                       title="Ver no Instagram">
                      <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    </a>
                    <a href="<?= htmlspecialchars($permalink, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="hover:text-cyan-400 transition-colors"
                       title="Abrir post">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                  </div>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Rodapé da Seção: Botão CTA Centralizado com Gradiente (Mockup) -->
    <div class="mt-12 text-center">
      <a href="<?= htmlspecialchars($profileUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
         target="_blank"
         rel="noopener noreferrer"
         class="inline-flex items-center gap-2.5 px-6 py-3 rounded-full bg-gradient-to-r from-pink-500 via-purple-600 to-pink-500 hover:from-pink-600 hover:to-purple-700 text-white font-bold text-xs sm:text-sm shadow-lg shadow-pink-500/25 hover:shadow-pink-500/40 transition-all transform hover:scale-105">
        <svg class="w-4 h-4 fill-current shrink-0" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
        <span>Ver todas as publicações no Instagram</span>
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
      </a>
    </div>
  </div>
</section>

<script>
(function() {
  const pages = document.querySelectorAll('.ig-page');
  const btnPrev = document.getElementById('ig-btn-prev');
  const btnNext = document.getElementById('ig-btn-next');
  const indicator = document.getElementById('ig-page-indicator');
  if (!pages.length || !btnPrev || !btnNext) return;

  let currentPage = 1;
  const totalPages = pages.length;

  function update() {
    pages.forEach((p) => {
      const pageNum = parseInt(p.getAttribute('data-page') || '1', 10);
      if (pageNum === currentPage) {
        p.classList.remove('hidden');
      } else {
        p.classList.add('hidden');
      }
    });

    if (indicator) {
      indicator.textContent = 'Página ' + currentPage + ' de ' + totalPages;
    }

    // Botão Anterior
    btnPrev.disabled = (currentPage <= 1);
    if (currentPage <= 1) {
      btnPrev.classList.add('opacity-40', 'cursor-not-allowed', 'text-slate-600');
      btnPrev.classList.remove('hover:text-cyan-300', 'hover:border-cyan-500/50', 'text-slate-400');
    } else {
      btnPrev.classList.remove('opacity-40', 'cursor-not-allowed', 'text-slate-600');
      btnPrev.classList.add('hover:text-cyan-300', 'hover:border-cyan-500/50', 'text-slate-400');
    }

    // Botão Próximo
    btnNext.disabled = (currentPage >= totalPages);
    if (currentPage >= totalPages) {
      btnNext.classList.add('opacity-40', 'cursor-not-allowed', 'text-slate-600');
      btnNext.classList.remove('hover:text-cyan-300', 'hover:border-cyan-500/50', 'text-slate-400');
    } else {
      btnNext.classList.remove('opacity-40', 'cursor-not-allowed', 'text-slate-600');
      btnNext.classList.add('hover:text-cyan-300', 'hover:border-cyan-500/50', 'text-slate-400');
    }
  }

  btnPrev.addEventListener('click', function(e) {
    e.preventDefault();
    if (currentPage > 1) {
      currentPage--;
      update();
    }
  });

  btnNext.addEventListener('click', function(e) {
    e.preventDefault();
    if (currentPage < totalPages) {
      currentPage++;
      update();
    }
  });

  update();
})();
</script>
