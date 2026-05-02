<?php

declare(strict_types=1);

use App\Support\View;

$module = is_array($module ?? null) ? $module : [];
$moduleKey = (string) ($module_key ?? '');
$label = (string) ($module['label'] ?? 'Central Operacional V2');
$description = (string) ($module['description'] ?? 'Página de leitura da Central Operacional V2.');

$notes = [
    'backup-sistemico' => [
        'Escopo: arquivos técnicos do sistema, pacote técnico e restore sistêmico.',
        'Nesta fase, a tela é apenas uma área de leitura e organização.',
        'Nenhuma rotina de backup ou restore é executada aqui.',
    ],
    'backup-editorial' => [
        'Escopo: banco, uploads, mídias e sincronização editorial.',
        'Nesta fase, a tela é apenas uma área de leitura e organização.',
        'Nenhuma rotina de conteúdo, sync ou restore é executada aqui.',
    ],
    'observabilidade' => [
        'Escopo: logs, histórico, alertas, status de integrações e sinais operacionais.',
        'Testes automatizados ficam fora da Central Operacional, em menu próprio.',
        'Health Check continua no menu de Sistema.',
    ],
];

$moduleNotes = $notes[$moduleKey] ?? ['Módulo V2 em preparação.'];
?>
<section class="space-y-6">
  <?php View::component('admin/v2/page-header', [
      'eyebrow' => 'Central Operacional V2',
      'title' => $label,
      'description' => $description,
      'actions' => [
          [
              'href' => url('/admin/central-operacional-v2'),
              'label' => 'Voltar',
              'icon' => 'fa-solid fa-arrow-left',
              'variant' => 'secondary',
          ],
      ],
  ]); ?>

  <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
    <div class="flex flex-wrap items-center gap-3">
      <h2 class="font-orbitron text-lg font-black text-white">Leitura Inicial</h2>
      <?php View::component('admin/v2/status-badge', [
          'label' => 'Sem Ações',
          'tone' => 'neutral',
      ]); ?>
    </div>

    <div class="mt-5 divide-y divide-slate-800 overflow-hidden rounded-2xl border border-slate-800 bg-slate-950/60">
      <?php foreach ($moduleNotes as $note): ?>
        <div class="p-4 text-sm leading-6 text-slate-300">
          <?= htmlspecialchars($note, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</section>
