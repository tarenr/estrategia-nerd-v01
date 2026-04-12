<?php
use App\Support\Csrf;

if (!site_section_public_active('newsletter')) {
    return;
}
?>

<section id="newsletter" class="py-28 relative">
  <div class="absolute inset-0 bg-gradient-to-r from-cyan-900/20 to-fuchsia-900/20"></div>
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
    <div class="bg-slate-800/50 backdrop-blur-md p-8 md:p-12 rounded-3xl border border-cyan-500/20 shadow-2xl">
      <h2 class="font-orbitron text-3xl md:text-4xl font-bold mb-4 text-white">
        Suba de <span class="text-cyan-400">Nível</span>
      </h2>
      <p class="text-gray-400 mb-8 text-lg max-w-3xl mx-auto">
        Receba as melhores dicas, reviews exclusivas e ofertas imperdíveis diretamente no seu e-mail. Sem spam, só conteúdo nerd de qualidade.
      </p>

      <form id="newsletter-form" class="flex flex-col gap-4 max-w-2xl mx-auto" action="<?= htmlspecialchars(url('/newsletter'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" method="POST">
        <?= Csrf::field() ?>
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-[1fr_1.1fr_auto] items-center">
          <input type="text" id="name-input" name="nome" aria-required="true" placeholder="Seu nome" class="w-full px-6 py-4 bg-slate-900/50 border border-cyan-500/30 rounded-full text-white placeholder-gray-500 focus:outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/20 transition-all">
          <input type="email" id="email-input" name="email" aria-required="true" placeholder="seu@email.com" class="w-full px-6 py-4 bg-slate-900/50 border border-cyan-500/30 rounded-full text-white placeholder-gray-500 focus:outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/20 transition-all">
          <button type="submit" id="submit-btn" class="px-8 py-4 bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-bold rounded-full hover:shadow-lg hover:shadow-cyan-500/25 transition-all transform hover:scale-105 flex items-center justify-center gap-2 min-w-[140px] w-full md:col-span-2 lg:col-span-1 lg:w-auto">
            <span id="btn-text">Inscrever</span>
            <svg id="btn-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
            </svg>
            <svg id="btn-loading" class="w-5 h-5 animate-spin hidden" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
          </button>
        </div>
      </form>

      <p class="mt-6 text-sm text-gray-500">
        Ao se inscrever, você concorda com nossa política de privacidade. Pode cancelar a qualquer momento.
      </p>
    </div>
  </div>
</section>