<?php
use App\Support\View;

$latestPosts = $latest_posts ?? [];
$featuredPosts = $featured_posts ?? [];
$siteMeta = $site_meta ?? [];
$hero = $hero ?? [];
$homeIntro = $home_intro ?? [];
$topicLinks = $topic_links ?? [];
$postsSection = $posts_section ?? [];

$siteName = (string) ($siteMeta['name'] ?? 'Estratégia Nerd');
$siteDescription = (string) ($siteMeta['description'] ?? 'Tecnologia, games, gadgets e cultura geek com reviews, comparativos, dicas e oportunidades que valem o clique.');
$bioTitle = (string) ($siteMeta['bio_title'] ?? $siteName);
$footerText = (string) ($siteMeta['footer'] ?? $siteName);
$siteKicker = (string) ($siteMeta['kicker'] ?? 'Portal geek estratégico');
$siteEmail = (string) ($siteMeta['email'] ?? '');
$brandSymbol = (string) ($siteMeta['brand_symbol'] ?? '');
$aboutMark = (string) ($siteMeta['about_image'] ?? '');
$brandSymbol = $brandSymbol !== '' ? $brandSymbol : url('/assets/brand/logo-symbol.png');
$aboutMark = $aboutMark !== '' ? $aboutMark : url('/assets/brand/logo-about.png');
$brandWordPrimary = 'ESTRATEGIA';
$brandWordAccent = 'NERD';
$showNewsletterHome = site_section_visible_on_home('newsletter');
$socialLinks = [
    'instagram' => [
        'url' => (string) ($siteMeta['instagram'] ?? ''),
        'label' => 'Instagram',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5" ry="5" fill="none" stroke="currentColor" stroke-width="1.9"/><circle cx="12" cy="12" r="4.2" fill="none" stroke="currentColor" stroke-width="1.9"/><circle cx="17.3" cy="6.7" r="1.1" fill="currentColor"/></svg>',
    ],
    'tiktok' => [
        'url' => (string) ($siteMeta['tiktok'] ?? ''),
        'label' => 'TikTok',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.5 4c.7 2 2.1 3.3 4.2 3.7v2.7a7.3 7.3 0 0 1-3.8-1.1v5.2a5.5 5.5 0 1 1-4.6-5.4v2.8a2.7 2.7 0 1 0 1.8 2.6V4h2.4Z" fill="currentColor"/></svg>',
    ],
    'kwai' => [
        'url' => (string) ($siteMeta['kwai'] ?? ''),
        'label' => 'Kwai',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8.2 5.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6Zm7.6 0a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6ZM6.3 12.3h11.4a3.3 3.3 0 0 1 3.3 3.3v.3a3.9 3.9 0 0 1-3.9 3.9H6.9A3.9 3.9 0 0 1 3 15.9v-.3a3.3 3.3 0 0 1 3.3-3.3Zm2.2 2.2a1.7 1.7 0 1 0 0 3.4 1.7 1.7 0 0 0 0-3.4Zm7 0a1.7 1.7 0 1 0 0 3.4 1.7 1.7 0 0 0 0-3.4Z" fill="currentColor"/></svg>',
    ],
    'youtube' => [
        'url' => (string) ($siteMeta['youtube'] ?? ''),
        'label' => 'YouTube',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.6 8.2a3 3 0 0 0-2.1-2.1C17.7 5.6 12 5.6 12 5.6s-5.7 0-7.5.5A3 3 0 0 0 2.4 8.2 31.6 31.6 0 0 0 2 12a31.6 31.6 0 0 0 .4 3.8 3 3 0 0 0 2.1 2.1c1.8.5 7.5.5 7.5.5s5.7 0 7.5-.5a3 3 0 0 0 2.1-2.1A31.6 31.6 0 0 0 22 12a31.6 31.6 0 0 0-.4-3.8ZM10 15.4V8.6l5.2 3.4L10 15.4Z" fill="currentColor"/></svg>',
    ],
    'telegram' => [
        'url' => (string) ($siteMeta['telegram'] ?? ''),
        'label' => 'Telegram',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m20.7 4.3-2.5 14.1c-.2 1-.8 1.2-1.7.7l-4.5-3.3-2.2 2.1c-.2.3-.5.5-1 .5l.3-4.6 8.4-7.6c.4-.3-.1-.5-.6-.2L6.5 12.6l-4.4-1.4c-.9-.3-1-.9.2-1.4L19 3.4c.8-.3 1.5.2 1.3.9Z" fill="currentColor"/></svg>',
    ],
    'whatsapp' => [
        'url' => (string) ($siteMeta['whatsapp'] ?? ''),
        'label' => 'WhatsApp',
        'icon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.2a8.7 8.7 0 0 0-7.5 13.1L3 21l4.9-1.3A8.8 8.8 0 1 0 12 3.2Zm0 15.8c-1.3 0-2.6-.4-3.6-1.1l-.3-.2-2.9.8.8-2.8-.2-.3A6.8 6.8 0 1 1 12 19Zm3.7-5.1c-.2-.1-1.3-.6-1.5-.7-.2-.1-.4-.1-.6.1l-.4.6c-.1.2-.3.2-.5.1a5.6 5.6 0 0 1-2.8-2.5c-.1-.2 0-.4.1-.5l.4-.5c.1-.2.2-.4 0-.6l-.7-1.6c-.1-.2-.3-.4-.6-.4h-.5c-.2 0-.5.1-.7.3-.7.6-1 1.4-.9 2.3.2 1.3.8 2.5 1.7 3.5a8 8 0 0 0 4.8 2.8c1 .1 2-.1 2.8-.7.4-.3.7-.9.8-1.4.1-.3 0-.5-.2-.6Z" fill="currentColor"/></svg>',
    ],
];
$hasSocialLinks = array_reduce(
    $socialLinks,
    static fn (bool $carry, array $social): bool => $carry || trim((string) ($social['url'] ?? '')) !== '',
    false
);
$contactHref = site_contact_fallback_href($siteEmail);
$homeEnhancementCssPath = base_path('public/assets/css/site-hero-preview.css');
$homeEnhancementJsPath = base_path('public/assets/js/site-hero-preview.js');
$homeEnhancementCssVersion = is_file($homeEnhancementCssPath) ? ((string) filemtime($homeEnhancementCssPath) . '-' . (string) filesize($homeEnhancementCssPath)) : '1';
$homeEnhancementJsVersion = is_file($homeEnhancementJsPath) ? ((string) filemtime($homeEnhancementJsPath) . '-' . (string) filesize($homeEnhancementJsPath)) : '1';
?>

<link rel="stylesheet" href="<?= url('/assets/css/site-hero-preview.css?v=' . $homeEnhancementCssVersion) ?>">

<div class="site-home-page site-home-hero-preview-page">
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

  <?= View::component('site/home/about-preview', [
      'about_mark' => $aboutMark,
      'bio_title' => $bioTitle,
      'home_intro' => $homeIntro,
      'topic_links' => $topicLinks,
  ]) ?>

  <div class="site-preview-reveal-wrap" data-reveal>
    <?= View::component('site/home/posts-preview', [
      'latest_posts' => $latestPosts,
      'featured_posts' => $featuredPosts,
      'posts_section' => $postsSection,
    ]) ?>
  </div>

  <?php if ($showNewsletterHome): ?>
    <?= View::component('site/home/newsletter') ?>
  <?php endif; ?>

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

<script src="https://cdn.jsdelivr.net/npm/animejs@4.5.0/dist/bundles/anime.umd.min.js" defer></script>
<script src="<?= url('/assets/js/site-hero-preview.js?v=' . $homeEnhancementJsVersion) ?>" defer></script>
