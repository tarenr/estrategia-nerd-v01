<?php
declare(strict_types=1);

use App\Support\Csrf;

$form = $form ?? [];
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$postId     = (int) ($form['id'] ?? 0);
$enabled    = (int) ($form['ig_crosspost'] ?? 0) === 1;
$token      = (string) ($form['ig_crosspost_token'] ?? '');
$formUid    = (string) ($form['ig_crosspost_form_uid'] ?? '');
if ($formUid === '') {
    $formUid = bin2hex(random_bytes(12));
}
$legenda    = (string) ($form['ig_crosspost_legenda'] ?? '');
$hashtags   = (string) ($form['ig_crosspost_hashtags'] ?? '');
$previewRel = (string) ($form['ig_crosspost_preview_url'] ?? '');
$linked     = is_array($form['_ig_linked'] ?? null) ? $form['_ig_linked'] : null;
$notice     = is_array($form['_ig_notice'] ?? null) ? $form['_ig_notice'] : null;
$editable   = $linked === null || ($linked['_editable'] ?? false) === true;

$statusLabels = [
    'rascunho'   => ['Rascunho', 'text-slate-200 border-slate-500/40'],
    'agendado'   => ['Agendado', 'text-amber-200 border-amber-500/40'],
    'publicando' => ['Publicando', 'text-cyan-200 border-cyan-500/40'],
    'publicado'  => ['Publicado', 'text-emerald-200 border-emerald-500/40'],
    'erro'       => ['Erro', 'text-rose-200 border-rose-500/40'],
];
?>

<section id="igCrosspost" class="admin-panel space-y-4"
  data-endpoint="<?= $e(url('/admin/instagram/api/crosspost-preview')) ?>"
  data-csrf="<?= $e(Csrf::token()) ?>"
  data-post-id="<?= $postId ?>"
  data-base-url="<?= $e(url('/')) ?>">

  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h2 class="font-orbitron text-lg font-black text-slate-100"><i class="fa-brands fa-instagram mr-2 text-pink-400"></i>Instagram</h2>
      <p class="text-xs text-slate-400 mt-1">Cria ou atualiza um rascunho no Instagram ao salvar o post. Nada e publicado automaticamente.</p>
    </div>
    <?php if ($editable): ?>
      <label class="inline-flex items-center gap-2 text-sm font-bold text-slate-200 cursor-pointer">
        <input type="checkbox" id="igCrosspostToggle" name="ig_crosspost" value="1" class="w-4 h-4 accent-pink-500"<?= $enabled ? ' checked' : '' ?>>
        <?= $linked !== null ? 'Atualizar rascunho no Instagram' : 'Criar rascunho no Instagram' ?>
      </label>
    <?php endif; ?>
  </div>

  <?php if ($notice !== null): ?>
    <?php $ok = (string) ($notice['status'] ?? '') === 'ok'; ?>
    <div class="rounded-xl border px-4 py-3 text-sm <?= $ok ? 'border-emerald-500/30 text-emerald-200' : 'border-amber-500/40 text-amber-100' ?>">
      <?php if ($ok): ?>
        <?= $e($notice['message'] ?? 'Rascunho do Instagram salvo.') ?>
      <?php else: ?>
        <strong>Blog salvo; rascunho do Instagram nao foi atualizado:</strong> <?= $e($notice['message'] ?? '') ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($linked !== null): ?>
    <?php
      $st = (string) ($linked['status'] ?? 'rascunho');
      [$stLabel, $stClass] = $statusLabels[$st] ?? [$st, 'text-slate-200 border-slate-500/40'];
      $media = (string) ($linked['_media'] ?? '');
    ?>
    <div class="flex flex-wrap gap-4 items-start rounded-xl border border-slate-700/60 p-3" data-ig-linked>
      <?php if ($media !== ''): ?>
        <img src="<?= $e(url('/' . ltrim($media, '/'))) ?>" alt="Imagem do post do Instagram vinculado" class="w-28 h-28 object-cover rounded-lg border border-slate-700">
      <?php endif; ?>
      <div class="flex-1 min-w-[220px] space-y-2">
        <div class="flex flex-wrap items-center gap-2 text-xs">
          <span class="rounded-full border px-2 py-0.5 font-bold <?= $stClass ?>"><?= $e($stLabel) ?></span>
          <span class="text-slate-400">Post do Instagram #<?= (int) ($linked['id'] ?? 0) ?></span>
          <?php if (!empty($linked['permalink'])): ?>
            <a href="<?= $e($linked['permalink']) ?>" target="_blank" rel="noopener" class="text-cyan-300 underline">Ver no Instagram</a>
          <?php endif; ?>
          <a href="<?= $e(url('/admin/instagram/posts/' . (int) ($linked['id'] ?? 0) . '/editar')) ?>" class="text-cyan-300 underline">Abrir no modulo Instagram</a>
        </div>
        <p class="text-sm text-slate-300 whitespace-pre-line line-clamp-4"><?= $e($linked['legenda'] ?? '') ?></p>
        <?php if (!$editable): ?>
          <p class="text-xs text-amber-200">Somente leitura: posts agendados, em publicacao ou publicados nao sao alterados pelo blog.</p>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($editable): ?>
    <input type="hidden" name="ig_crosspost_token" id="igToken" value="<?= $e($token) ?>">
    <input type="hidden" name="ig_crosspost_form_uid" id="igFormUid" value="<?= $e($formUid) ?>">
    <input type="hidden" name="ig_crosspost_preview_url" id="igPreviewRel" value="<?= $e($previewRel) ?>">

    <div id="igCrosspostBody" class="grid gap-5 lg:grid-cols-2<?= $enabled ? '' : ' hidden' ?>">
      <div class="space-y-4">
        <div class="flex flex-wrap gap-2">
          <button type="button" id="igBtnPreview" class="admin-btn admin-btn-secondary"><i class="fa-solid fa-wand-magic-sparkles mr-1"></i>Gerar previa</button>
          <button type="button" id="igBtnCaption" class="admin-btn admin-btn-secondary"><i class="fa-solid fa-rotate mr-1"></i>Gerar outra legenda</button>
        </div>
        <div id="igStatus" class="text-xs text-slate-400" role="status" aria-live="polite"></div>
        <div id="igStale" class="hidden rounded-lg border border-amber-500/40 px-3 py-2 text-xs text-amber-100">A capa mudou depois da previa. Gere a previa novamente antes de salvar.</div>

        <div>
          <label for="igArte" class="block text-sm font-bold text-slate-200 mb-2">Arte dedicada (opcional, 1:1 ou 4:5)</label>
          <input type="file" id="igArte" accept="image/jpeg,image/png,image/webp" class="block w-full text-xs text-slate-300">
          <p class="text-xs text-slate-500 mt-1">Sem arte dedicada, a capa do post vira um canvas 1080x1080 sem cortes.</p>
        </div>

        <div>
          <label for="igLegenda" class="block text-sm font-bold text-slate-200 mb-2">Legenda</label>
          <textarea id="igLegenda" name="ig_crosspost_legenda" rows="8" class="nerd-input w-full px-4 py-3 rounded-xl text-sm"><?= $e($legenda) ?></textarea>
        </div>
        <div>
          <label for="igHashtags" class="block text-sm font-bold text-slate-200 mb-2">Hashtags</label>
          <input id="igHashtags" name="ig_crosspost_hashtags" type="text" value="<?= $e($hashtags) ?>" class="nerd-input w-full px-4 py-3 rounded-xl text-sm" placeholder="#EstrategiaNerd #Games">
        </div>
        <div class="flex gap-4 text-xs text-slate-400">
          <span>Caracteres: <strong id="igCountChars">0</strong>/2200</span>
          <span>Hashtags: <strong id="igCountTags">0</strong>/30</span>
        </div>
      </div>

      <div>
        <div class="mx-auto max-w-sm rounded-2xl border border-slate-700 bg-black text-slate-100 overflow-hidden" aria-label="Previa do post no Instagram">
          <div class="flex items-center gap-2 px-3 py-2">
            <span class="w-8 h-8 rounded-full bg-gradient-to-tr from-amber-400 via-pink-500 to-purple-600 flex items-center justify-center text-[10px] font-black">EN</span>
            <span class="text-sm font-bold">estrategia_nerd</span>
          </div>
          <div class="aspect-square bg-slate-900 flex items-center justify-center">
            <img id="igPreviewImg" alt="Previa da imagem do Instagram" class="w-full h-full object-cover<?= $previewRel === '' ? ' hidden' : '' ?>" src="<?= $previewRel !== '' ? $e(url('/' . $previewRel)) : '' ?>">
            <span id="igPreviewEmpty" class="text-xs text-slate-500<?= $previewRel !== '' ? ' hidden' : '' ?>">Gere a previa para ver a imagem</span>
          </div>
          <div class="px-3 py-2 text-lg space-x-3"><i class="fa-regular fa-heart"></i><i class="fa-regular fa-comment"></i><i class="fa-regular fa-paper-plane"></i></div>
          <div class="px-3 pb-3 text-sm">
            <span class="font-bold mr-1">estrategia_nerd</span><span id="igCaptionText" class="whitespace-pre-line"></span>
            <button type="button" id="igMore" class="text-slate-400 hidden">mais</button>
            <div id="igCaptionTags" class="mt-1 text-sky-400 break-words"></div>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</section>

<?php if ($editable): ?>
<script>
(function () {
  var root = document.getElementById('igCrosspost');
  if (!root) return;
  var $ = function (id) { return document.getElementById(id); };
  var toggle = $('igCrosspostToggle'), body = $('igCrosspostBody');
  var legenda = $('igLegenda'), hashtags = $('igHashtags');
  var version = 0, coverSignature = null, expanded = false;

  function currentCoverSignature() {
    var cover = $('imagem_capa'), upload = $('imagem_capa_upload');
    var file = upload && upload.files && upload.files[0];
    return (cover ? cover.value : '') + '|' + (file ? file.name + ':' + file.size + ':' + file.lastModified : '');
  }

  function setStatus(text, isError) {
    var el = $('igStatus');
    el.textContent = text || '';
    el.className = 'text-xs ' + (isError ? 'text-rose-300' : 'text-slate-400');
  }

  function tagsList() {
    return (hashtags.value || '').split(/[\s,]+/).filter(Boolean).map(function (t) { return '#' + t.replace(/^#+/, ''); });
  }

  function render() {
    var text = legenda.value || '';
    var tags = tagsList();
    var full = tags.length ? text + '\n\n' + tags.join(' ') : text;
    $('igCountChars').textContent = String(Array.from(full).length);
    $('igCountTags').textContent = String(tags.length);
    $('igCountChars').className = Array.from(full).length > 2200 ? 'text-rose-300' : '';
    $('igCountTags').className = tags.length > 30 ? 'text-rose-300' : '';
    var short = !expanded && text.length > 125;
    $('igCaptionText').textContent = short ? text.slice(0, 125) + '… ' : text;
    $('igMore').classList.toggle('hidden', !short);
    $('igCaptionTags').textContent = tags.join(' ');
  }

  function formData(mode) {
    var fd = new FormData();
    fd.append('_csrf_token', root.dataset.csrf);
    fd.append('post_id', root.dataset.postId);
    fd.append('form_uid', $('igFormUid').value);
    fd.append('version', String(version));
    fd.append('modo', mode);
    fd.append('titulo', ($('titulo') || {}).value || '');
    fd.append('resumo', ($('resumo') || {}).value || '');
    var cat = $('categoria_post_id');
    fd.append('categoria', cat && cat.selectedIndex > 0 ? cat.options[cat.selectedIndex].text.trim() : '');
    fd.append('imagem_capa', ($('imagem_capa') || {}).value || '');
    var arte = $('igArte').files && $('igArte').files[0];
    var upload = $('imagem_capa_upload');
    if (arte) fd.append('arte_dedicada', arte);
    else if (upload && upload.files && upload.files[0]) fd.append('imagem_capa_upload', upload.files[0]);
    return fd;
  }

  function request(mode) {
    version += 1;
    var myVersion = version;
    var signature = currentCoverSignature();
    setStatus(mode === 'legenda' ? 'Gerando nova legenda...' : 'Gerando previa e legenda...', false);
    $('igBtnPreview').disabled = true; $('igBtnCaption').disabled = true;

    fetch(root.dataset.endpoint, { method: 'POST', body: formData(mode), credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Resposta invalida do servidor.' }; }); })
      .then(function (data) {
        if (myVersion !== version) return;
        if (!data || data.ok !== true) { setStatus((data && data.error) || 'Nao foi possivel gerar a previa.', true); return; }
        legenda.value = data.caption || '';
        hashtags.value = (data.hashtags || []).join(' ');
        expanded = false;
        if (mode !== 'legenda') {
          $('igToken').value = data.token || '';
          $('igPreviewRel').value = data.preview_url || '';
          var img = $('igPreviewImg');
          img.src = root.dataset.baseUrl.replace(/\/$/, '') + '/' + data.preview_url;
          img.classList.remove('hidden'); $('igPreviewEmpty').classList.add('hidden');
          coverSignature = data.kind === 'capa' ? signature : null;
          $('igStale').classList.add('hidden');
        }
        setStatus(data.source === 'ai' ? 'Legenda gerada pela IA. Revise antes de salvar.' : 'IA indisponivel: legenda basica gerada a partir do titulo e resumo.' + (data.error ? ' (' + data.error + ')' : ''), data.source !== 'ai');
        render();
      })
      .catch(function () { if (myVersion === version) setStatus('Falha de rede ao gerar a previa.', true); })
      .finally(function () { if (myVersion === version) { $('igBtnPreview').disabled = false; $('igBtnCaption').disabled = false; } });
  }

  function checkStale() {
    if (!toggle.checked || coverSignature === null || !$('igToken').value) return;
    if (currentCoverSignature() !== coverSignature) {
      $('igToken').value = '';
      $('igStale').classList.remove('hidden');
    }
  }

  toggle.addEventListener('change', function () {
    body.classList.toggle('hidden', !toggle.checked);
    if (toggle.checked && !$('igToken').value) request('completo');
  });
  $('igBtnPreview').addEventListener('click', function () { request('completo'); });
  $('igBtnCaption').addEventListener('click', function () { request('legenda'); });
  $('igArte').addEventListener('change', function () { if (toggle.checked) request('completo'); });
  $('igMore').addEventListener('click', function () { expanded = true; render(); });
  legenda.addEventListener('input', render);
  hashtags.addEventListener('input', render);
  if ($('igToken').value) coverSignature = currentCoverSignature();
  window.setInterval(checkStale, 1000);
  render();
})();
</script>
<?php endif; ?>
