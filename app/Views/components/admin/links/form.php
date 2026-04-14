<?php
declare(strict_types=1);

use App\Support\Csrf;

$mode = (string) ($mode ?? 'create');
$action = (string) ($action ?? '#');
$submitLabel = (string) ($submitLabel ?? 'Salvar link');
$form = $form ?? [];
$errors = $errors ?? [];
$mediaItems = $media_items ?? [];
$currentFeatured = is_array($current_featured ?? null) ? $current_featured : null;
$currentFeaturedId = (int) ($currentFeatured['id'] ?? 0);
$currentFeaturedTitle = trim((string) ($currentFeatured['titulo'] ?? ''));
$editingId = (int) ($form['id'] ?? 0);
$currentFeaturedType = trim((string) ($currentFeatured['tipo'] ?? ''));
$currentFeaturedTypeLabel = match ($currentFeaturedType) {
    'produto' => 'Produto',
    'cupom' => 'Cupom',
    'conteudo' => 'Conteudo',
    'rede_social' => 'Rede social',
    'servico' => 'Servico',
    default => 'Link',
};
$currentFeaturedUrl = trim((string) ($currentFeatured['url'] ?? ''));
$currentFeaturedDescription = trim((string) ($currentFeatured['descricao'] ?? ''));
$currentFeaturedDiscount = trim((string) ($currentFeatured['desconto_percentual'] ?? ''));
$currentFeaturedDiscountContext = trim((string) ($currentFeatured['desconto_contexto'] ?? ''));
$currentFeaturedCouponCode = trim((string) ($currentFeatured['codigo_cupom'] ?? ''));
$currentFeaturedIsCoupon = $currentFeaturedType === 'cupom';
$featuredSwitchMessage = ($currentFeaturedId > 0 && $currentFeaturedId !== $editingId)
    ? 'Confirmar? Este item substituira o destaque atual: ' . ($currentFeaturedTitle !== '' ? $currentFeaturedTitle : 'item atual') . '.'
    : '';

$fieldError = static fn (string $key): string => (string) ($errors[$key] ?? '');
$imageValue = trim((string) ($form['imagem'] ?? ''));
$imagePreview = $imageValue !== '' ? (preg_match('~^https?://~i', $imageValue) ? $imageValue : url('/' . ltrim($imageValue, '/'))) : '';
?>

<form method="POST" action="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" enctype="multipart/form-data" class="space-y-6" novalidate data-link-form data-featured-switch-message="<?= htmlspecialchars($featuredSwitchMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
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
        <div class="text-xs text-slate-400 mt-1">Essa tela alimenta diretamente a Central Nerd, com a mesma tipagem publica usada no link da bio.</div>
      </div>
      <div class="admin-chip">Modo: <?= $mode === 'edit' ? 'edicao' : 'criacao' ?></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <div>
        <label for="titulo" class="block text-sm font-bold text-slate-200 mb-2">Titulo</label>
        <input id="titulo" name="titulo" type="text" value="<?= htmlspecialchars((string) ($form['titulo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Ex.: SSD NVMe Gen4 ou Cupom Hostinger Brasil">
        <?php if ($fieldError('titulo') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('titulo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
      </div>

      <div>
        <label for="slug" class="block text-sm font-bold text-slate-200 mb-2">Slug</label>
        <div class="flex gap-2">
          <input id="slug" name="slug" type="text" value="<?= htmlspecialchars((string) ($form['slug'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="ssd-nvme-gen4">
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
        <select id="tipo" name="tipo" class="nerd-input w-full px-4 py-3 rounded-xl" data-link-type-select>
          <?php foreach (['produto' => 'Produto', 'cupom' => 'Cupom de Desconto', 'conteudo' => 'Conteudo', 'rede_social' => 'Rede Social', 'servico' => 'Servicos'] as $value => $label): ?>
            <option value="<?= $value ?>" <?= (string) ($form['tipo'] ?? 'produto') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label for="status" class="block text-sm font-bold text-slate-200 mb-2">Status</label>
        <select id="status" name="status" class="nerd-input w-full px-4 py-3 rounded-xl">
          <?php foreach (['ativo' => 'Ativo', 'oculto' => 'Oculto', 'expirado' => 'Expirado', 'quebrado' => 'Revisar'] as $value => $label): ?>
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

      <div data-link-product-wrap class="<?= (string) ($form['tipo'] ?? 'produto') === 'produto' ? '' : 'hidden' ?>">
        <label for="subgrupo_publico" class="block text-sm font-bold text-slate-200 mb-2">Grupo de produtos</label>
        <input id="subgrupo_publico" name="subgrupo_publico" type="text" value="<?= htmlspecialchars((string) ($form['subgrupo_publico'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Ex.: Action Figures, Upgrade Monstro, Gadgets">
        <div class="mt-2 text-xs text-slate-500">Esse nome vira o botao/accordion da secao de produtos na Central Nerd.</div>
        <?php if ($fieldError('subgrupo_publico') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('subgrupo_publico'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
      </div>

      <div data-link-product-wrap class="<?= (string) ($form['tipo'] ?? 'produto') === 'produto' ? '' : 'hidden' ?>">
        <label class="block text-sm font-bold text-slate-200 mb-2">Promoções / Ofertas</label>
        <label class="inline-flex items-center gap-3 text-sm text-slate-200">
          <input type="hidden" name="promocao" value="0">
          <input type="checkbox" name="promocao" value="1" class="rounded border-slate-700 bg-slate-900" <?= (int) ($form['promocao'] ?? 0) === 1 ? 'checked' : '' ?>>
          Marcar este item como promoção/oferta para subir ao topo da Central Nerd (respeitando a posição)
        </label>
      </div>

      <div data-link-coupon-wrap class="<?= (string) ($form['tipo'] ?? '') === 'cupom' ? '' : 'hidden' ?>">
        <label for="codigo_cupom" class="block text-sm font-bold text-slate-200 mb-2">Codigo do cupom (opcional)</label>
        <input id="codigo_cupom" name="codigo_cupom" type="text" value="<?= htmlspecialchars((string) ($form['codigo_cupom'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Ex.: PROMO100 ou deixe vazio para oferta por link">
        <?php if ($fieldError('codigo_cupom') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('codigo_cupom'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
      </div>

      <div data-link-coupon-wrap class="<?= (string) ($form['tipo'] ?? '') === 'cupom' ? '' : 'hidden' ?>">
        <label for="desconto_percentual" class="block text-sm font-bold text-slate-200 mb-2">Valor do desconto</label>
        <input id="desconto_percentual" name="desconto_percentual" type="text" value="<?= htmlspecialchars((string) ($form['desconto_percentual'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Ex.: 15% ou R$12">
        <?php if ($fieldError('desconto_percentual') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('desconto_percentual'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
      </div>

      <div data-link-coupon-wrap class="lg:col-span-2 <?= (string) ($form['tipo'] ?? '') === 'cupom' ? '' : 'hidden' ?>">
        <label for="desconto_contexto" class="block text-sm font-bold text-slate-200 mb-2">Onde esse cupom se aplica?</label>
        <input id="desconto_contexto" name="desconto_contexto" type="text" value="<?= htmlspecialchars((string) ($form['desconto_contexto'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Ex.: Loja toda ou produtos selecionados.">
        <?php if ($fieldError('desconto_contexto') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('desconto_contexto'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
      </div>

      <div class="lg:col-span-2">
        <label for="descricao" class="block text-sm font-bold text-slate-200 mb-2">Descricao curta</label>
        <textarea id="descricao" name="descricao" rows="3" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Resumo rapido para exibir na Central Nerd."><?= htmlspecialchars((string) ($form['descricao'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
        <?php if ($fieldError('descricao') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('descricao'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
      </div>

      <div>
        <label for="cta_curto" class="block text-sm font-bold text-slate-200 mb-2">CTA curto</label>
        <input id="cta_curto" name="cta_curto" type="text" value="<?= htmlspecialchars((string) ($form['cta_curto'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Ex.: Oferta da semana">
      </div>

      <div>
        <label for="texto_botao" class="block text-sm font-bold text-slate-200 mb-2">Texto do botao</label>
        <input id="texto_botao" name="texto_botao" type="text" value="<?= htmlspecialchars((string) ($form['texto_botao'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Ex.: Ver produto">
      </div>

      <div class="lg:col-span-2">
        <label for="selo" class="block text-sm font-bold text-slate-200 mb-2">Selo</label>
        <input id="selo" name="selo" type="text" value="<?= htmlspecialchars((string) ($form['selo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Ex.: Mais clicado, Novo, Oferta relampago">
      </div>

      <div class="lg:col-span-2">
        <label for="observacao_status" class="block text-sm font-bold text-slate-200 mb-2">Observacao de status</label>
        <input id="observacao_status" name="observacao_status" type="text" value="<?= htmlspecialchars((string) ($form['observacao_status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Opcional: contexto sobre campanha, revisao ou verificacao.">
      </div>
    </div>

    <div class="mt-5">
      <div class="text-sm font-bold text-slate-200 mb-2">Destaque principal (1 link)</div>
      <label class="inline-flex items-center gap-3 text-sm text-slate-200">
        <input type="hidden" name="destaque" value="0">
        <input id="destaque" type="checkbox" name="destaque" value="1" class="rounded border-slate-700 bg-slate-900" <?= (int) ($form['destaque'] ?? 0) === 1 ? 'checked' : '' ?>>
        Enviar este link para o destaque principal da Central Nerd
      </label>
      <div class="mt-2 text-xs text-slate-500">Ao ativar em outro item, o destaque atual sera substituido automaticamente.</div>

      <?php if ($currentFeatured !== null): ?>
        <div class="mt-4 rounded-2xl border border-cyan-500/25 bg-slate-900/40 p-4">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-2">
              <div class="inline-flex items-center gap-2 rounded-full border border-cyan-500/25 bg-cyan-500/10 px-3 py-1 text-xs text-cyan-100">
                <span class="font-bold">Destaque atual</span>
                <span>#<?= $currentFeaturedId ?></span>
                <span>• <?= htmlspecialchars($currentFeaturedTypeLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
              </div>
              <div class="text-sm font-bold text-white"><?= htmlspecialchars($currentFeaturedTitle !== '' ? $currentFeaturedTitle : 'Sem titulo', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <?php if ($currentFeaturedDescription !== ''): ?>
                <div class="text-xs text-slate-300 leading-5"><?= htmlspecialchars($currentFeaturedDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <?php endif; ?>
              <?php if ($currentFeaturedIsCoupon && ($currentFeaturedDiscount !== '' || $currentFeaturedDiscountContext !== '')): ?>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                  <?php if ($currentFeaturedDiscount !== ''): ?>
                    <span class="inline-flex items-center rounded-full border border-blue-500/30 bg-blue-500/10 px-2.5 py-1 text-blue-100"><?= htmlspecialchars($currentFeaturedDiscount, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                  <?php endif; ?>
                  <?php if ($currentFeaturedDiscountContext !== ''): ?>
                    <span class="inline-flex items-center rounded-full border border-slate-600/50 bg-slate-800/50 px-2.5 py-1 text-slate-300"><?= htmlspecialchars($currentFeaturedDiscountContext, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <?php if ($currentFeaturedIsCoupon && $currentFeaturedCouponCode !== ''): ?>
                <button type="button" class="admin-btn admin-btn-secondary !px-3 !py-2 text-xs" data-current-featured-copy="<?= htmlspecialchars($currentFeaturedCouponCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-current-featured-copy-text>
                  Copiar codigo: <?= htmlspecialchars($currentFeaturedCouponCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </button>
              <?php endif; ?>
              <?php if ($currentFeaturedIsCoupon && $currentFeaturedCouponCode === ''): ?>
                <span class="inline-flex items-center rounded-full border border-slate-600/50 bg-slate-800/40 px-3 py-2 text-xs text-slate-300">Oferta sem codigo</span>
              <?php endif; ?>
              <?php if ($currentFeaturedUrl !== ''): ?>
                <a href="<?= htmlspecialchars($currentFeaturedUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="admin-btn admin-btn-secondary !px-3 !py-2 text-xs">Ir para site</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="mt-3 text-xs text-slate-400">Nenhum link esta marcado como destaque principal.</div>
      <?php endif; ?>
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
          <div class="text-xs text-slate-400 mt-1">Ideal para botoes visuais, ofertas e cards destacados na Central Nerd.</div>
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

  <section class="admin-panel flex flex-wrap items-center justify-between gap-3">
    <div class="text-xs text-slate-400">Os links desta tela alimentam exclusivamente a Central Nerd do Instagram e futuras secoes publicas de servicos.</div>
    <div class="flex flex-wrap gap-2">
      <a href="<?= url('/admin/links') ?>" class="admin-btn admin-btn-secondary">Cancelar</a>
      <button type="submit" class="admin-btn admin-btn-primary"><?= htmlspecialchars($submitLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></button>
    </div>
  </section>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var formEl = document.querySelector('[data-link-form]');
  var titulo = document.getElementById('titulo');
  var slug = document.getElementById('slug');
  var gerar = document.getElementById('gerarLinkSlug');
  var tipoInput = document.querySelector('[data-link-type-select]');
  var productWraps = document.querySelectorAll('[data-link-product-wrap]');
  var couponWraps = document.querySelectorAll('[data-link-coupon-wrap]');
  var destaqueInput = document.getElementById('destaque');
  var imageInput = document.getElementById('imagem');
  var fileInput = document.getElementById('imagem_upload');
  var preview = document.getElementById('imagem_link_preview');
  var previewEmpty = document.getElementById('imagem_link_preview_empty');
  var clearButton = document.getElementById('limparImagemLink');
  var publicBase = <?= json_encode(rtrim(url('/'), '/'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  var featuredSwitchMessage = formEl ? (formEl.getAttribute('data-featured-switch-message') || '').trim() : '';
  var featuredSwitchConfirmed = false;

  var escapeHtml = function (value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\"/g, '&quot;')
      .replace(/'/g, '&#039;');
  };

  var openInlineConfirmModal = function (options) {
    return new Promise(function (resolve) {
      var host = document.createElement('div');
      host.className = 'fixed inset-0 z-[10020] flex items-center justify-center px-4 py-8';
      host.innerHTML =
        '<div class="absolute inset-0 bg-slate-950/80 backdrop-blur-sm"></div>' +
        '<div class="relative w-full max-w-lg rounded-3xl border border-cyan-500/20 bg-slate-950 shadow-2xl shadow-cyan-500/10">' +
          '<div class="border-b border-slate-800/70 px-6 py-5">' +
            '<div class="font-orbitron text-lg font-black text-white">' + escapeHtml(options.title || 'Confirmacao') + '</div>' +
          '</div>' +
          '<div class="px-6 py-5 text-sm leading-6 text-slate-300">' + escapeHtml(options.message || '') + '</div>' +
          '<div class="flex items-center justify-end gap-3 border-t border-slate-800/70 px-6 py-5">' +
            '<button type="button" data-inline-cancel class="admin-btn admin-btn-secondary">' + escapeHtml(options.cancelLabel || 'Cancelar') + '</button>' +
            '<button type="button" data-inline-confirm class="admin-btn admin-btn-primary">' + escapeHtml(options.submitLabel || 'Confirmar') + '</button>' +
          '</div>' +
        '</div>';

      var close = function (accepted) {
        host.remove();
        document.body.style.overflow = '';
        resolve(accepted);
      };

      document.body.appendChild(host);
      document.body.style.overflow = 'hidden';

      var confirmButton = host.querySelector('[data-inline-confirm]');
      var cancelButton = host.querySelector('[data-inline-cancel]');
      var overlay = host.firstElementChild;

      if (confirmButton) confirmButton.addEventListener('click', function () { close(true); });
      if (cancelButton) cancelButton.addEventListener('click', function () { close(false); });
      if (overlay) overlay.addEventListener('click', function () { close(false); });
    });
  };

  var openConfirmModal = function (options) {
    if (window.adminUi && typeof window.adminUi.confirm === 'function') {
      return window.adminUi.confirm({
        title: options.title || 'Confirmacao',
        subtitle: 'Central Nerd',
        message: '<p class="text-sm leading-6 text-slate-300">' + escapeHtml(options.message || '') + '</p>',
        submitLabel: options.submitLabel || 'Confirmar',
        cancelLabel: options.cancelLabel || 'Cancelar'
      });
    }

    return openInlineConfirmModal(options);
  };

  if (gerar && titulo && slug) {
    gerar.addEventListener('click', function () {
      var value = titulo.value || '';
      value = value.toLowerCase();
      try { value = value.normalize('NFD').replace(/[\u0300-\u036f]/g, ''); } catch (e) {}
      value = value.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
      slug.value = value;
    });
  }

  var syncTypeFields = function () {
    var type = tipoInput ? tipoInput.value : 'produto';
    productWraps.forEach(function (wrap) { wrap.classList.toggle('hidden', type !== 'produto'); });
    couponWraps.forEach(function (wrap) { wrap.classList.toggle('hidden', type !== 'cupom'); });
    if (type !== 'produto') {
      var group = document.getElementById('subgrupo_publico');
      var promo = document.querySelector('input[name="promocao"][value="1"]');
      if (group) group.value = '';
      if (promo) promo.checked = false;
    }
    if (type !== 'cupom') {
      var code = document.getElementById('codigo_cupom');
      var percent = document.getElementById('desconto_percentual');
      var context = document.getElementById('desconto_contexto');
      if (code) code.value = '';
      if (percent) percent.value = '';
      if (context) context.value = '';
    }
  };

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
      return;
    }
    preview.src = resolved;
    preview.classList.remove('hidden');
    if (previewEmpty) previewEmpty.classList.add('hidden');
  };

  if (tipoInput) {
    tipoInput.addEventListener('change', syncTypeFields);
    syncTypeFields();
  }

  if (formEl && destaqueInput) {
    destaqueInput.addEventListener('change', async function () {
      if (!destaqueInput.checked) {
        featuredSwitchConfirmed = false;
        return;
      }

      if (!featuredSwitchMessage || featuredSwitchConfirmed) return;

      var ok = await openConfirmModal({
        title: 'Trocar destaque principal',
        message: featuredSwitchMessage,
        submitLabel: 'Confirmar troca',
        cancelLabel: 'Cancelar'
      });
      if (!ok) {
        destaqueInput.checked = false;
        featuredSwitchConfirmed = false;
        return;
      }

      featuredSwitchConfirmed = true;
    });

    formEl.addEventListener('submit', function (event) {
      if (!destaqueInput.checked) return;
      if (!featuredSwitchMessage || featuredSwitchConfirmed) return;
      event.preventDefault();
      openConfirmModal({
        title: 'Trocar destaque principal',
        message: featuredSwitchMessage,
        submitLabel: 'Confirmar troca',
        cancelLabel: 'Cancelar'
      }).then(function (ok) {
        if (!ok) {
          destaqueInput.checked = false;
          featuredSwitchConfirmed = false;
          return;
        }

        featuredSwitchConfirmed = true;
        formEl.submit();
      });
    });
  }

  var copyTextToClipboard = async function (value) {
    var text = String(value || '').trim();
    if (!text) return false;

    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
      return true;
    }

    var input = document.createElement('textarea');
    input.value = text;
    input.setAttribute('readonly', 'readonly');
    input.style.position = 'fixed';
    input.style.opacity = '0';
    document.body.appendChild(input);
    input.select();
    var ok = document.execCommand('copy');
    document.body.removeChild(input);
    return ok;
  };

  document.querySelectorAll('[data-current-featured-copy]').forEach(function (button) {
    var original = button.textContent;
    var timer = null;
    button.addEventListener('click', async function () {
      try {
        var ok = await copyTextToClipboard(button.getAttribute('data-current-featured-copy') || '');
        if (!ok) throw new Error('fail');
        button.textContent = 'Codigo copiado';
        window.clearTimeout(timer);
        timer = window.setTimeout(function () { button.textContent = original; }, 1800);
      } catch (e) {
        button.textContent = 'Falha ao copiar';
        window.clearTimeout(timer);
        timer = window.setTimeout(function () { button.textContent = original; }, 1800);
      }
    });
  });

  if (imageInput) {
    imageInput.addEventListener('input', function () { syncPreview(imageInput.value); });
  }

  if (fileInput) {
    fileInput.addEventListener('change', function () {
      var file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
      if (!file || !preview) return;
      var objectUrl = URL.createObjectURL(file);
      preview.src = objectUrl;
      preview.classList.remove('hidden');
      if (previewEmpty) previewEmpty.classList.add('hidden');
    });
  }

  if (clearButton && imageInput) {
    clearButton.addEventListener('click', function () {
      imageInput.value = '';
      if (fileInput) fileInput.value = '';
      syncPreview('');
    });
  }

  document.querySelectorAll('[data-link-image-pick]').forEach(function (button) {
    button.addEventListener('click', function () {
      if (!imageInput) return;
      imageInput.value = button.getAttribute('data-link-image-pick') || '';
      if (fileInput) fileInput.value = '';
      syncPreview(imageInput.value);
    });
  });

  syncPreview(imageInput ? imageInput.value : '');
});
</script>
