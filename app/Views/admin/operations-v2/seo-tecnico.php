<?php

declare(strict_types=1);

$module = is_array($module ?? null) ? $module : [];
$searchConsoleTools = is_array($search_console_tools ?? null) ? $search_console_tools : [];
?>

<section class="space-y-6 operations-v2-page">
  <div class="ops-v2-hero">
    <div>
      <p class="ops-v2-eyebrow">Central Operacional</p>
      <h1 class="ops-v2-title"><?= htmlspecialchars((string) ($module['label'] ?? 'SEO Tecnico'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
    </div>
    <a href="<?= url('/admin/central-operacional-v2') ?>" class="ops-v2-back">
      <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
      <span>Voltar</span>
    </a>
  </div>

  <?= \App\Support\View::fragment('site/search-console-monitor', $searchConsoleTools) ?>
</section>
