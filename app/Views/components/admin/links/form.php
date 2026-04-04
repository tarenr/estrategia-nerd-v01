<?php
declare(strict_types=1);

use App\Support\Csrf;

$mode = (string) ($mode ?? 'create');
$action = (string) ($action ?? '#');
$submitLabel = (string) ($submitLabel ?? 'Salvar link');
$form = $form ?? [];
$errors = $errors ?? [];
$mediaItems = $media_items ?? [];

$fieldError = static fn (string $key): string => (string) ($errors[$key] ?? '');
$imageValue = trim((string) ($form['imagem'] ?? ''));
$imagePreview = $imageValue !== '' ? (preg_match('~^https?://~i', $imageValue) ? $imageValue : url('/' . ltrim($imageValue, '/'))) : '';
?>

<form method="POST" action="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" enctype="multipart/form-data" class="space-y-6" novalidate>
  <?= Csrf::field() ?>
  <?php if ((int) ($form['id'] ?? 0) > 0): ?>
    <input type="hidden" name="id" value="<?= (int) ($form['id'] ?? 0) ?>">
  <?php endif; ?>

  <?php if ($errors !== []): ?>
    <section class="admin-panel border border-rose-500/30">
      <h2 class="font-orbitron text-lg font-black text-rose-300">Ajustes necessarios</h2>
      <div class="mt-3 text-sm text-rose-100 space-y-1">
        <?php foreach ($errors as $message): ?>
          <div><?= htmlspecialchars((string) $message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="admin-panel">
    <div class="flex items-center justify-between gap-4 flex-wrap mb-5">
      <div>
        <h2 class="font-orbitron text-lg font-black text-white">Dados do link</h2>
        <div class="text-xs text-slate-400 mt-1">Defina o destino, a posicao e como esse link sera exibido na base publica.</div>
      </div>
      <div class="admin-chip">Modo: <?= $mode === 'edit' ? 'edicao' : 'criacao' ?></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <div>
        <label for="titulo" class="block text-sm font-bold text-slate-200 mb-2">Titulo</label>
        <input id="titulo" name="titulo" type="text" value="<?= htmlspecialchars((string) ($form['titulo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Ex.: Canal do YouTube">
        <?php if ($fieldError('titulo') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('titulo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
      </div>

      <div>
        <label for="slug" class="block text-sm font-bold text-slate-200 mb-2">Slug</label>
        <div class="flex gap-2">
          <input id="slug" name="slug" type="text" value="<?= htmlspecialchars((string) ($form['slug'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="canal-youtube">
          <button type="button" class="admin-btn admin-btn-secondary" id="gerarLinkSlug">Gerar</button>
        </div>
        <?php if ($fieldError('slug') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('slug'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
      </div>

      <div class="lg:col-span-2">
        <label for="url" class="block text-sm font-bold text-slate-200 mb-2">URL de destino</label>
        <input id="url" name="url" type="text" value="<?= htmlspecialchars((string) ($form['url'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="https://exemplo.com ou /blog">
        <?php if ($fieldError('url') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('url'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
      </div>

      <div>
        <label for="tipo" class="block text-sm font-bold text-slate-200 mb-2">Tipo</label>
        <select id="tipo" name="tipo" class="nerd-input w-full px-4 py-3 rounded-xl">
          <?php foreach (['afiliado' => 'Afiliado', 'oferta' => 'Oferta', 'conteudo' => 'Conteudo', 'rede_social' => 'Rede social', 'servico' => 'Servico'] as $value => $label): ?>
            <option value="<?= $value ?>" <?= (string) ($form['tipo'] ?? 'conteudo') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label for="status" class="block text-sm font-bold text-slate-200 mb-2">Status</label>
        <select id="status" name="status" class="nerd-input w-full px-4 py-3 rounded-xl">
          <?php foreach (['ativo' => 'Ativo', 'oculto' => 'Oculto', 'expirado' => 'Expirado', 'quebrado' => 'Quebrado'] as $value => $label): ?>
            <option value="<?= $value ?>" <?= (string) ($form['status'] ?? 'ativo') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label for="posicao" class="block text-sm font-bold text-slate-200 mb-2">Posicao</label>
        <input id="posicao" name="posicao" type="number" min="0" value="<?= (int) ($form['posicao'] ?? 0) ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="0">
      </div>

      <div>
        <label for="expira_em" class="block text-sm font-bold text-slate-200 mb-2">Expira em</label>
        <input id="expira_em" name="expira_em" type="datetime-local" value="<?= htmlspecialchars((string) ($form['expira_em'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl">
      </div>

      <div class="lg:col-span-2">
        <label for="descricao" class="block text-sm font-bold text-slate-200 mb-2">Descricao curta</label>
        <textarea id="descricao" name="descricao" rows="3" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Resumo rapido do link, oferta ou destino."><?= htmlspecialchars((string) ($form['descricao'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
        <?php if ($fieldError('descricao') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('descricao'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
      </div>

      <div>
        <label for="cta_curto" class="block text-sm font-bold text-slate-200 mb-2">CTA curto</label>
        <input id="cta_curto" name="cta_curto" type="text" value="<?= htmlspecialchars((string) ($form['cta_curto'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Ex.: Oferta da semana">
        <?php if ($fieldError('cta_curto') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('cta_curto'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
      </div>

      <div>
        <label for="texto_botao" class="block text-sm font-bold text-slate-200 mb-2">Texto do botao</label>
        <input id="texto_botao" name="texto_botao" type="text" value="<?= htmlspecialchars((string) ($form['texto_botao'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Ex.: Ver oferta">
        <?php if ($fieldError('texto_botao') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('texto_botao'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
      </div>

      <div class="lg:col-span-2">
        <label for="selo" class="block text-sm font-bold text-slate-200 mb-2">Selo</label>
        <input id="selo" name="selo" type="text" value="<?= htmlspecialchars((string) ($form['selo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Ex.: Mais clicado, Novo, Destaque da semana">
        <?php if ($fieldError('selo') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('selo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
      </div>

      <div class="lg:col-span-2">
        <label for="observacao_status" class="block text-sm font-bold text-slate-200 mb-2">Observacao de status</label>
        <input id="observacao_status" name="observacao_status" type="text" value="<?= htmlspecialchars((string) ($form['observacao_status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Opcional: contexto sobre expiracao, campanha ou verificacao.">
        <?php if ($fieldError('observacao_status') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('observacao_status'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
      </div>
    </div>

    <div class="mt-5">
      <label class="inline-flex items-center gap-3 text-sm text-slate-200">
        <input type="hidden" name="destaque" value="0">
        <input type="checkbox" name="destaque" value="1" class="rounded border-slate-700 bg-slate-900" <?= (int) ($form['destaque'] ?? 0) === 1 ? 'checked' : '' ?>>
        Marcar como link em destaque
      </label>
    </div>
  </section>

  <section class="admin-panel space-y-5">
    <div>
      <h2 class="font-orbitron text-lg font-black text-white">Imagem do link</h2>
      <div class="text-xs text-slate-400 mt-1">Envie a imagem sem sair da tela ou reaproveite itens recentes da biblioteca.</div>
    </div>

    <div class="rounded-2xl border border-cyan-500/15 bg-slate-950/40 p-4 space-y-4">
      <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
          <h3 class="text-sm font-black text-white">Midia principal</h3>
          <div class="text-xs text-slate-400 mt-1">Ideal para botoes visuais, ofertas e cards destacados na pagina de bio.</div>
        </div>
        <button type="button" class="admin-btn admin-btn-secondary !px-3 !py-2 text-xs" id="limparImagemLink">Limpar</button>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-[220px_1fr] gap-4 items-start">
        <div id="imagem_link_preview_wrap" class="aspect-video rounded-2xl border border-cyan-500/20 bg-slate-900/70 overflow-hidden flex items-center justify-center text-xs text-slate-500">
          <?php if ($imagePreview !== ''): ?>
            <img id="imagem_link_preview" src="<?= htmlspecialchars($imagePreview, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="Preview da imagem do link" class="w-full h-full object-cover">
          <?php else: ?>
            <div id="imagem_link_preview_empty" class="px-4 text-center">Sem imagem selecionada</div>
            <img id="imagem_link_preview" src="" alt="Preview da imagem do link" class="hidden w-full h-full object-cover">
          <?php endif; ?>
        </div>

        <div class="space-y-3">
          <div>
            <label for="imagem" class="block text-sm font-bold text-slate-200 mb-2">URL ou caminho</label>
            <input id="imagem" name="imagem" type="text" value="<?= htmlspecialchars($imageValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="/uploads/links/banner.webp ou https://..." data-link-image-input>
            <?php if ($fieldError('imagem') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('imagem'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
          </div>
          <div>
            <label for="imagem_upload" class="block text-sm font-bold text-slate-200 mb-2">Enviar nova imagem</label>
            <input id="imagem_upload" name="imagem_upload" type="file" accept="image/*" class="block w-full text-sm text-slate-300 file:mr-4 file:rounded-xl file:border-0 file:bg-cyan-500/15 file:px-4 file:py-2 file:font-bold file:text-cyan-100 hover:file:bg-cyan-500/25">
            <div class="text-xs text-slate-500 mt-2">Formatos aceitos: JPG, PNG, WEBP, GIF e SVG.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="rounded-2xl border border-cyan-500/15 bg-slate-950/40 p-4 space-y-4">
      <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
          <h3 class="text-sm font-black text-white">Biblioteca recente</h3>
          <div class="text-xs text-slate-400 mt-1">Escolha uma imagem ja enviada e aplique em um clique.</div>
        </div>
        <a href="<?= htmlspecialchars(url('/admin/midia'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-btn admin-btn-secondary !px-3 !py-2 text-xs">Abrir midia</a>
      </div>

      <?php if ($mediaItems === []): ?>
        <div class="rounded-xl border border-dashed border-slate-700 bg-slate-900/40 px-4 py-6 text-sm text-slate-400">Nenhuma imagem recente encontrada na biblioteca.</div>
      <?php else: ?>
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
          <?php foreach ($mediaItems as $item): ?>
            <?php $itemUrl = (string) ($item['public_url'] ?? ''); ?>
            <?php $itemPath = (string) ($item['relative_path'] ?? ''); ?>
            <button type="button" class="rounded-2xl border border-cyan-500/15 bg-slate-900/50 overflow-hidden text-left hover:border-cyan-400/35 transition" data-link-image-pick="<?= htmlspecialchars($itemPath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
              <div class="aspect-square bg-slate-950/70 overflow-hidden">
                <img src="<?= htmlspecialchars($itemUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($item['name'] ?? 'Midia'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="w-full h-full object-cover">
              </div>
              <div class="p-3">
                <div class="text-[11px] leading-4 text-slate-300 break-all"><?= htmlspecialchars((string) ($item['name'] ?? $itemPath), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </div>
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="admin-panel space-y-5">
    <div>
      <h2 class="font-orbitron text-lg font-black text-white">Variacoes de preview</h2>
      <div class="text-xs text-slate-400 mt-1">Leituras rapidas de como o link pode aparecer na futura pagina de bio.</div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
    <div class="rounded-[28px] border border-cyan-500/15 bg-slate-950/45 p-4">
      <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500 mb-3">Card principal</div>
      <div id="linkCardPreviewImageWrap" class="aspect-[16/9] rounded-2xl border border-cyan-500/15 bg-slate-900/70 overflow-hidden flex items-center justify-center text-xs text-slate-500">
        <?php if ($imagePreview !== ''): ?>
          <img id="linkCardPreviewImage" src="<?= htmlspecialchars($imagePreview, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="Preview do card do link" class="w-full h-full object-cover">
          <div id="linkCardPreviewImageEmpty" class="hidden px-4 text-center">Imagem do link</div>
        <?php else: ?>
          <img id="linkCardPreviewImage" src="" alt="Preview do card do link" class="hidden w-full h-full object-cover">
          <div id="linkCardPreviewImageEmpty" class="px-4 text-center">Imagem do link</div>
        <?php endif; ?>
      </div>

      <div class="mt-4 flex items-center gap-2 flex-wrap">
        <span id="linkCardPreviewType" class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold border border-slate-500/30 text-slate-100 bg-slate-500/10">
          <i id="linkCardPreviewTypeIcon" class="fa-solid fa-link text-slate-300"></i>
          <span id="linkCardPreviewTypeLabel">Conteudo</span>
        </span>
        <span id="linkCardPreviewDestaque" class="admin-chip <?= (int) ($form['destaque'] ?? 0) === 1 ? '' : 'hidden' ?>" style="border-color:rgba(168,85,247,.30);color:#d8b4fe;background:rgba(168,85,247,.12);">Destaque</span>
      </div>

      <div class="mt-4">
        <div id="linkCardPreviewTitle" class="font-orbitron text-xl font-black text-white">Titulo do link</div>
        <div id="linkCardPreviewDescription" class="mt-2 text-sm leading-6 text-slate-300">Descricao curta do link para a pagina de bio.</div>
        <div id="linkCardPreviewUrl" class="mt-3 text-xs font-bold text-cyan-300 break-all">https://exemplo.com</div>
      </div>

      <div class="mt-5">
        <button type="button" id="linkCardPreviewButton" class="inline-flex items-center justify-center rounded-2xl px-4 py-3 text-sm font-bold border border-cyan-500/25 text-cyan-100 bg-cyan-500/12">
          Abrir link
        </button>
      </div>
    </div>

    <div class="rounded-[28px] border border-slate-800/80 bg-slate-950/45 p-4">
      <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500 mb-3">Social / perfil</div>
      <div class="flex items-center gap-3">
        <div class="w-14 h-14 rounded-2xl bg-slate-900 border border-slate-700 flex items-center justify-center">
          <i id="linkPreviewSocialIcon" class="fa-solid fa-share-nodes text-indigo-300 text-xl"></i>
        </div>
        <div class="min-w-0">
          <div id="linkPreviewSocialTitle" class="font-black text-white truncate">Titulo do link</div>
          <div id="linkPreviewSocialCta" class="text-xs text-slate-400 mt-1">CTA rapido</div>
        </div>
      </div>
      <button type="button" id="linkPreviewSocialButton" class="mt-4 w-full inline-flex items-center justify-center rounded-2xl px-4 py-3 text-sm font-bold border border-indigo-500/25 text-indigo-100 bg-indigo-500/12">Visitar perfil</button>
    </div>

    <div class="rounded-[28px] border border-slate-800/80 bg-slate-950/45 p-4">
      <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500 mb-3">Bloco editorial</div>
      <div class="flex items-center gap-2 flex-wrap">
        <span id="linkPreviewEditorialSelo" class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-bold border border-slate-500/25 text-slate-100 bg-slate-500/10">Selo</span>
        <span id="linkPreviewEditorialTipo" class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">Conteudo</span>
      </div>
      <div id="linkPreviewEditorialTitle" class="mt-4 font-orbitron text-lg font-black text-white">Titulo do link</div>
      <div id="linkPreviewEditorialDesc" class="mt-2 text-sm leading-6 text-slate-300">Descricao curta do link para a pagina de bio.</div>
      <div id="linkPreviewEditorialUrl" class="mt-3 text-xs font-bold text-cyan-300 break-all">https://exemplo.com</div>
    </div>
    </div>
  </section>

  <section class="admin-panel flex flex-wrap items-center justify-between gap-3">
    <div class="text-xs text-slate-400">Os links desta tela devem alimentar a futura pagina de bio, ofertas e distribuicao de campanhas.</div>
    <div class="flex flex-wrap gap-2">
      <a href="<?= url('/admin/links') ?>" class="admin-btn admin-btn-secondary">Cancelar</a>
      <button type="submit" class="admin-btn admin-btn-primary"><?= htmlspecialchars($submitLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></button>
    </div>
  </section>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var titulo = document.getElementById('titulo');
  var slug = document.getElementById('slug');
  var gerar = document.getElementById('gerarLinkSlug');

  if (gerar && titulo && slug) {
    gerar.addEventListener('click', function () {
      var value = titulo.value || '';
      value = value.toLowerCase();
      try {
        value = value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
      } catch (e) {}
      value = value.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
      slug.value = value;
    });
  }

  var imageInput = document.getElementById('imagem');
  var fileInput = document.getElementById('imagem_upload');
  var preview = document.getElementById('imagem_link_preview');
  var previewEmpty = document.getElementById('imagem_link_preview_empty');
  var clearButton = document.getElementById('limparImagemLink');
  var publicBase = <?= json_encode(rtrim(url('/'), '/'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  var descricaoInput = document.getElementById('descricao');
  var urlInput = document.getElementById('url');
  var tipoInput = document.getElementById('tipo');
  var destaqueInput = document.querySelector('input[name="destaque"][value="1"]');
  var ctaInput = document.getElementById('cta_curto');
  var buttonTextInput = document.getElementById('texto_botao');
  var seloInput = document.getElementById('selo');
  var previewTitle = document.getElementById('linkCardPreviewTitle');
  var previewDescription = document.getElementById('linkCardPreviewDescription');
  var previewUrl = document.getElementById('linkCardPreviewUrl');
  var previewType = document.getElementById('linkCardPreviewType');
  var previewTypeLabel = document.getElementById('linkCardPreviewTypeLabel');
  var previewTypeIcon = document.getElementById('linkCardPreviewTypeIcon');
  var previewDestaque = document.getElementById('linkCardPreviewDestaque');
  var previewButton = document.getElementById('linkCardPreviewButton');
  var previewSocialIcon = document.getElementById('linkPreviewSocialIcon');
  var previewSocialTitle = document.getElementById('linkPreviewSocialTitle');
  var previewSocialCta = document.getElementById('linkPreviewSocialCta');
  var previewSocialButton = document.getElementById('linkPreviewSocialButton');
  var previewEditorialSelo = document.getElementById('linkPreviewEditorialSelo');
  var previewEditorialTipo = document.getElementById('linkPreviewEditorialTipo');
  var previewEditorialTitle = document.getElementById('linkPreviewEditorialTitle');
  var previewEditorialDesc = document.getElementById('linkPreviewEditorialDesc');
  var previewEditorialUrl = document.getElementById('linkPreviewEditorialUrl');
  var cardImage = document.getElementById('linkCardPreviewImage');
  var cardImageEmpty = document.getElementById('linkCardPreviewImageEmpty');

  var resolveUrl = function (value) {
    value = (value || '').trim();
    if (!value) return '';
    if (/^https?:\/\//i.test(value)) return value;
    if (value.charAt(0) !== '/') value = '/' + value;
    return publicBase + value;
  };

  var syncPreview = function (value) {
    if (!preview) return;
    var resolved = resolveUrl(value || (imageInput ? imageInput.value : ''));
    if (!resolved) {
      preview.src = '';
      preview.classList.add('hidden');
      if (previewEmpty) previewEmpty.classList.remove('hidden');
      if (cardImage) cardImage.classList.add('hidden');
      if (cardImageEmpty) cardImageEmpty.classList.remove('hidden');
      return;
    }
    preview.src = resolved;
    preview.classList.remove('hidden');
    if (previewEmpty) previewEmpty.classList.add('hidden');
    if (cardImage) {
      cardImage.src = resolved;
      cardImage.classList.remove('hidden');
    }
    if (cardImageEmpty) cardImageEmpty.classList.add('hidden');
  };

  var syncCardPreview = function () {
    if (previewTitle && titulo) {
      previewTitle.textContent = (titulo.value || '').trim() || 'Titulo do link';
    }

    if (previewDescription && descricaoInput) {
      previewDescription.textContent = (descricaoInput.value || '').trim() || 'Descricao curta do link para a pagina de bio.';
    }

    if (previewSocialTitle && titulo) {
      previewSocialTitle.textContent = (titulo.value || '').trim() || 'Titulo do link';
    }

    if (previewEditorialTitle && titulo) {
      previewEditorialTitle.textContent = (titulo.value || '').trim() || 'Titulo do link';
    }

    if (previewUrl && urlInput) {
      previewUrl.textContent = (urlInput.value || '').trim() || 'https://exemplo.com';
    }

    if (previewEditorialUrl && urlInput) {
      previewEditorialUrl.textContent = (urlInput.value || '').trim() || 'https://exemplo.com';
    }

    var buttonText = 'Abrir link';
    if (previewButton && tipoInput) {
      if (tipoInput.value === 'rede_social') buttonText = 'Visitar perfil';
      if (tipoInput.value === 'oferta') buttonText = 'Ver oferta';
      if (tipoInput.value === 'servico') buttonText = 'Conhecer servico';
      if (tipoInput.value === 'afiliado') buttonText = 'Conferir produto';
      if (buttonTextInput && (buttonTextInput.value || '').trim()) buttonText = buttonTextInput.value.trim();
      previewButton.textContent = buttonText;
    }

    if (previewSocialButton) {
      previewSocialButton.textContent = buttonText;
    }

    if (previewType && previewTypeLabel && previewTypeIcon && tipoInput) {
      var map = {
        afiliado: { label: 'Afiliado', icon: 'fa-solid fa-coins', iconClass: 'text-teal-300' },
        oferta: { label: 'Oferta', icon: 'fa-solid fa-bolt', iconClass: 'text-pink-300' },
        rede_social: { label: 'Rede social', icon: 'fa-solid fa-share-nodes', iconClass: 'text-indigo-300' },
        servico: { label: 'Servico', icon: 'fa-solid fa-briefcase', iconClass: 'text-orange-300' },
        conteudo: { label: 'Conteudo', icon: 'fa-solid fa-newspaper', iconClass: 'text-slate-300' }
      };
      var current = map[tipoInput.value] || map.conteudo;
      previewTypeLabel.textContent = current.label;
      previewTypeIcon.className = current.icon + ' ' + current.iconClass;
      if (previewSocialIcon) previewSocialIcon.className = current.icon + ' ' + current.iconClass + ' text-xl';
      if (previewEditorialTipo) previewEditorialTipo.textContent = current.label;
    }

    if (previewDestaque && destaqueInput) {
      previewDestaque.classList.toggle('hidden', !destaqueInput.checked);
    }

    if (previewSocialCta) {
      previewSocialCta.textContent = (ctaInput && (ctaInput.value || '').trim()) || 'CTA rapido';
    }

    if (previewEditorialDesc) {
      previewEditorialDesc.textContent = (descricaoInput && (descricaoInput.value || '').trim()) || 'Descricao curta do link para a pagina de bio.';
    }

    if (previewEditorialSelo) {
      var seloValue = (seloInput && (seloInput.value || '').trim()) || ((destaqueInput && destaqueInput.checked) ? 'Destaque' : 'Selo');
      previewEditorialSelo.textContent = seloValue;
      previewEditorialSelo.classList.toggle('hidden', seloValue === '');
    }
  };

  if (imageInput) {
    imageInput.addEventListener('input', function () {
      syncPreview(imageInput.value);
      syncCardPreview();
    });
  }

  if (fileInput) {
    fileInput.addEventListener('change', function () {
      var file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
      if (!file || !preview) return;
      var objectUrl = URL.createObjectURL(file);
      preview.src = objectUrl;
      preview.classList.remove('hidden');
      if (previewEmpty) previewEmpty.classList.add('hidden');
      if (cardImage) {
        cardImage.src = objectUrl;
        cardImage.classList.remove('hidden');
      }
      if (cardImageEmpty) cardImageEmpty.classList.add('hidden');
    });
  }

  if (clearButton && imageInput) {
    clearButton.addEventListener('click', function () {
      imageInput.value = '';
      if (fileInput) fileInput.value = '';
      syncPreview('');
      syncCardPreview();
    });
  }

  document.querySelectorAll('[data-link-image-pick]').forEach(function (button) {
    button.addEventListener('click', function () {
      if (!imageInput) return;
      imageInput.value = button.getAttribute('data-link-image-pick') || '';
      if (fileInput) fileInput.value = '';
      syncPreview(imageInput.value);
      syncCardPreview();
    });
  });

  [titulo, descricaoInput, urlInput, ctaInput, buttonTextInput, seloInput].forEach(function (input) {
    if (!input) return;
    input.addEventListener('input', syncCardPreview);
  });

  if (tipoInput) {
    tipoInput.addEventListener('change', syncCardPreview);
  }

  if (destaqueInput) {
    destaqueInput.addEventListener('change', syncCardPreview);
  }

  syncPreview(imageInput ? imageInput.value : '');
  syncCardPreview();
});
</script>
