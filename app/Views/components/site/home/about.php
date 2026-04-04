<?php
$aboutMark = (string) ($about_mark ?? '');
$bioTitle = (string) ($bio_title ?? 'Estrategia Nerd');
?>

<section id="sobre" class="site-about-section py-32 relative overflow-hidden">
  <div class="site-about-backdrop" aria-hidden="true"></div>
  <div class="site-about-grid" aria-hidden="true"></div>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid lg:grid-cols-2 gap-16 items-center">
      <div class="relative">
        <div class="absolute -inset-4 bg-gradient-to-r from-cyan-500 to-purple-600 rounded-[2rem] opacity-20 blur-xl"></div>
        <div class="relative site-profile-card">
          <div class="site-profile-orb">
            <img src="<?= htmlspecialchars($aboutMark, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($bioTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="site-about-mark">
          </div>
        </div>
      </div>

      <div>
        <h2 class="font-orbitron text-4xl md:text-5xl font-black text-white leading-tight">
          A origem da <span class="text-cyan-400">estrategia</span>
        </h2>
        <div class="space-y-6 text-lg text-slate-300 leading-relaxed mt-6">
          <p>A Estrategia Nerd nasce da paixao por tecnologia, cultura geek e aquele prazer classico de descobrir algo incrivel antes de todo mundo.</p>
          <p>Aqui a ideia e unir conteudo, curiosidade e oportunidade no mesmo lugar. Nao e so um blog: e a base editorial de um portal proprio.</p>
          <p>Tudo passa por um filtro simples: <span class="text-cyan-400 font-semibold">vale a pena mesmo?</span></p>
        </div>

        <div class="mt-10 grid md:grid-cols-2 gap-4">
          <div class="site-value-box site-value-cyan"><h3>Conteudo com criterio</h3><p>Reviews, listas, comparativos e guias com leitura clara e util.</p></div>
          <div class="site-value-box site-value-purple"><h3>Nerd com proposito</h3><p>Paixao por tecnologia, cultura pop e recomendacoes com criterio.</p></div>
          <div class="site-value-box site-value-blue"><h3>Level up constante</h3><p>Um portal pronto para crescer com blog, newsletter e novas frentes.</p></div>
          <div class="site-value-box site-value-green"><h3>Domine o jogo</h3><p>Informacao util para estar sempre um passo a frente.</p></div>
        </div>
      </div>
    </div>
  </div>
</section>
