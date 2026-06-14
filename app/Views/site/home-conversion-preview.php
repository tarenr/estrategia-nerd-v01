<?php
declare(strict_types=1);

use App\Support\View;

$latestPosts = is_array($latest_posts ?? null) ? $latest_posts : [];
$featuredPosts = is_array($featured_posts ?? null) ? $featured_posts : [];
$siteMeta = is_array($site_meta ?? null) ? $site_meta : [];
$hero = is_array($hero ?? null) ? $hero : [];
$homeIntro = is_array($home_intro ?? null) ? $home_intro : [];
$topicLinks = is_array($topic_links ?? null) ? $topic_links : [];
$postsSection = is_array($posts_section ?? null) ? $posts_section : [];
$links = is_array($links ?? null) ? $links : [];

$siteName = (string) ($siteMeta['name'] ?? 'Estrategia Nerd');
$siteDescription = (string) ($siteMeta['description'] ?? 'Tecnologia, games, gadgets e cultura geek com reviews, comparativos, dicas e oportunidades que valem o clique.');
$bioTitle = (string) ($siteMeta['bio_title'] ?? $siteName);
$footerText = (string) ($siteMeta['footer'] ?? $siteName);
$siteKicker = (string) ($siteMeta['kicker'] ?? 'Portal geek estrategico');
$siteEmail = (string) ($siteMeta['email'] ?? '');
$brandSymbol = (string) ($siteMeta['brand_symbol'] ?? '');
$aboutMark = (string) ($siteMeta['about_image'] ?? '');
$brandSymbol = $brandSymbol !== '' ? $brandSymbol : url('/assets/brand/logo-symbol.png');
$aboutMark = $aboutMark !== '' ? $aboutMark : url('/assets/brand/logo-about.png');
$brandWordPrimary = 'ESTRATEGIA';
$brandWordAccent = 'NERD';
$showNewsletterHome = site_section_visible_on_home('newsletter');
$previewCssPath = base_path('public/assets/css/site-hero-preview.css');
$additionsCssPath = base_path('public/assets/css/home-preview.css');
$previewJsPath = base_path('public/assets/js/site-hero-preview.js');
$previewCssVersion = is_file($previewCssPath) ? ((string) filemtime($previewCssPath) . '-' . (string) filesize($previewCssPath)) : '1';
$additionsCssVersion = is_file($additionsCssPath) ? ((string) filemtime($additionsCssPath) . '-' . (string) filesize($additionsCssPath)) : '1';
$previewJsVersion = is_file($previewJsPath) ? ((string) filemtime($previewJsPath) . '-' . (string) filesize($previewJsPath)) : '1';
$offerLinks = array_slice(array_values(array_filter($links, static fn (array $item): bool => in_array((string) ($item['tipo'] ?? ''), ['produto', 'cupom'], true))), 0, 3);

$socialLinks = [
    'instagram' => ['url' => (string) ($siteMeta['instagram'] ?? ''), 'label' => 'Instagram', 'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5" ry="5" fill="none" stroke="currentColor" stroke-width="1.9"/><circle cx="12" cy="12" r="4.2" fill="none" stroke="currentColor" stroke-width="1.9"/><circle cx="17.3" cy="6.7" r="1.1" fill="currentColor"/></svg>'],
    'tiktok' => ['url' => (string) ($siteMeta['tiktok'] ?? ''), 'label' => 'TikTok', 'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.5 4c.7 2 2.1 3.3 4.2 3.7v2.7a7.3 7.3 0 0 1-3.8-1.1v5.2a5.5 0 1 1-4.6-5.4v2.8a2.7 2.7 0 1 0 1.8 2.6V4h2.4Z" fill="currentColor"/></svg>'],
    'kwai' => ['url' => (string) ($siteMeta['kwai'] ?? ''), 'label' => 'Kwai', 'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8.2 5.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Zm7.6 0a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6ZM6.3 12.3h11.4a3.3 3.3 0 0 1 3.3 3.3v.3a3.9 3.9 0 0 1-3.9 3.9H6.9A3.9 3.9 0 0 1 3 15.9v-.3a3.3 3.3 0 0 1 3.3-3.3Zm2.2 2.2a1.7 1.7 0 1 0 0 3.4 1.7 1.7 0 0 0 0-3.4Zm7 0a1.7 1.7 0 1 0 0 3.4 1.7 1.7 0 0 0 0-3.4Z" fill="currentColor"/></svg>'],
    'youtube' => ['url' => (string) ($siteMeta['youtube'] ?? ''), 'label' => 'YouTube', 'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.6 8.2a3 3 0 0 0-2.1-2.1C17.7 5.6 12 5.6 12 5.6s-5.7 0-7.5.5A3 3 0 0 0 2.4 8.2 31.6 31.6 0 0 0 2 12a31.6 31.6 0 0 0 .4 3.8 3 3 0 0 0 2.1 2.1c1.8.5 7.5.5 7.5.5s5.7 0 7.5-.5a3 3 0 0 0 2.1-2.1A31.6 31.6 0 0 0 22 12a31.6 31.6 0 0 0-.4-3.8ZM10 15.4V8.6l5.2 3.4L10 15.4Z" fill="currentColor"/></svg>'],
    'telegram' => ['url' => (string) ($siteMeta['telegram'] ?? ''), 'label' => 'Telegram', 'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m20.7 4.3-2.5 14.1c-.2 1-.8 1.2-1.7.7l-4.5-3.3-2.2 2.1c-.2.3-.5.5-1 .5l.3-4.6 8.4-7.6c.4-.3-.1-.5-.6-.2L6.5 12.6l-4.4-1.4c-.9-.3-1-.9.2-1.4L19 3.4c.8-.3 1.5.2 1.3.9Z" fill="currentColor"/></svg>'],
    'whatsapp' => ['url' => (string) ($siteMeta['whatsapp'] ?? ''), 'label' => 'WhatsApp', 'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.2a8.7 8.7 0 0 0-7.5 13.1L3 21l4.9-1.3A8.8 8.8 0 1 0 12 3.2Zm0 15.8c-1.3 0-2.6-.4-3.6-1.1l-.3-.2-2.9.8.8-2.8-.2-.3A6.8 6.8 0 1 1 12 19Zm3.7-5.1c-.2-.1-1.3-.6-1.5-.7-.2-.1-.4-.1-.6.1l-.4.6c-.1.2-.3.2-.5.1a5.6 5.6 0 0 1-2.8-2.5c-.1-.2 0-.4.1-.5l.4-.5c.1-.2.2-.4 0-.6l-.7-1.6c-.1-.2-.3-.4-.6-.4h-.5c-.2 0-.5.1-.7.3-.7.6-1 1.4-.9 2.3.2 1.3.8 2.5 1.7 3.5a8 8 0 0 0 4.8 2.8c1 .1 2-.1 2.8-.7.4-.3.7-.9.8-1.4.1-.3 0-.5-.2-.6Z" fill="currentColor"/></svg>'],
];
$hasSocialLinks = array_reduce($socialLinks, static fn (bool $carry, array $social): bool => $carry || trim((string) ($social['url'] ?? '')) !== '', false);
$contactHref = site_contact_fallback_href($siteEmail);
?>

<link rel="stylesheet" href="<?= url('/assets/css/site-hero-preview.css?v=' . $previewCssVersion) ?>">
<link rel="stylesheet" href="<?= url('/assets/css/home-preview.css?v=' . $additionsCssVersion) ?>">

<div class="site-home-page site-home-hero-preview-page home-preview-additive-page">
  <div class="circuit-bg"></div>
  <div class="scanline"></div>
  <div id="siteToast" class="site-toast" aria-live="polite"></div>

  <?= View::component('site/home/header-hero-preview', [
      'site_name' => $siteName,
      'site_description' => $siteDescription,
      'site_kicker' => $siteKicker,
      'brand_symbol' => $brandSymbol,
      'brand_word_primary' => $brandWordPrimary,
      'brand_word_accent' => $brandWordAccent,
      'hero' => $hero,
  ]) ?>

  <section id="por-onde-comecar" class="home-preview-addition home-preview-paths" data-reveal>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="home-preview-addition-head">
        <span>Comece por aqui</span>
        <h2>Escolha seu caminho nerd</h2>
        <p>Um bloco novo, mas dentro da identidade atual: ele guia a visita para reviews, setup, newsletter e Central Nerd sem substituir o hero.</p>
      </div>
      <div class="home-preview-path-grid">
        <a href="<?= htmlspecialchars(site_section_public_active('blog') ? site_section_href('blog') : url('/blog'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="site-value-box site-value-cyan home-preview-path-card">
          <h3>Reviews e comparativos</h3>
          <p>Para decidir melhor antes de comprar, montar ou trocar uma peca do setup.</p>
        </a>
        <a href="<?= htmlspecialchars(($topicLinks[0]['url'] ?? url('/blog')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="site-value-box site-value-purple home-preview-path-card">
          <h3>Setup do Dev Nerd</h3>
          <p>Um futuro caminho editorial para hardware, perifericos, produtividade e criacao.</p>
        </a>
        <a href="<?= htmlspecialchars(site_section_public_active('central_nerd') ? site_section_href('central_nerd') : url('/central-nerd'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="site-value-box site-value-cyan home-preview-path-card">
          <h3>Central Nerd</h3>
          <p>Ofertas e atalhos como apoio ao conteudo, sem transformar a Home em vitrine.</p>
        </a>
      </div>
    </div>
  </section>

  <?= View::component('site/home/about-preview', [
      'about_mark' => $aboutMark,
      'bio_title' => $bioTitle,
      'home_intro' => $homeIntro,
      'topic_links' => $topicLinks,
  ]) ?>

  <section class="home-preview-addition home-preview-authority-strip" data-reveal>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="home-preview-authority-panel">
        <div>
          <span>Autoridade editorial</span>
          <h2>Menos ruido, mais criterio.</h2>
        </div>
        <p>Este reforco entra como camada adicional: deixa claro que o Estrategia Nerd nao publica so por volume, mas para ajudar o leitor a comparar, entender contexto e escolher melhor.</p>
      </div>
    </div>
  </section>

  <div class="site-preview-reveal-wrap" data-reveal>
    <?= View::component('site/home/posts-preview', [
      'latest_posts' => $latestPosts,
      'featured_posts' => $featuredPosts,
      'posts_section' => $postsSection,
    ]) ?>
  </div>

  <?php if ($showNewsletterHome): ?>
    <section class="home-preview-addition home-preview-newsletter-note" data-reveal>
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="home-preview-newsletter-note-box">
          <span>Conversao sem descaracterizar</span>
          <p>A newsletter abaixo continua com o visual atual. A melhoria proposta e de copy e contexto: receber reviews, comparativos, setups e ofertas uteis sem ruido.</p>
        </div>
      </div>
    </section>
    <div class="site-preview-reveal-wrap" data-reveal>
      <?= View::component('site/home/newsletter') ?>
    </div>
  <?php endif; ?>

  <?php if ($offerLinks !== []): ?>
    <section class="home-preview-addition home-preview-affiliate-preview" data-reveal>
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="home-preview-addition-head">
          <span>Estrutura futura</span>
          <h2>Afiliados como bloco editorial discreto</h2>
          <p>Reaproveitando a Central Nerd, esta area testa produtos/ofertas como complemento. Nada de recriar motor ou mudar o fluxo de Links.</p>
        </div>
        <div class="home-preview-offer-grid">
          <?php foreach ($offerLinks as $item): ?>
            <a href="<?= htmlspecialchars((string) ($item['url'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="home-preview-offer-card">
              <?php if ((string) ($item['imagem'] ?? '') !== ''): ?>
                <img src="<?= htmlspecialchars((string) $item['imagem'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($item['titulo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" loading="lazy" decoding="async">
              <?php endif; ?>
              <div>
                <span><?= htmlspecialchars((string) ($item['selo'] ?? 'Central Nerd'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <strong><?= htmlspecialchars((string) ($item['titulo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
                <small><?= htmlspecialchars((string) ($item['cta'] ?? 'Ver produto'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
        <p class="home-preview-affiliate-note">Bloco de preview. Aviso formal de afiliado e regras editoriais devem ser definidos antes de producao.</p>
      </div>
    </section>
  <?php endif; ?>

  <div class="site-preview-reveal-wrap site-preview-reveal-footer" data-reveal>
    <?= View::component('site/home/footer', [
      'site_name' => $siteName,
      'site_description' => $siteDescription,
      'site_kicker' => $siteKicker,
      'footer_text' => $footerText,
      'site_email' => $siteEmail,
      'brand_symbol' => $brandSymbol,
      'social_links' => $socialLinks,
      'brand_word_primary' => $brandWordPrimary,
      'brand_word_accent' => $brandWordAccent,
      'has_social_links' => $hasSocialLinks,
      'contact_href' => $contactHref,
    ]) ?>
  </div>
</div>

<script src="<?= url('/assets/js/site-hero-preview.js?v=' . $previewJsVersion) ?>" defer></script>
