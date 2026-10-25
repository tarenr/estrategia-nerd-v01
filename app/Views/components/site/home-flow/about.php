<?php
// FEAT-015, Parte 2: Sobre da prévia em card de vidro, para o feixe atravessar
// (por trás do texto e pelo vão central entre os pilares). Mesmos dados do
// about-preview.php oficial, que não muda.
if (!site_section_visible_on_home('sobre')) {
    return;
}

$aboutMark = (string) ($about_mark ?? '');
$bioTitle = (string) ($bio_title ?? 'Estrategia Nerd');
$homeIntro = $home_intro ?? [];
$topicLinks = $topic_links ?? [];
$introLinks = (array) ($homeIntro['context_links'] ?? []);
$paragraphs = array_values(array_filter(array_map('strval', (array) ($homeIntro['body'] ?? [])), static fn (string $p): bool => trim($p) !== ''));
$half = (int) ceil(count($paragraphs) / 2);
$columns = [array_slice($paragraphs, 0, $half), array_slice($paragraphs, $half)];
$pillars = array_values((array) ($homeIntro['pillars'] ?? []));
?>

<section id="sobre" class="site-about-section en-flow-about relative" data-flow-about>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="en-flow-about__card site-preview-about-piece" data-flow-about-card data-reveal>
      <div class="en-flow-about__glass">
      <div class="en-flow-about__head">
        <?php if ($aboutMark !== ''): ?>
          <img src="<?= htmlspecialchars($aboutMark, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="" role="presentation" class="en-flow-about__mark" width="96" height="96">
        <?php endif; ?>
        <div>
          <h2 class="en-flow-about__title font-orbitron">
            <?= htmlspecialchars((string) ($homeIntro['title'] ?? 'O que voce encontra no Estrategia Nerd'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
          </h2>
          <?php if (trim((string) ($homeIntro['lead'] ?? '')) !== ''): ?>
            <p class="en-flow-about__lead"><?= htmlspecialchars((string) $homeIntro['lead'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($paragraphs !== []): ?>
        <div class="en-flow-about__body">
          <?php foreach ($columns as $column): ?>
            <?php if ($column !== []): ?>
              <div class="en-flow-about__col">
                <?php foreach ($column as $paragraph): ?>
                  <p><?= htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($introLinks !== []): ?>
        <p class="en-flow-about__links">
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

      <?php if ($pillars !== []): ?>
        <div class="en-flow-about__pillars" data-flow-pillars>
          <?php foreach ($pillars as $index => $pillar): ?>
            <div class="site-value-box site-value-cyan site-preview-about-piece site-preview-about-card en-flow-about__pillar" style="--reveal-delay: <?= (int) ($index * 80) ?>ms" data-flow-pillar data-reveal>
              <h3><?= htmlspecialchars((string) ($pillar['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
              <p><?= htmlspecialchars((string) ($pillar['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($topicLinks !== []): ?>
      <div class="mt-16 site-preview-topics-band">
        <div class="site-preview-topics-inner">
          <div class="max-w-3xl mb-8 site-preview-about-piece site-preview-about-topics-head" data-reveal>
            <h2 class="font-orbitron text-3xl md:text-4xl font-bold text-white">Temas centrais do portal</h2>
            <p class="text-slate-300 text-lg mt-3">Navegue pelos principais assuntos do Estrategia Nerd e siga o tema que mais combina com o que voce quer descobrir, comparar ou decidir agora.</p>
          </div>
          <div class="grid md:grid-cols-2 xl:grid-cols-4 gap-5">
            <?php foreach ($topicLinks as $index => $topic): ?>
              <a href="<?= htmlspecialchars((string) ($topic['url'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="site-value-box site-value-purple block group site-preview-about-piece site-preview-about-card" style="--reveal-delay: <?= (int) ($index * 70) ?>ms" data-reveal>
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
      </div>
    <?php endif; ?>
  </div>
</section>
