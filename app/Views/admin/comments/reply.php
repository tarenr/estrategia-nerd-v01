<?php
declare(strict_types=1);

use App\Support\Csrf;

$comment = $comment ?? [];
$replyTarget = $reply_target ?? [];
$form = $form ?? ['id' => 0, 'resposta' => ''];
$errors = $errors ?? [];
$returnTo = (string) ($return_to ?? url('/admin/comentarios'));
$fieldError = static fn (string $key): string => (string) ($errors[$key] ?? '');
?>

<div class="max-w-5xl mx-auto px-4 py-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Responder Comentario</h1>
      <div class="admin-page-subtitle">A resposta sera criada como comentario-filho aprovado e passara a contar como resposta real.</div>
    </div>

    <div class="admin-page-actions">
      <a href="<?= htmlspecialchars($returnTo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-btn admin-btn-secondary">Voltar para comentarios</a>
    </div>
  </div>

  <div class="space-y-6">
    <?php if ($errors !== []): ?>
      <section class="admin-panel border border-rose-500/30">
        <div class="text-sm font-bold text-rose-300">Ajustes necessarios</div>
        <div class="mt-2 text-sm text-rose-100 space-y-1"><?php foreach ($errors as $message): ?><div><?= htmlspecialchars((string) $message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endforeach; ?></div>
      </section>
    <?php endif; ?>

    <section class="admin-panel">
      <div class="flex flex-wrap items-center gap-2 mb-4">
        <span class="admin-chip">Comentario #<?= (int) ($comment['id'] ?? 0) ?></span>
        <span class="admin-chip">Post #<?= (int) ($comment['post_id'] ?? 0) ?></span>
        <span class="admin-chip">Status: <?= htmlspecialchars((string) ($comment['status'] ?? 'pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
        <?php if ((int) ($comment['parent_id'] ?? 0) > 0): ?>
          <span class="admin-chip">Thread #<?= (int) ($comment['parent_id'] ?? 0) ?></span>
        <?php endif; ?>
      </div>
      <h2 class="font-orbitron text-xl font-black text-white"><?= htmlspecialchars((string) ($comment['nome'] ?? 'Anonimo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
      <div class="mt-1 text-sm text-slate-400"><?= htmlspecialchars((string) ($comment['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <div class="mt-4 rounded-2xl border border-slate-800 bg-slate-900/60 p-4 text-sm leading-relaxed text-slate-200 whitespace-pre-line"><?= htmlspecialchars((string) ($comment['comentario'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>

      <?php if ((int) ($comment['parent_id'] ?? 0) > 0): ?>
        <div class="mt-5 rounded-2xl border border-cyan-500/20 bg-cyan-500/5 p-4">
          <div class="text-xs font-bold uppercase tracking-[0.18em] text-cyan-300">Voce esta respondendo uma resposta</div>
          <div class="mt-2 text-sm text-slate-300">
            Esta nova mensagem sera vinculada diretamente ao comentario #<?= (int) ($comment['id'] ?? 0) ?>, ficando um nivel a frente no post publico.
          </div>
        </div>
      <?php endif; ?>
    </section>

    <form method="POST" action="<?= url('/admin/responder-comentario?id=' . (int) ($comment['id'] ?? 0)) ?>" class="space-y-6" novalidate>
      <?= Csrf::field() ?>
      <input type="hidden" name="id" value="<?= (int) ($comment['id'] ?? 0) ?>">
      <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

      <section class="admin-panel">
        <label for="resposta" class="block text-sm font-bold text-slate-200 mb-2">Resposta oficial</label>
        <textarea id="resposta" name="resposta" rows="8" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Escreva a resposta oficial da equipe...\n\nEx.: Obrigado pelo comentario. Atualizamos o post com essa observacao."><?= htmlspecialchars((string) ($form['resposta'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
        <?php if ($fieldError('resposta') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('resposta'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
        <div class="mt-3 text-xs text-slate-400">A resposta sera publicada como resposta oficial na mesma thread do comentario principal.</div>
      </section>

      <section class="admin-panel flex flex-wrap items-center justify-between gap-3">
        <div class="text-xs text-slate-400">Depois de salvar, a central de comentarios passara a mostrar este item como respondido.</div>
        <div class="flex flex-wrap gap-2">
          <a href="<?= htmlspecialchars($returnTo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-btn admin-btn-secondary">Cancelar</a>
          <button type="submit" class="admin-btn admin-btn-primary">Publicar resposta</button>
        </div>
      </section>
    </form>
  </div>
</div>
