<?php

declare(strict_types=1);

use App\Support\View;

$overview = is_array($overview ?? null) ? $overview : [];
$facts = is_array($overview['facts'] ?? null) ? $overview['facts'] : [];
?>
<style>
  @keyframes admin-v2-fade-in {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
  }
</style>

<section class="space-y-6" style="animation: admin-v2-fade-in .22s ease-out both;">
  <?php View::component('admin/v2/page-header', [
      'eyebrow' => 'Operacional',
      'title' => 'Central Operacional',
      'description' => '',
  ]); ?>

  <?= View::fragment('admin/operations-v2/panel', ['overview' => $overview]) ?>
</section>
