<?php
declare(strict_types=1);

use App\Support\View;

$siteMeta = is_array($site_meta ?? null) ? $site_meta : [];
$siteName = (string) ($siteMeta['name'] ?? 'Estratégia Nerd');
$siteKicker = (string) ($siteMeta['kicker'] ?? 'Portal geek estratégico');
$brandSymbol = (string) ($siteMeta['brand_symbol'] ?? '');
$brandSymbol = $brandSymbol !== '' ? $brandSymbol : url('/assets/brand/logo-symbol.png');
$brandWordPrimary = 'ESTRATEGIA';
$brandWordAccent = 'NERD';
$headline = trim((string) ($headline ?? 'Post não encontrado'));
$message = trim((string) ($message ?? 'Esse conteúdo não está disponível no momento.'));
$note = trim((string) ($note ?? ''));
$reason = trim((string) ($reason ?? 'not_found'));
$requestedSlug = trim((string) ($requested_slug ?? ''));
$matchedPost = is_array($matched_post ?? null) ? $matched_post : null;
$recentPosts = is_array($recent_posts ?? null) ? $recent_posts : [];

$reasonLabel = match ($reason) {
    'scheduled' => 'Post agendado',
    'unavailable' => 'Post indisponível',
    default => 'Post não encontrado',
};
?>

<div class="site-home-page site-post-page">
  <div class="circuit-bg"></div>
  <div class="scanline"></div>

  <?= View::component('site/shared/nav', [
      'site_name' => $siteName,
      'site_kicker' => $siteKicker,
      'brand_symbol' => $brandSymbol,
      'brand_word_primary' => $brandWordPrimary,
      'brand_word_accent' => $brandWordAccent,
      'active_page' => 'blog',
  ]) ?>

  <section class="relative pt-32 pb-12 overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-b from-cyan-500/10 via-transparent to-slate-950/60"></div>

    <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="max-w-3xl mx-auto text-center">
        <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-cyan-500/20 bg-cyan-500/10 text-cyan-300 text-sm font-semibold tracking-[0.18em] uppercase">
          <?= htmlspecialchars($reasonLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </span>

        <h1 class="mt-6 font-orbitron text-4xl md:text-5xl lg:text-6xl font-black text-white leading-tight">
          <?= htmlspecialchars($headline, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </h1>

        <p class="post-lead max-w-3xl mx-auto mt-6">
          <?= htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </p>

        <?php if ($note !== ''): ?>
          <p class="mt-4 text-base md:text-lg text-slate-400 leading-8">
            <?= htmlspecialchars($note, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
          </p>
        <?php endif; ?>
      </div>

      <div class="mt-10 max-w-4xl mx-auto grid gap-6 lg:grid-cols-[1.3fr_0.7fr]">
        <div class="site-empty-panel p-7 md:p-8 border border-cyan-500/15 rounded-[2rem]">
          <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
              <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Slug solicitado</p>
              <p class="mt-2 font-rajdhani text-xl text-white break-all">/post/<?= htmlspecialchars($requestedSlug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            </div>
            <?php if (is_array($matchedPost)): ?>
              <span class="inline-flex items-center rounded-full border border-cyan-500/20 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-300">
                <?= htmlspecialchars(strtoupper((string) ($matchedPost['status'] ?? 'indisponível')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
              </span>
            <?php endif; ?>
          </div>

          <?php if (is_array($matchedPost) && trim((string) ($matchedPost['titulo'] ?? '')) !== ''): ?>
            <div class="mt-6 rounded-3xl border border-white/8 bg-slate-950/35 p-5">
              <p class="text-xs uppercase tracking-[0.22em] text-slate-400">Post relacionado a este endereço</p>
              <h2 class="mt-3 font-orbitron text-2xl text-white leading-tight">
                <?= htmlspecialchars(public_title((string) ($matchedPost['titulo'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
              </h2>
              <?php if (trim((string) ($matchedPost['data'] ?? '')) !== ''): ?>
                <p class="mt-3 text-sm text-slate-400">
                  Data registrada: <?= htmlspecialchars((string) ($matchedPost['data'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </p>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <div class="mt-7 flex flex-wrap gap-3">
            <a href="<?= htmlspecialchars(url('/blog'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="site-nav-cta">
              Voltar para o blog
            </a>
            <a href="<?= htmlspecialchars(url('/'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-full border border-cyan-500/20 bg-slate-900/50 px-6 py-3 text-sm font-semibold text-white transition hover:border-cyan-400/40 hover:text-cyan-300">
              Ir para a home
            </a>
          </div>
        </div>

        <div class="site-empty-panel p-7 md:p-8 border border-cyan-500/15 rounded-[2rem]">
          <p class="text-xs uppercase tracking-[0.24em] text-slate-400">O que você pode fazer agora</p>
          <div class="mt-5 space-y-4 text-slate-300">
            <div class="rounded-2xl border border-white/8 bg-slate-950/30 p-4">
              Verifique se o endereço foi digitado corretamente.
            </div>
            <div class="rounded-2xl border border-white/8 bg-slate-950/30 p-4">
              Use o blog para encontrar a versão pública mais recente do conteúdo.
            </div>
            <div class="rounded-2xl border border-white/8 bg-slate-950/30 p-4">
              Se o post estiver agendado, ele abrirá normalmente quando for publicado.
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <?php if ($recentPosts !== []): ?>
    <section class="pb-16">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-end justify-between gap-4 mb-8 flex-wrap">
          <div>
            <p class="text-xs uppercase tracking-[0.24em] text-cyan-300">Explorar conteúdo</p>
            <h2 class="mt-2 font-orbitron text-2xl md:text-3xl text-white">Posts recentes</h2>
          </div>
          <a href="<?= htmlspecialchars(url('/blog'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="text-cyan-300 font-semibold hover:text-cyan-200 transition">Abrir blog</a>
        </div>

        <div class="grid gap-6 md:grid-cols-3">
          <?php foreach ($recentPosts as $item): ?>
            <article class="site-post-card p-4 md:p-5 rounded-[1.6rem] border border-cyan-500/12">
              <a href="<?= htmlspecialchars((string) ($item['url'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="block rounded-2xl overflow-hidden bg-slate-900/40 border border-white/6">
                <?php if (trim((string) ($item['imagem'] ?? '')) !== ''): ?>
                  <img src="<?= htmlspecialchars((string) ($item['imagem'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars(public_title((string) ($item['titulo'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="w-full h-44 object-cover">
                <?php else: ?>
                  <div class="h-44 grid place-items-center text-cyan-300 bg-gradient-to-br from-cyan-500/20 to-blue-600/20">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h11l5 5v9a2 2 0 01-2 2Z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M14 4v5h5M8 13h8M8 17h5M8 9h2"/>
                    </svg>
                  </div>
                <?php endif; ?>
              </a>
              <div class="mt-4">
                <p class="text-xs uppercase tracking-[0.22em] text-cyan-300">
                  <?= htmlspecialchars((string) ($item['categoria_nome'] ?? 'Blog'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </p>
                <h3 class="mt-2 font-orbitron text-xl leading-tight text-white">
                  <a href="<?= htmlspecialchars((string) ($item['url'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="hover:text-cyan-300 transition">
                    <?= htmlspecialchars(public_title((string) ($item['titulo'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                  </a>
                </h3>
                <?php if (trim((string) ($item['resumo'] ?? '')) !== ''): ?>
                  <p class="mt-3 text-slate-400 leading-7">
                    <?= htmlspecialchars((string) ($item['resumo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                  </p>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>
</div>