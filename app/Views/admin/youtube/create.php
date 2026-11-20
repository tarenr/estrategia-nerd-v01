<?php
declare(strict_types=1);
$esc = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$old = is_array($old ?? null) ? $old : [];
?>
<div class="max-w-4xl mx-auto px-4 py-6">
  <div class="admin-page-header"><div class="admin-page-heading"><h1 class="admin-page-title"><i class="fa-brands fa-youtube text-red-400"></i> Novo vídeo</h1><div class="admin-page-subtitle">O arquivo será enviado ao YouTube 24 horas antes da publicação.</div></div><a href="<?= url('/admin/youtube') ?>" class="admin-btn admin-btn-secondary">Voltar</a></div>
  <?php if ($account === null): ?><div class="admin-panel mt-5 border border-amber-500/30 text-amber-200">Conecte um canal do YouTube antes de cadastrar vídeos.</div><?php endif; ?>
  <?php if (!empty($error)): ?><div class="admin-panel mt-5 border border-rose-500/30 text-rose-200" role="alert"><?= $esc($error) ?></div><?php endif; ?>
  <form method="post" action="<?= url('/admin/youtube/posts/criar') ?>" enctype="multipart/form-data" class="admin-panel mt-6 space-y-5" novalidate>
    <input type="hidden" name="_csrf_token" value="<?= $esc($csrf_token ?? '') ?>">
    <div><label class="admin-filter-label" for="yt-title">Título</label><input id="yt-title" name="title" maxlength="100" required class="nerd-input admin-filter-control w-full" value="<?= $esc($old['title'] ?? '') ?>"></div>
    <div><label class="admin-filter-label" for="yt-description">Descrição</label><textarea id="yt-description" name="description" maxlength="5000" rows="7" class="nerd-input admin-filter-control w-full"><?= $esc($old['description'] ?? '') ?></textarea></div>
    <div class="grid md:grid-cols-2 gap-4"><div><label class="admin-filter-label" for="yt-tags">Tags, separadas por vírgula</label><input id="yt-tags" name="tags" class="nerd-input admin-filter-control w-full" value="<?= $esc($old['tags'] ?? '') ?>"></div><div><label class="admin-filter-label" for="yt-category">Categoria</label><select id="yt-category" name="category_id" class="nerd-input admin-filter-control w-full"><option value="28">Ciência e tecnologia</option><option value="24">Entretenimento</option><option value="20">Jogos</option></select></div></div>
    <div><label class="admin-filter-label" for="yt-scheduled">Data e hora de publicação</label><input id="yt-scheduled" name="scheduled_at" type="datetime-local" required class="nerd-input admin-filter-control w-full" value="<?= $esc($old['scheduled_at'] ?? '') ?>"><p class="text-xs text-slate-500 mt-1">Fuso: America/Sao_Paulo. O YouTube recebe o vídeo como privado e publica no horário escolhido.</p></div>
    <div class="grid md:grid-cols-2 gap-4"><div><label class="admin-filter-label" for="yt-video">Vídeo MP4</label><input id="yt-video" name="video" type="file" accept="video/mp4" required class="nerd-input admin-filter-control w-full"></div><div><label class="admin-filter-label" for="yt-thumbnail">Miniatura JPG/PNG (opcional)</label><input id="yt-thumbnail" name="thumbnail" type="file" accept="image/jpeg,image/png" class="nerd-input admin-filter-control w-full"></div></div>
    <label class="flex gap-2 text-sm text-slate-200"><input type="checkbox" name="made_for_kids" value="1" class="accent-cyan-500"><span>Conteúdo feito para crianças</span></label>
    <label class="flex gap-2 text-sm text-slate-200"><input type="checkbox" name="contains_synthetic_media" value="1" class="accent-cyan-500"><span>Contém mídia sintética realista</span></label>
    <div class="pt-2"><button type="submit" class="admin-btn admin-btn-primary"<?= $account === null ? ' disabled' : '' ?>><i class="fa-solid fa-calendar-check"></i> Adicionar à fila</button></div>
  </form>
</div>
