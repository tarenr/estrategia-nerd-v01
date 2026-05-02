<?php

declare(strict_types=1);

$label = (string) ($label ?? 'Pendente');
$tone = (string) ($tone ?? 'neutral');

$tones = [
    'success' => 'border-emerald-400/25 bg-emerald-500/10 text-emerald-200',
    'warning' => 'border-amber-400/25 bg-amber-500/10 text-amber-200',
    'danger' => 'border-rose-400/30 bg-rose-500/10 text-rose-200',
    'info' => 'border-cyan-400/25 bg-cyan-500/10 text-cyan-200',
    'neutral' => 'border-slate-700 bg-slate-900/80 text-slate-300',
];
$class = $tones[$tone] ?? $tones['neutral'];
?>
<span class="inline-flex items-center rounded-full border px-3.5 py-1.5 text-[11px] font-black uppercase tracking-[0.14em] shadow-[0_0_18px_rgba(15,23,42,0.22)] <?= htmlspecialchars($class, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
  <?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
</span>
