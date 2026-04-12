<?php
declare(strict_types=1);

use App\Support\View;

$requestedPath = trim((string) ($requested_path ?? ''));
$siteName = 'Estratégia Nerd';
$siteKicker = 'Portal geek estratégico';
$brandSymbol = url('/assets/brand/logo-symbol.png');
?>

<div class="site-home-page site-post-page">
  <div class="circuit-bg"></div>
  <div class="scanline"></div>

  <?= View::component('site/shared/nav', [
      'site_name' => $siteName,
      'site_kicker' => $siteKicker,
      'brand_symbol' => $brandSymbol,
      'brand_word_primary' => 'ESTRATEGIA',
      'brand_word_accent' => 'NERD',
      'active_page' => '',
  ]) ?>

  <section class="relative pt-32 pb-16 overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-b from-cyan-500/10 via-transparent to-slate-950/60"></div>

    <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="max-w-3xl mx-auto text-center">
        <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-cyan-500/20 bg-cyan-500/10 text-cyan-300 text-sm font-semibold tracking-[0.18em] uppercase">
          Página não encontrada
        </span>

        <h1 class="mt-6 font-orbitron text-4xl md:text-5xl lg:text-6xl font-black text-white leading-tight">
          Esse caminho não existe no portal
        </h1>

        <p class="post-lead max-w-3xl mx-auto mt-6">
          O endereço solicitado não foi encontrado. Você pode voltar para a home, abrir o blog ou seguir para a Central Nerd.
        </p>
      </div>

      <div class="mt-10 max-w-4xl mx-auto grid gap-6 lg:grid-cols-[1.25fr_0.75fr]">
        <div class="site-empty-panel p-7 md:p-8 border border-cyan-500/15 rounded-[2rem]">
          <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Caminho solicitado</p>
          <p class="mt-3 font-rajdhani text-xl text-white break-all">
            <?= htmlspecialchars($requestedPath !== '' ? $requestedPath : '/', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
          </p>

          <div class="mt-7 flex flex-wrap gap-3">
            <a href="<?= htmlspecialchars(url('/'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="site-nav-cta">
              Ir para a home
            </a>
            <a href="<?= htmlspecialchars(url('/blog'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-full border border-cyan-500/20 bg-slate-900/50 px-6 py-3 text-sm font-semibold text-white transition hover:border-cyan-400/40 hover:text-cyan-300">
              Abrir blog
            </a>
            <a href="<?= htmlspecialchars(url('/central-nerd'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-full border border-cyan-500/20 bg-slate-900/50 px-6 py-3 text-sm font-semibold text-white transition hover:border-cyan-400/40 hover:text-cyan-300">
              Abrir Central Nerd
            </a>
          </div>
        </div>

        <div class="site-empty-panel p-7 md:p-8 border border-cyan-500/15 rounded-[2rem]">
          <p class="text-xs uppercase tracking-[0.24em] text-slate-400">Dicas rapidas</p>
          <div class="mt-5 space-y-4 text-slate-300">
            <div class="rounded-2xl border border-white/8 bg-slate-950/30 p-4">
              Verifique se o endereço foi digitado corretamente.
            </div>
            <div class="rounded-2xl border border-white/8 bg-slate-950/30 p-4">
              Use o blog para abrir os posts públicos disponíveis agora.
            </div>
            <div class="rounded-2xl border border-white/8 bg-slate-950/30 p-4">
              Se você chegou por um link antigo, ele pode ter sido alterado ou removido.
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>