<?php
declare(strict_types=1);

$summary = $summary ?? ['total' => 0, 'images' => 0, 'others' => 0, 'directories' => 0, 'size_label' => '0 B'];
$cards = [
    ['label' => 'Arquivos', 'value' => (int) ($summary['total'] ?? 0)],
    ['label' => 'Imagens', 'value' => (int) ($summary['images'] ?? 0)],
    ['label' => 'Pastas', 'value' => (int) ($summary['directories'] ?? 0)],
    ['label' => 'Espaco usado', 'value' => (string) ($summary['size_label'] ?? '0 B')],
];
?>

<section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
  <?php foreach ($cards as $card): ?>
    <article class="stat-card">
      <div class="text-sm text-slate-400"><?= htmlspecialchars((string) $card['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <div class="mt-3 text-4xl font-orbitron font-black text-white"><?= htmlspecialchars((string) $card['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    </article>
  <?php endforeach; ?>
</section>