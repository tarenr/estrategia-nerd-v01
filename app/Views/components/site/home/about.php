<?php
if (!site_section_visible_on_home('sobre')) {
    return;
}

$aboutMark = (string) ($about_mark ?? '');
$bioTitle = (string) ($bio_title ?? 'Estratégia Nerd');
$homeIntro = $home_intro ?? [];
$topicLinks = $topic_links ?? [];
$introLinks = (array) ($homeIntro['context_links'] ?? []);
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
            <img src="<?= htmlspecialchars($aboutMark, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="" role="presentation" class="site-about-mark">
          </div>
        </div>
      </div>

      <div>
        <h2 class="font-orbitron text-4xl md:text-5xl font-black text-white leading-tight">
          <?= htmlspecialchars((string) ($homeIntro['title'] ?? 'O que você encontra no Estratégia Nerd'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </h2>
        <div class="space-y-6 text-lg text-slate-300 leading-relaxed mt-6">
          <p class="text-cyan-100 font-medium"><?= htmlspecialchars((string) ($homeIntro['lead'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
          <?php foreach ((array) ($homeIntro['body'] ?? []) as $paragraph): ?>
            <p><?= htmlspecialchars((string) $paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
          <?php endforeach; ?>
          <?php if ($introLinks !== []): ?>
            <p class="text-base text-slate-300">
              Comece pelo
              <?php foreach ($introLinks as $index => $link): ?>
                <?php if ($index > 0): ?><?= $index === count($introLinks) - 1 ? ' ou ' : ', ' ?><?php endif; ?>
                <a href="<?= htmlspecialchars((string) ($link['url'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="text-cyan-300 hover:text-cyan-200 underline underline-offset-4 decoration-cyan-500/40">
                  <?= htmlspecialchars((string) ($link['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </a>
              <?php endforeach; ?>.
            </p>
          <?php endif; ?>
        </div>

        <div class="mt-10 grid md:grid-cols-2 gap-4">
          <?php foreach ((array) ($homeIntro['pillars'] ?? []) as $pillar): ?>
            <div class="site-value-box site-value-cyan">
              <h3><?= htmlspecialchars((string) ($pillar['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
              <p><?= htmlspecialchars((string) ($pillar['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <?php if ($topicLinks !== []): ?>
      <div class="mt-20">
        <div class="max-w-3xl mb-8">
          <h2 class="font-orbitron text-3xl md:text-4xl font-bold text-white">Temas centrais do portal</h2>
          <p class="text-slate-300 text-lg mt-3">Navegue pelos principais assuntos do Estratégia Nerd e siga o tema que mais combina com o que você quer descobrir, comparar ou decidir agora.</p>
        </div>
        <div class="grid md:grid-cols-2 xl:grid-cols-4 gap-5">
          <?php foreach ($topicLinks as $topic): ?>
            <a href="<?= htmlspecialchars((string) ($topic['url'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="site-value-box site-value-purple block group">
              <h3 class="flex items-center justify-between gap-3">
                <span><?= htmlspecialchars((string) ($topic['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <span class="text-cyan-300 text-sm"><?= (int) ($topic['count'] ?? 0) ?> posts</span>
              </h3>
              <p><?= htmlspecialchars((string) ($topic['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
              <span class="mt-4 inline-flex items-center text-cyan-300 font-semibold group-hover:text-cyan-200 transition-colors">
                <?= htmlspecialchars((string) ($topic['cta'] ?? 'Ver posts'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
              </span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>