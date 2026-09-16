<?php
declare(strict_types=1);

use App\Support\Csrf;

$subscriber = $subscriber ?? [];
$returnTo = (string) ($returnTo ?? url('/admin/newsletter'));
$status = (string) ($subscriber['status'] ?? 'ativo');
$statusClass = match ($status) {
    'ativo' => 'border-emerald-500/30 text-emerald-300 bg-emerald-500/10',
    'inativo' => 'border-amber-500/30 text-amber-300 bg-amber-500/10',
    default => 'border-rose-500/30 text-rose-300 bg-rose-500/10',
};
$requiresProductionConfirmation = (bool) ($requires_production_confirmation ?? false);
$isRemoteTarget = (bool) ($is_remote_target ?? false);
$targetEnvironmentLabel = (string) ($target_environment_label ?? '');
$errors = is_array($errors ?? null) ? $errors : [];
?>

<div class="max-w-4xl mx-auto px-4 py-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Excluir Inscrito</h1>
      <div class="admin-page-subtitle">Confirme a remocao permanente deste contato da base da newsletter.</div>
    </div>

    <div class="admin-page-actions">
      <?php if ($isRemoteTarget): ?>
        <div class="admin-chip border-cyan-500/30 text-cyan-200">Ambiente alvo: <?= htmlspecialchars($targetEnvironmentLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <?php endif; ?>
      <a href="<?= htmlspecialchars($returnTo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-btn admin-btn-secondary">Voltar</a>
    </div>
  </div>

  <section class="admin-panel space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-[1.3fr_0.7fr] gap-6">
      <div class="rounded-2xl border border-rose-500/20 bg-slate-950/50 p-6">
        <div class="flex flex-wrap items-center gap-3 mb-4">
          <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border <?= $statusClass ?>"><?= htmlspecialchars(strtoupper($status), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
          <span class="text-xs text-slate-400">ID #<?= (int) ($subscriber['id'] ?? 0) ?></span>
        </div>

        <h2 class="font-orbitron text-2xl font-black text-white"><?= htmlspecialchars((string) ($subscriber['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
        <div class="mt-3 text-sm text-slate-300"><?= htmlspecialchars((string) (($subscriber['nome'] ?? '') !== '' ? $subscriber['nome'] : 'Sem nome informado'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>

        <dl class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
          <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
            <dt class="text-slate-400 text-xs uppercase tracking-[0.2em]">Data de cadastro</dt>
            <dd class="mt-2 text-white font-bold"><?= htmlspecialchars((string) ($subscriber['data_cadastro'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
          </div>
          <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
            <dt class="text-slate-400 text-xs uppercase tracking-[0.2em]">IP</dt>
            <dd class="mt-2 text-white font-bold"><?= htmlspecialchars((string) (($subscriber['ip'] ?? '') !== '' ? $subscriber['ip'] : '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
          </div>
        </dl>
      </div>

      <aside class="rounded-2xl border border-rose-500/20 bg-rose-950/10 p-6">
        <div class="font-orbitron text-lg text-rose-200 font-black">Acao irreversivel</div>
        <p class="mt-3 text-sm text-slate-300 leading-7">Ao excluir este inscrito, o cadastro sera removido permanentemente da tabela de newsletter. Use essa acao apenas quando quiser limpar a base de vez.</p>
      </aside>
    </div>

    <?php if ($requiresProductionConfirmation): ?>
      <div class="rounded-2xl border border-amber-500/20 bg-amber-500/10 px-4 py-4 space-y-2">
        <div class="text-sm font-bold text-amber-200">Confirmacao obrigatoria para producao</div>
        <div class="text-sm text-slate-300">Digite <strong>PRODUCAO</strong> para confirmar esta exclusao no ambiente de producao.</div>
        <?php if (!empty($errors['production_confirmation'])): ?>
          <div class="text-xs text-rose-300"><?= htmlspecialchars((string) $errors['production_confirmation'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= url('/admin/excluir-inscrito') ?>" class="flex flex-wrap items-center justify-end gap-3">
      <?= Csrf::field() ?>
      <input type="hidden" name="id" value="<?= (int) ($subscriber['id'] ?? 0) ?>">
      <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <?php if ($requiresProductionConfirmation): ?>
        <input type="text" name="production_confirmation" value="" class="nerd-input px-4 py-3 rounded-xl" placeholder="Digite PRODUCAO" autocomplete="off">
      <?php endif; ?>
      <a href="<?= htmlspecialchars($returnTo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-btn admin-btn-secondary">Cancelar</a>
      <button type="submit" class="admin-btn admin-btn-primary" style="background:linear-gradient(135deg,#ef4444,#dc2626);border-color:rgba(248,113,113,.35);box-shadow:none;">Excluir permanentemente</button>
    </form>
  </section>
</div>
