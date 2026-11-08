<?php
/** Assistente de Stories e Destaques: configurar, montar, conferir. */
declare(strict_types=1);
$statusData = $initial_status ?? [];
$jobs = (array) ($statusData['jobs'] ?? []);
$sessionStored = (bool) ($statusData['session_stored'] ?? false);
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="<?= $escape(url('/assets/css/admin-instagram-highlights.css')) ?>?v=ux3">
<div class="ih-container">
  <header class="ih-page-header"><div><h1>Stories e destaques</h1><p>Você adiciona as imagens e os links. O sistema publica os stories e monta o destaque.</p></div><span class="ih-account">@estrategia_nerd</span></header>
  <nav aria-label="Etapas da publicação" class="ih-steps">
    <button type="button" data-ih-step="1" aria-current="step"><span>1</span><span>Configurar conta</span></button>
    <button type="button" data-ih-step="2"><span>2</span><span>Montar destaque</span></button>
    <button type="button" data-ih-step="3" disabled><span>3</span><span>Conferir e publicar</span></button>
  </nav>
  <div id="ihOperationNotice" class="ih-notice hidden"><p id="ihOperationMessage" role="status" aria-live="polite"></p><div class="ih-item-actions">
    <button type="button" id="ihBtnRecover" class="ih-btn ih-btn-secondary hidden">Conferir andamento</button>
    <button type="button" id="ihBtnContinue" class="ih-btn ih-btn-primary hidden">Revisar retomada</button>
    <button type="button" id="ihBtnReconcile" class="ih-btn ih-btn-secondary hidden">Conferir no Instagram</button>
    <button type="button" id="ihBtnNewOperation" class="ih-btn ih-btn-secondary hidden">Nova montagem</button>
  </div></div>
  <section class="ih-stage" data-ih-panel="1" aria-labelledby="ihSetupHeading">
    <h2 id="ihSetupHeading" tabindex="-1">Primeiro, prepare sua conta</h2>
    <p class="ih-lead">A conexão é feita uma vez neste computador. Você também pode montar o destaque agora e conectar antes de publicar.</p>
    <div class="ih-connection"><span class="ih-account-symbol" aria-hidden="true">EN</span><div><strong>@estrategia_nerd</strong><p id="ihConnectionStatus" role="status"><?= $sessionStored ? 'Configuração encontrada. A conta será verificada ao publicar.' : 'Conta ainda não configurada.' ?></p></div><button type="button" id="ihRefreshStatus" class="ih-btn ih-btn-secondary">Atualizar situação</button></div>
    <details id="ihAuthHelp" class="ih-help"><summary>Como conectar minha conta</summary>
      <ol><li>No computador do projeto, abra o PowerShell na pasta <strong>estrategia-nerd</strong>.</li><li>Copie e execute o comando abaixo.</li><li>Informe sua senha e, se solicitado, o código de verificação. A senha não aparece enquanto você digita e não é salva pelo painel.</li><li>Quando o terminal confirmar a conexão, volte aqui e clique em <strong>Atualizar situação</strong>.</li></ol>
      <div class="ih-command"><code id="ihAuthCommand">storage\instagram-highlights\venv\Scripts\python.exe tools\instagram-highlights\auth_cli.py</code><button type="button" id="ihCopyCommand" class="ih-btn ih-btn-secondary">Copiar comando</button></div>
      <p class="ih-muted">Este botão apenas copia o comando. A conexão acontece no terminal, não nesta página.</p><p id="ihCopyStatus" role="status"></p>
    </details>
    <div class="ih-stage-footer"><p class="ih-muted">Nada será publicado nesta etapa.</p><button type="button" id="ihStartBuilding" class="ih-btn ih-btn-primary">Continuar para montar</button></div>
  </section>
  <section class="ih-stage hidden" data-ih-panel="2" aria-labelledby="ihBuildHeading">
    <h2 id="ihBuildHeading" tabindex="-1">Monte seu destaque</h2><p class="ih-lead">Exemplo: “PC R$400”, com um story do gabinete, outro do processador e o link de cada peça.</p>
    <div class="ih-build-layout"><div data-ih-editor>
      <fieldset class="ih-choice"><legend>Onde colocar os stories?</legend><label><input type="radio" name="ih_highlight_mode" value="new" checked> Criar um destaque</label><label><input type="radio" name="ih_highlight_mode" value="existing"> Adicionar a um existente</label></fieldset>
      <div id="ihExistingHighlightFields" class="hidden ih-field-group"><label for="ihExistingSelect">Destaque de destino</label><div class="ih-inline"><select id="ihExistingSelect" class="ih-input"><option value="">Consulte os destaques para escolher</option></select><button type="button" id="ihBtnRefreshCatalog" class="ih-btn ih-btn-secondary">Buscar meus destaques</button></div><p class="ih-muted">Essa consulta acessa o Instagram e precisa da conta configurada.</p></div>
      <div id="ihNewHighlightFields" class="ih-appearance">
        <div class="ih-field-group"><div class="ih-label-row"><label for="ihHighlightTitle" id="ihTitleLabel">Nome do destaque</label><span id="ihTitleCounter" class="ih-muted">0 / 15</span></div><input type="text" id="ihHighlightTitle" class="ih-input" placeholder="Ex.: PC R$400" maxlength="15" autocomplete="off"><p class="ih-muted">É o nome que aparece no seu perfil.</p></div>
        <div class="ih-field-group"><label for="ihCoverFileInput">Capa (opcional)</label><div class="ih-inline"><img id="ihCoverPreview" class="ih-cover hidden" alt="Capa escolhida"><button type="button" id="ihCoverPicker" class="ih-btn ih-btn-secondary">Escolher foto da capa</button><input type="file" id="ihCoverFileInput" accept="image/jpeg" class="hidden"></div><p class="ih-muted">JPEG quadrado, de 320 a 2160 px. Sem escolha, um destaque novo usa o primeiro story; um existente mantém a capa.</p></div>
      </div>
      <div class="ih-story-section"><div class="ih-label-row"><h3>Imagens e links dos stories</h3><span id="ihStoryCountBadge" class="ih-muted">0 / 20</span></div><p class="ih-muted">Cada imagem vira um story. Depois de adicionar, cole o link correspondente abaixo dela.</p>
        <div id="ihDropZone" class="ih-drop" role="button" tabindex="0" aria-label="Adicionar imagens de stories"><input type="file" id="ihFileInput" accept="image/jpeg" multiple class="hidden"><strong>+ Adicionar imagens</strong><span>Ou arraste os arquivos para cá</span><small>JPEG vertical de 1080 × 1920 px · Até 8 MB por imagem · Máximo de 20</small></div>
        <p id="ihUploadStatus" role="status" class="ih-muted"></p><div id="ihStoryList" class="ih-story-list"><p class="ih-muted">Adicione a primeira imagem para começar.</p></div>
      </div>
    </div>
    <aside id="ihPreviewPanel" class="ih-preview-sticky hidden" aria-label="Prévia dos stories">
      <button type="button" id="ihTogglePreview" class="ih-btn ih-btn-secondary" aria-expanded="false" aria-controls="ihPreviewBody">Ver como ficará</button>
      <div id="ihPreviewBody" class="ih-preview-body"><div class="ih-label-row"><h3>Prévia do story</h3><span id="ihPreviewIndexIndicator" class="ih-muted">0 / 0</span></div>
        <div class="ih-phone-frame"><div class="ih-phone-screen" id="ihPhoneScreen"><div id="ihPhoneEmptyNotice">Adicione uma imagem para ver a prévia.</div><img id="ihPhoneImage" class="ih-phone-image hidden" alt="Prévia do story selecionado"><div class="ih-story-progress-bar" id="ihPhoneProgressBar"></div><div class="ih-story-header"><div class="ih-avatar-circle"><div class="ih-avatar-inner">EN</div></div><strong>estrategia_nerd</strong></div><div id="ihPhoneLinkSticker" class="ih-link-sticker hidden"><span aria-hidden="true">↗</span><span id="ihPhoneLinkText">link</span></div></div></div>
        <div class="ih-preview-controls"><button type="button" id="ihPreviewPrev" disabled class="ih-btn ih-btn-secondary">Anterior</button><button type="button" id="ihPreviewNext" disabled class="ih-btn ih-btn-secondary">Próximo</button></div><p class="ih-muted">Prévia ilustrativa. O Instagram pode ajustar a aparência do link.</p>
      </div>
    </aside></div>
    <div class="ih-stage-footer"><button type="button" id="ihBackSetup" class="ih-btn ih-btn-secondary">Voltar</button><div><p id="ihReviewHint" class="ih-muted">Adicione uma imagem para continuar.</p><button type="button" id="ihBtnReview" class="ih-btn ih-btn-primary" disabled>Continuar para conferir</button></div></div>
  </section>
  <section id="ihReviewModal" class="ih-stage hidden" data-ih-panel="3" aria-labelledby="ihReviewHeading"><h2 id="ihReviewHeading" tabindex="-1">Confira antes de publicar</h2><p class="ih-lead">Veja as imagens, os links e o destaque de destino. A publicação só começa quando você clicar no botão abaixo.</p><div id="ihReviewSummary"></div>
    <div id="ihPublishRequirement" class="ih-notice hidden"><p>Configure a conta antes de publicar. Sua montagem permanece nesta aba.</p><button type="button" id="ihGoConnect" class="ih-btn ih-btn-secondary">Ver como conectar</button></div><p class="ih-muted">Se uma resposta não chegar, o envio será interrompido para você conferir o resultado antes de continuar.</p>
    <div class="ih-stage-footer"><button type="button" id="ihEditDraft" class="ih-btn ih-btn-secondary">Voltar para editar</button><button type="button" id="ihBtnConfirmPublish" class="ih-btn ih-btn-primary">Publicar stories e montar destaque</button></div>
  </section>
  <details class="ih-history"><summary>Publicações anteriores <span class="ih-muted">(<?= count($jobs) ?>)</span></summary>
    <?php if (!$jobs): ?><p class="ih-muted">Você ainda não fez publicações por esta integração.</p><?php endif; ?>
    <?php foreach ($jobs as $job): $jId = (string) ($job['id'] ?? ''); $jStatus = (string) ($job['status'] ?? 'ready'); $labels = ['ready' => 'Pronta para continuar', 'completed' => 'Concluída', 'unknown' => 'Precisa conferir no Instagram', 'running' => 'Em andamento', 'failed' => 'Interrompida']; ?>
      <div class="ih-history-row"><div><strong><?= $escape((string) (($job['highlight']['title'] ?? '') ?: 'Destaque existente')) ?></strong><p class="ih-muted"><?= $escape($labels[$jStatus] ?? 'Conferir andamento') ?> · <?= (int) ($job['completed_steps'] ?? 0) ?> de <?= (int) ($job['total_steps'] ?? 0) ?> etapas</p></div>
      <?php if ($jStatus === 'unknown'): ?><button type="button" class="ih-btn ih-btn-secondary" data-ih-reconcile="<?= $escape($jId) ?>">Conferir resultado</button><?php elseif ($jStatus === 'ready'): ?><button type="button" class="ih-btn ih-btn-secondary" data-ih-resume="<?= $escape($jId) ?>">Revisar retomada</button><?php endif; ?></div>
    <?php endforeach; ?>
  </details>
</div>
<div id="ihProgressModal" class="ih-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="ihProgressStatusText" tabindex="-1"><div class="ih-modal-content"><h2 id="ihProgressStatusText" role="status">Preparando publicação…</h2><p id="ihProgressStepDetail">Aguarde a confirmação.</p><div class="ih-progress-track"><div id="ihProgressBarFill" style="width:0%"></div></div><p id="ihProgressPercent">0%</p><p class="ih-muted">Mantenha esta página aberta enquanto os stories são enviados.</p></div></div>
<div id="ihReconcileModal" class="ih-modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="ihReconcileHeading"><div class="ih-modal-content"><h2 id="ihReconcileHeading">Confira o resultado no Instagram</h2><p id="ihReconcileMessage"></p><p class="ih-muted">Abra o aplicativo e confira se esta etapa foi concluída. Se ela não aconteceu ou você não tem certeza, feche esta janela e mantenha o envio interrompido.</p><input type="hidden" id="ihReconcileJobId"><label for="ihReconcileRemoteId">Identificador do story ou destaque confirmado</label><input type="text" id="ihReconcileRemoteId" class="ih-input" placeholder="Ex.: 123456789_123456"><p class="ih-muted">Obrigatório para um story ou destaque recém-criado. Para alterar um destaque existente, pode ficar vazio.</p><label class="ih-confirm"><input type="checkbox" id="ihReconcileConfirmCheck"><span>Conferi no Instagram e confirmo que esta etapa foi realizada com sucesso, com o conteúdo esperado.</span></label><div class="ih-stage-footer"><button type="button" data-ih-close class="ih-btn ih-btn-secondary">Fechar sem confirmar</button><button type="button" id="ihBtnSubmitReconcile" disabled class="ih-btn ih-btn-primary">Confirmar resultado</button></div></div></div>
<script>window.IH_CONFIG = <?= json_encode(['endpoint' => $endpoint, 'csrfToken' => $csrf_token, 'status' => ['session_stored' => $sessionStored, 'runtime_ready' => (bool) ($statusData['runtime_ready'] ?? false)]], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="<?= $escape(url('/assets/js/admin-instagram-highlights.js')) ?>?v=ux3"></script>
