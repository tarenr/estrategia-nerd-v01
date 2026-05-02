<?php

declare(strict_types=1);

use App\Support\View;

$module = is_array($module ?? null) ? $module : [];
$backupTools = is_array($backup_tools ?? null) ? $backup_tools : [];
$backupTools['admin_embed'] = true;
$backupTools['embed_mode'] = true;
?>
<section class="space-y-6">
  <?php View::component('admin/v2/page-header', [
      'eyebrow' => 'Central Operacional V2',
      'title' => (string) ($module['label'] ?? 'Backup Sistêmico e Restore'),
      'description' => '',
      'actions' => [
          [
              'href' => url('/admin/central-operacional-v2'),
              'label' => 'Voltar',
              'icon' => 'fa-solid fa-arrow-left',
              'variant' => 'secondary',
          ],
      ],
  ]); ?>

  <?= View::fragment('site/backup-tools', $backupTools) ?>
</section>
