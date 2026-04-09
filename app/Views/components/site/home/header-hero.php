<?php
use App\Support\View;

$siteName = (string) ($site_name ?? 'Estrategia Nerd');
$siteDescription = (string) ($site_description ?? '');
$siteKicker = (string) ($site_kicker ?? '');
$brandSymbol = (string) ($brand_symbol ?? '');
$brandWordPrimary = (string) ($brand_word_primary ?? 'ESTRATEGIA');
$brandWordAccent = (string) ($brand_word_accent ?? 'NERD');

$showHero = site_section_visible_on_home('hero');
$blogHomeVisible = site_section_visible_on_home('blog');
$blogPublic = site_section_public_active('blog');
$newsletterHomeVisible = site_section_visible_on_home('newsletter');
$centralPublic = site_section_public_active('central_nerd');

$primaryCtaHref = $blogHomeVisible
    ? '#blog'
    : ($blogPublic ? site_section_href('blog') : ($centralPublic ? site_section_href('central_nerd') : url('/')));
$primaryCtaLabel = $blogPublic ? 'Explorar conteudo' : ($centralPublic ? 'Abrir central' : 'Explorar');

$secondaryCta = null;
if ($newsletterHomeVisible) {
    $secondaryCta = ['href' => '#newsletter', 'label' => 'Inscrever-se'];
} elseif ($centralPublic) {
    $secondaryCta = ['href' => site_section_href('central_nerd'), 'label' => 'Ver links'];
} elseif ($blogPublic) {
    $secondaryCta = ['href' => site_section_href('blog'), 'label' => 'Ver blog'];
}
?>

<?= View::component('site/shared/nav', [
    'site_name' => $siteName,
    'site_kicker' => $siteKicker,
    'brand_symbol' => $brandSymbol,
    'brand_word_primary' => $brandWordPrimary,
    'brand_word_accent' => $brandWordAccent,
    'active_page' => 'home',
]) ?>

<?php if ($showHero): ?>
<section id="home" class="site-hero-section relative min-h-screen flex items-center justify-center pt-24 overflow-hidden">
  <div class="absolute inset-0 bg-gradient-to-b from-cyan-500/10 via-transparent to-slate-950"></div>
  <div class="absolute inset-0 site-grid-fade"></div>
  <div class="site-hero-aurora site-hero-aurora-cyan" aria-hidden="true"></div>
  <div class="site-hero-aurora site-hero-aurora-violet" aria-hidden="true"></div>
  <div class="site-hero-traces" aria-hidden="true">
    <span class="trace trace-h trace-h-1"></span>
    <span class="trace trace-h trace-h-2"></span>
    <span class="trace trace-v trace-v-1"></span>
    <span class="trace trace-v trace-v-2"></span>
    <span class="trace-pulse pulse-h pulse-h-1"></span>
    <span class="trace-pulse pulse-h pulse-h-2"></span>
    <span class="trace-pulse pulse-v pulse-v-1"></span>
    <span class="trace-pulse pulse-v pulse-v-2"></span>
    <span class="trace-node node-1"></span>
    <span class="trace-node node-2"></span>
    <span class="trace-node node-3"></span>
    <span class="trace-node node-4"></span>
  </div>
  <div class="site-hero-circuit site-hero-circuit-left" aria-hidden="true"></div>
  <div class="site-hero-circuit site-hero-circuit-right" aria-hidden="true"></div>
  <div class="site-hero-particles" aria-hidden="true">
    <span></span>
    <span></span>
    <span></span>
    <span></span>
    <span></span>
    <span></span>
  </div>

  <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full text-center">
    <div class="mb-8 inline-flex items-center gap-3 px-4 py-2 rounded-full border border-cyan-500/30 bg-cyan-500/10 text-cyan-300 text-sm font-medium tracking-widest uppercase">
      <span class="w-2 h-2 rounded-full bg-cyan-300"></span>
      Bem-vindo ao next level
    </div>

    <h1 class="font-orbitron text-5xl md:text-7xl lg:text-8xl font-black mb-6 leading-tight">
      <span class="block text-white glitch" data-text="ESTRATEGIA">ESTRATEGIA</span>
      <span class="block text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 via-blue-500 to-purple-600 neon-text">NERD</span>
    </h1>

    <p class="text-xl md:text-2xl text-slate-400 max-w-3xl mx-auto mb-12 font-light site-hero-copy">
      <?= htmlspecialchars($siteDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </p>

    <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
      <a href="<?= htmlspecialchars($primaryCtaHref, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="site-primary-btn"><?= htmlspecialchars($primaryCtaLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
      <?php if (is_array($secondaryCta)): ?>
        <a href="<?= htmlspecialchars((string) ($secondaryCta['href'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="site-secondary-btn"><?= htmlspecialchars((string) ($secondaryCta['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
      <?php endif; ?>
    </div>

    <div class="absolute top-1/4 left-10 w-20 h-20 border border-cyan-500/20 rounded-full animate-pulse hidden lg:block"></div>
    <div class="absolute bottom-1/4 right-10 w-32 h-32 border border-purple-500/20 rounded-full animate-pulse hidden lg:block" style="animation-delay: 1s;"></div>
  </div>
</section>
<?php endif; ?>