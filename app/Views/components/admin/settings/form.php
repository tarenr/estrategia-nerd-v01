<?php
declare(strict_types=1);

use App\Support\Csrf;

$form = $form ?? [];
$errors = $errors ?? [];
$mediaItems = $media_items ?? [];
$quickMediaItems = array_slice($mediaItems, 0, 4);
$action = (string) ($action ?? '#');
$submitLabel = (string) ($submitLabel ?? 'Salvar configuracoes');

$fieldError = static fn (string $key): string => (string) ($errors[$key] ?? '');
$resolvePreview = static function (string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    return preg_match('~^https?://~i', $value) ? $value : url('/' . ltrim($value, '/'));
};

$logoPreview = $resolvePreview((string) ($form['logo_url'] ?? ''));
$brandSymbolPreview = $resolvePreview((string) ($form['brand_symbol_url'] ?? ''));
$faviconPreview = $resolvePreview((string) ($form['favicon_url'] ?? ''));
$bioPreview = $resolvePreview((string) ($form['bio_avatar_url'] ?? ''));
$aboutPreview = $resolvePreview((string) ($form['sobre_imagem_url'] ?? ''));
?>

<form method="POST" action="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" enctype="multipart/form-data" class="space-y-6" novalidate>
  <?= Csrf::field() ?>

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

  <section class="admin-panel settings-portal-panel space-y-5">
    <div>
      <h2 class="font-orbitron text-lg font-black text-white">Portal</h2>
      <div class="text-xs text-slate-400 mt-1">Dados institucionais, contato e SEO base que sustentam o portal publico.</div>
    </div>

    <div class="settings-portal-stack">
      <div class="settings-subpanel">
        <div class="settings-subpanel-head">
          <div>
            <h3 class="settings-subpanel-title">Identidade</h3>
            <div class="settings-subpanel-copy">Defina o nome editorial do portal e a frase curta que acompanha a marca.</div>
          </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
          <div>
            <label for="nome_site" class="block text-sm font-bold text-slate-200 mb-2">Nome do portal</label>
            <input id="nome_site" name="nome_site" type="text" value="<?= htmlspecialchars((string) ($form['nome_site'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Estrategia Nerd">
            <div class="settings-field-helper">Nome principal do cabecalho, footer e areas institucionais.</div>
            <?php if ($fieldError('nome_site') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('nome_site'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
          </div>

          <div>
            <div class="settings-field-head">
              <label for="site_kicker" class="block text-sm font-bold text-slate-200">Texto curto da marca</label>
              <div class="settings-field-counter"><span id="settingsSiteKickerCount">0</span> caracteres</div>
            </div>
            <input id="site_kicker" name="site_kicker" type="text" value="<?= htmlspecialchars((string) ($form['site_kicker'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Portal geek estrategico">
            <div class="settings-field-helper">Apoio rapido de marca para blocos curtos e textos institucionais.</div>
            <?php if ($fieldError('site_kicker') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('site_kicker'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
          </div>

          <div class="xl:col-span-2">
            <div class="settings-field-head">
              <label for="descricao_site" class="block text-sm font-bold text-slate-200">Descricao do portal</label>
              <div class="settings-field-counter"><span id="settingsDescricaoSiteCount">0</span> caracteres</div>
            </div>
            <textarea id="descricao_site" name="descricao_site" rows="3" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Resumo curto do portal para home, SEO e blocos institucionais."><?= htmlspecialchars((string) ($form['descricao_site'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
            <div class="settings-field-helper">Resumo editorial do portal para home, apresentacao institucional e pontos de contexto.</div>
            <?php if ($fieldError('descricao_site') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('descricao_site'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
          </div>
        </div>
      </div>

      <div class="settings-subpanel">
        <div class="settings-subpanel-head">
          <div>
            <h3 class="settings-subpanel-title">Contato e base publica</h3>
            <div class="settings-subpanel-copy">Informacoes de acesso e contato que alimentam o site e pontos institucionais.</div>
          </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
          <div>
            <label for="site_url" class="block text-sm font-bold text-slate-200 mb-2">URL principal do portal</label>
            <input id="site_url" name="site_url" type="text" value="<?= htmlspecialchars((string) ($form['site_url'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="https://estrategianerd.com">
            <div class="settings-field-helper">Endereco principal usado por previews, redirecionamentos e referencias do portal.</div>
            <?php if ($fieldError('site_url') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('site_url'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
          </div>

          <div>
            <label for="email_contato" class="block text-sm font-bold text-slate-200 mb-2">Email de contato</label>
            <input id="email_contato" name="email_contato" type="email" value="<?= htmlspecialchars((string) ($form['email_contato'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="contato@estrategianerd.com">
            <div class="settings-field-helper">Contato institucional exibido no portal e em blocos operacionais.</div>
            <?php if ($fieldError('email_contato') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('email_contato'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
          </div>

          <div class="xl:col-span-2">
            <div class="settings-field-head">
              <label for="footer_texto" class="block text-sm font-bold text-slate-200">Texto de rodape</label>
              <div class="settings-field-counter"><span id="settingsFooterTextCount">0</span> caracteres</div>
            </div>
            <input id="footer_texto" name="footer_texto" type="text" value="<?= htmlspecialchars((string) ($form['footer_texto'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Estrategia Nerd - Conteudo, links e ofertas geek">
            <div class="settings-field-helper">Mensagem curta que fecha o footer institucional do portal.</div>
            <?php if ($fieldError('footer_texto') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('footer_texto'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
          </div>
        </div>
      </div>

      <div class="settings-subpanel">
        <div class="settings-subpanel-head">
          <div>
            <h3 class="settings-subpanel-title">SEO base</h3>
            <div class="settings-subpanel-copy">Defina o titulo e a descricao padrao para home, paginas institucionais e futuros pontos publicos.</div>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-5">
          <div>
            <div class="settings-field-head">
              <label for="meta_title_padrao" class="block text-sm font-bold text-slate-200">Meta title padrao</label>
              <div class="settings-field-counter"><span id="settingsMetaTitleCount">0</span> caracteres</div>
            </div>
            <input id="meta_title_padrao" name="meta_title_padrao" type="text" value="<?= htmlspecialchars((string) ($form['meta_title_padrao'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Estrategia Nerd | Conteudo, ofertas e tecnologia geek">
            <div class="settings-field-helper">Use um titulo objetivo para busca. Faixa recomendada: 45 a 60 caracteres.</div>
            <?php if ($fieldError('meta_title_padrao') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('meta_title_padrao'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
          </div>

          <div>
            <div class="settings-field-head">
              <label for="meta_description_padrao" class="block text-sm font-bold text-slate-200">Meta description padrao</label>
              <div class="settings-field-counter"><span id="settingsMetaDescriptionCount">0</span> caracteres</div>
            </div>
            <textarea id="meta_description_padrao" name="meta_description_padrao" rows="3" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Descricao SEO padrao para home e paginas institucionais do portal."><?= htmlspecialchars((string) ($form['meta_description_padrao'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
            <div class="settings-field-helper">Resuma o beneficio do portal em uma frase curta. Faixa recomendada: 120 a 160 caracteres.</div>
            <?php if ($fieldError('meta_description_padrao') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('meta_description_padrao'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
          </div>

          <div class="settings-note">
            <div class="settings-note-title">Leitura rapida</div>
            <div class="settings-note-text">Nome e texto curto sustentam a identidade do portal. URL, email e rodape alimentam contato e base institucional. Meta title e description formam o SEO padrao da home e das paginas fixas.</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="admin-panel space-y-5">
    <div>
      <h2 class="font-orbitron text-lg font-black text-white">Branding e imagens</h2>
      <div class="text-xs text-slate-400 mt-1">Logo, favicon e avatar da pagina de links com upload direto ou reaproveitamento da biblioteca.</div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
      <?php
      $mediaFields = [
          [
              'key' => 'logo_url',
              'upload' => 'logo_upload',
              'title' => 'Logo do portal',
              'description' => 'Usado em cabecalhos, areas institucionais e identidade principal.',
              'preview' => $logoPreview,
              'empty' => 'Sem logo selecionada',
          ],
          [
              'key' => 'brand_symbol_url',
              'upload' => 'brand_symbol_upload',
              'title' => 'Simbolo da marca',
              'description' => 'Icone reduzido do topo, footer e pontos compactos do site.',
              'preview' => $brandSymbolPreview,
              'empty' => 'Sem simbolo selecionado',
          ],
          [
              'key' => 'favicon_url',
              'upload' => 'favicon_upload',
              'title' => 'Favicon',
              'description' => 'Icone do navegador e atalhos do portal.',
              'preview' => $faviconPreview,
              'empty' => 'Sem favicon selecionado',
          ],
          [
              'key' => 'bio_avatar_url',
              'upload' => 'bio_avatar_upload',
              'title' => 'Avatar da bio',
              'description' => 'Imagem principal da futura pagina de links.',
              'preview' => $bioPreview,
              'empty' => 'Sem avatar selecionado',
          ],
          [
              'key' => 'sobre_imagem_url',
              'upload' => 'sobre_imagem_upload',
              'title' => 'Imagem da secao Sobre',
              'description' => 'Arte principal exibida no bloco institucional da Home.',
              'preview' => $aboutPreview,
              'empty' => 'Sem imagem da secao Sobre',
          ],
      ];
      ?>
      <?php foreach ($mediaFields as $field): ?>
        <div class="rounded-2xl border border-cyan-500/15 bg-slate-950/40 p-4 space-y-4">
          <div class="flex items-center justify-between gap-3">
            <div>
              <h3 class="text-sm font-black text-white"><?= htmlspecialchars($field['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
              <div class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($field['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            </div>
            <button type="button" class="admin-btn admin-btn-secondary !px-3 !py-2 text-xs" data-settings-clear="<?= htmlspecialchars($field['key'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Limpar</button>
          </div>

          <div class="aspect-video rounded-2xl border border-cyan-500/20 bg-slate-900/70 overflow-hidden flex items-center justify-center text-xs text-slate-500">
            <?php if ($field['preview'] !== ''): ?>
              <img src="<?= htmlspecialchars($field['preview'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($field['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="w-full h-full object-cover" data-settings-preview="<?= htmlspecialchars($field['key'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
              <div class="hidden px-4 text-center" data-settings-preview-empty="<?= htmlspecialchars($field['key'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($field['empty'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <?php else: ?>
              <img src="" alt="<?= htmlspecialchars($field['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="hidden w-full h-full object-cover" data-settings-preview="<?= htmlspecialchars($field['key'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
              <div class="px-4 text-center" data-settings-preview-empty="<?= htmlspecialchars($field['key'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($field['empty'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <?php endif; ?>
          </div>

          <div>
            <label for="<?= htmlspecialchars($field['key'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="block text-sm font-bold text-slate-200 mb-2">URL ou caminho</label>
            <input id="<?= htmlspecialchars($field['key'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" name="<?= htmlspecialchars($field['key'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" type="text" value="<?= htmlspecialchars((string) ($form[$field['key']] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="/uploads/configuracoes/... ou https://..." data-settings-image-input="<?= htmlspecialchars($field['key'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <?php if ($fieldError($field['key']) !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError($field['key']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
          </div>

          <div>
            <label for="<?= htmlspecialchars($field['upload'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="block text-sm font-bold text-slate-200 mb-2">Enviar arquivo</label>
            <input id="<?= htmlspecialchars($field['upload'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" name="<?= htmlspecialchars($field['upload'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" type="file" accept="image/*" class="block w-full text-sm text-slate-300 file:mr-4 file:rounded-xl file:border-0 file:bg-cyan-500/15 file:px-4 file:py-2 file:font-bold file:text-cyan-100 hover:file:bg-cyan-500/25" data-settings-upload-input="<?= htmlspecialchars($field['key'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="rounded-2xl border border-cyan-500/15 bg-slate-950/40 p-4 space-y-4">
      <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
          <h3 class="text-sm font-black text-white">Selecao rapida da biblioteca</h3>
          <div class="text-xs text-slate-400 mt-1">Mostra so os 4 itens mais recentes para aplicar no campo que estiver ativo.</div>
        </div>
        <a href="<?= htmlspecialchars(url('/admin/midia'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-btn admin-btn-secondary !px-3 !py-2 text-xs">Abrir midia</a>
      </div>

      <?php if ($quickMediaItems === []): ?>
        <div class="rounded-xl border border-dashed border-slate-700 bg-slate-900/40 px-4 py-6 text-sm text-slate-400">Nenhuma imagem recente encontrada na biblioteca.</div>
      <?php else: ?>
        <div class="settings-media-quick-grid">
          <?php foreach ($quickMediaItems as $item): ?>
            <?php $itemUrl = (string) ($item['public_url'] ?? ''); ?>
            <?php $itemPath = (string) ($item['relative_path'] ?? ''); ?>
            <?php $itemName = (string) ($item['name'] ?? $itemPath); ?>
            <button type="button" class="settings-media-quick-item" data-settings-image-pick="<?= htmlspecialchars($itemPath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
              <div class="settings-media-quick-thumb">
                <img src="<?= htmlspecialchars($itemUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($itemName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="w-full h-full object-cover">
              </div>
              <div class="settings-media-quick-copy">
                <div class="settings-media-quick-name"><?= htmlspecialchars($itemName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="settings-media-quick-path"><?= htmlspecialchars($itemPath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </div>
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="admin-panel settings-links-panel space-y-5">
    <div>
      <h2 class="font-orbitron text-lg font-black text-white">Pagina de links</h2>
      <div class="text-xs text-slate-400 mt-1">Base da Central Nerd: textos do topo e canais que podem aparecer no footer, na bio e em blocos publicos.</div>
    </div>

    <div class="settings-subpanel">
      <div class="settings-subpanel-head">
        <div>
          <h3 class="settings-subpanel-title">Topo da bio</h3>
          <div class="settings-subpanel-copy">Esses textos aparecem no hero da Central Nerd e definem o tom da bio publica.</div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-5">
        <div>
          <div class="settings-field-head">
            <label for="bio_titulo" class="block text-sm font-bold text-slate-200">Titulo da bio</label>
            <div class="settings-field-counter"><span id="settingsBioTitleCount">0</span> caracteres</div>
          </div>
          <input id="bio_titulo" name="bio_titulo" type="text" value="<?= htmlspecialchars((string) ($form['bio_titulo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Estrategia Nerd">
          <div class="settings-field-helper">Titulo principal exibido no topo da Central Nerd.</div>
          <?php if ($fieldError('bio_titulo') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('bio_titulo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
        </div>

        <div>
          <div class="settings-field-head">
            <label for="bio_descricao" class="block text-sm font-bold text-slate-200">Descricao da bio</label>
            <div class="settings-field-counter"><span id="settingsBioDescriptionCount">0</span> caracteres</div>
          </div>
          <textarea id="bio_descricao" name="bio_descricao" rows="3" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Texto curto para o topo da pagina /links."><?= htmlspecialchars((string) ($form['bio_descricao'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
          <div class="settings-field-helper">Descricao curta para contextualizar a bio e os links principais.</div>
          <?php if ($fieldError('bio_descricao') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('bio_descricao'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
        </div>

        <div class="settings-note">
          <div class="settings-note-title">Regra de exibicao</div>
          <div class="settings-note-text">Somente links sociais preenchidos aparecem no footer, na Central Nerd e em blocos publicos que usam essas configuracoes.</div>
        </div>
      </div>
    </div>

    <div class="settings-subpanel">
      <div class="settings-subpanel-head">
        <div>
          <h3 class="settings-subpanel-title">Redes e canais</h3>
          <div class="settings-subpanel-copy">Veja rapidamente o que ja esta configurado e o que ainda falta para a presenca publica da marca.</div>
        </div>
        <div class="settings-social-summary">
          <span class="settings-social-summary-chip" id="settingsSocialCountPreview">0 canais ativos</span>
          <span class="settings-social-summary-chip is-muted" id="settingsSocialPendingPreview">0 pendentes</span>
        </div>
      </div>

      <div class="settings-social-grid">
        <?php foreach ([
            'instagram_url' => ['label' => 'Instagram', 'icon' => 'fab fa-instagram'],
            'tiktok_url' => ['label' => 'TikTok', 'icon' => 'fab fa-tiktok'],
            'kwai_url' => ['label' => 'Kwai', 'icon' => 'fas fa-play-circle'],
            'youtube_url' => ['label' => 'YouTube', 'icon' => 'fab fa-youtube'],
            'telegram_url' => ['label' => 'Telegram', 'icon' => 'fab fa-telegram-plane'],
            'whatsapp_url' => ['label' => 'WhatsApp', 'icon' => 'fab fa-whatsapp'],
        ] as $field => $social): ?>
          <?php $socialValue = trim((string) ($form[$field] ?? '')); ?>
          <div class="settings-social-card<?= $socialValue !== '' ? ' is-active' : '' ?>" data-settings-social-card="<?= htmlspecialchars($field, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <div class="settings-social-card-head">
              <div class="settings-social-card-brand">
                <span class="settings-social-card-icon"><i class="<?= htmlspecialchars((string) $social['icon'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></i></span>
                <span class="settings-social-card-label"><?= htmlspecialchars((string) $social['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
              </div>
              <span class="settings-social-card-state" data-settings-social-state="<?= htmlspecialchars($field, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= $socialValue !== '' ? 'Preenchido' : 'Vazio' ?></span>
            </div>
            <input id="<?= htmlspecialchars($field, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" name="<?= htmlspecialchars($field, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" type="text" value="<?= htmlspecialchars((string) ($form[$field] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="https://...">
            <?php if ($fieldError($field) !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError($field), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="admin-panel space-y-5">
    <div>
      <h2 class="font-orbitron text-lg font-black text-white">Preview rapido</h2>
      <div class="text-xs text-slate-400 mt-1">Uma leitura simples do topo da futura pagina de links baseada nas configuracoes atuais.</div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[320px_1fr] gap-5 items-start">
      <div class="rounded-[30px] border border-cyan-500/15 bg-slate-950/55 p-5">
        <div class="w-20 h-20 rounded-3xl border border-cyan-500/20 bg-slate-900/70 overflow-hidden mx-auto flex items-center justify-center text-slate-500">
          <?php if ($bioPreview !== ''): ?>
            <img src="<?= htmlspecialchars($bioPreview, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="Avatar da bio" class="w-full h-full object-cover" id="settingsBioAvatarPreview">
            <span id="settingsBioAvatarEmpty" class="hidden text-xs text-center px-3">Avatar</span>
          <?php else: ?>
            <span id="settingsBioAvatarEmpty" class="text-xs text-center px-3">Avatar</span>
            <img src="" alt="Avatar da bio" class="hidden w-full h-full object-cover" id="settingsBioAvatarPreview">
          <?php endif; ?>
        </div>
        <div id="settingsBioTitlePreview" class="mt-4 font-orbitron text-2xl font-black text-white text-center"><?= htmlspecialchars((string) ($form['bio_titulo'] ?? 'Estrategia Nerd'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <div id="settingsBioDescriptionPreview" class="mt-3 text-sm leading-6 text-slate-300 text-center"><?= htmlspecialchars((string) ($form['bio_descricao'] ?? 'Descricao curta da pagina de links.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <div class="mt-5 grid grid-cols-2 gap-2 text-xs">
          <div class="rounded-2xl border border-slate-800 bg-slate-900/70 px-3 py-3 text-center text-slate-300">Posts</div>
          <div class="rounded-2xl border border-slate-800 bg-slate-900/70 px-3 py-3 text-center text-slate-300">Ofertas</div>
          <div class="rounded-2xl border border-slate-800 bg-slate-900/70 px-3 py-3 text-center text-slate-300">Newsletter</div>
          <div class="rounded-2xl border border-slate-800 bg-slate-900/70 px-3 py-3 text-center text-slate-300">Servicos</div>
        </div>
      </div>

      <div class="rounded-[30px] border border-slate-800/80 bg-slate-950/45 p-5">
        <div class="flex items-center gap-4">
          <div class="w-16 h-16 rounded-2xl border border-cyan-500/20 bg-slate-900/70 overflow-hidden flex items-center justify-center text-slate-500">
            <?php if ($logoPreview !== ''): ?>
              <img src="<?= htmlspecialchars($logoPreview, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="Logo do portal" class="w-full h-full object-cover" id="settingsLogoPreview">
              <span id="settingsLogoEmpty" class="hidden text-xs text-center px-2">Logo</span>
            <?php else: ?>
              <span id="settingsLogoEmpty" class="text-xs text-center px-2">Logo</span>
              <img src="" alt="Logo do portal" class="hidden w-full h-full object-cover" id="settingsLogoPreview">
            <?php endif; ?>
          </div>
          <div class="min-w-0">
            <div id="settingsSiteTitlePreview" class="font-orbitron text-2xl font-black text-white"><?= htmlspecialchars((string) ($form['nome_site'] ?? 'Estrategia Nerd'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <div id="settingsSiteDescriptionPreview" class="mt-2 text-sm leading-6 text-slate-300"><?= htmlspecialchars((string) ($form['descricao_site'] ?? 'Descricao principal do portal.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          </div>
        </div>

        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
            <div class="text-slate-400">Contato</div>
            <div id="settingsEmailPreview" class="mt-2 font-bold text-white break-all"><?= htmlspecialchars((string) ($form['email_contato'] ?? 'contato@portal.com'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          </div>
          <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
            <div class="text-slate-400">Redes configuradas</div>
            <div id="settingsSocialCountPreview" class="mt-2 font-bold text-white">0 canais ativos</div>
          </div>
          <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 md:col-span-2">
            <div class="text-slate-400">Meta title padrao</div>
            <div id="settingsMetaTitlePreview" class="mt-2 font-bold text-white"><?= htmlspecialchars((string) ($form['meta_title_padrao'] ?? 'Meta title do portal'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <div id="settingsMetaDescriptionPreview" class="mt-2 text-sm leading-6 text-slate-300"><?= htmlspecialchars((string) ($form['meta_description_padrao'] ?? 'Descricao SEO padrao do portal.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          </div>
          <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 md:col-span-2">
            <div class="text-slate-400">Rodape institucional</div>
            <div id="settingsFooterPreview" class="mt-2 font-bold text-white"><?= htmlspecialchars((string) ($form['footer_texto'] ?? 'Estrategia Nerd - Conteudo, links e ofertas geek'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="admin-panel flex flex-wrap items-center justify-between gap-3">
    <div class="text-xs text-slate-400">Essas configuracoes devem sustentar o portal principal, a futura pagina /links e blocos institucionais do projeto.</div>
    <div class="flex flex-wrap gap-2">
      <button type="submit" class="admin-btn admin-btn-primary"><?= htmlspecialchars($submitLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></button>
    </div>
  </section>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var publicBase = <?= json_encode(rtrim(url('/'), '/'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  var activeField = 'bio_avatar_url';

  var resolveUrl = function (value) {
    value = (value || '').trim();
    if (!value) return '';
    if (/^https?:\/\//i.test(value)) return value;
    if (value.charAt(0) !== '/') value = '/' + value;
    return publicBase + value;
  };

  var syncImageField = function (field, value) {
    var input = document.querySelector('[data-settings-image-input="' + field + '"]');
    var preview = document.querySelector('[data-settings-preview="' + field + '"]');
    var empty = document.querySelector('[data-settings-preview-empty="' + field + '"]');
    if (!input || !preview || !empty) return;

    if (typeof value === 'string' && !/^blob:/i.test(value)) input.value = value;
    var resolved = typeof value === 'string' && /^blob:/i.test(value) ? value : resolveUrl(input.value);
    if (!resolved) {
      preview.src = '';
      preview.classList.add('hidden');
      empty.classList.remove('hidden');
    } else {
      preview.src = resolved;
      preview.classList.remove('hidden');
      empty.classList.add('hidden');
    }

    if (field === 'logo_url') {
      var logoPreview = document.getElementById('settingsLogoPreview');
      var logoEmpty = document.getElementById('settingsLogoEmpty');
      if (logoPreview && logoEmpty) {
        if (!resolved) {
          logoPreview.src = '';
          logoPreview.classList.add('hidden');
          logoEmpty.classList.remove('hidden');
        } else {
          logoPreview.src = resolved;
          logoPreview.classList.remove('hidden');
          logoEmpty.classList.add('hidden');
        }
      }
    }

    if (field === 'bio_avatar_url') {
      var avatarPreview = document.getElementById('settingsBioAvatarPreview');
      var avatarEmpty = document.getElementById('settingsBioAvatarEmpty');
      if (avatarPreview && avatarEmpty) {
        if (!resolved) {
          avatarPreview.src = '';
          avatarPreview.classList.add('hidden');
          avatarEmpty.classList.remove('hidden');
        } else {
          avatarPreview.src = resolved;
          avatarPreview.classList.remove('hidden');
          avatarEmpty.classList.add('hidden');
        }
      }
    }
  };

  document.querySelectorAll('[data-settings-image-input]').forEach(function (input) {
    var field = input.getAttribute('data-settings-image-input') || '';
    input.addEventListener('focus', function () { activeField = field; });
    input.addEventListener('input', function () {
      activeField = field;
      syncImageField(field);
    });
  });

  document.querySelectorAll('[data-settings-upload-input]').forEach(function (input) {
    var field = input.getAttribute('data-settings-upload-input') || '';
    input.addEventListener('change', function () {
      activeField = field;
      var file = input.files && input.files[0] ? input.files[0] : null;
      if (!file) return;
      syncImageField(field, URL.createObjectURL(file));
    });
  });

  document.querySelectorAll('[data-settings-clear]').forEach(function (button) {
    button.addEventListener('click', function () {
      var field = button.getAttribute('data-settings-clear') || '';
      activeField = field;
      var input = document.querySelector('[data-settings-image-input="' + field + '"]');
      var upload = document.querySelector('[data-settings-upload-input="' + field + '"]');
      if (input) input.value = '';
      if (upload) upload.value = '';
      syncImageField(field, '');
    });
  });

  document.querySelectorAll('[data-settings-image-pick]').forEach(function (button) {
    button.addEventListener('click', function () {
      var path = button.getAttribute('data-settings-image-pick') || '';
      var input = document.querySelector('[data-settings-image-input="' + activeField + '"]');
      var upload = document.querySelector('[data-settings-upload-input="' + activeField + '"]');
      if (!input) return;
      input.value = path;
      if (upload) upload.value = '';
      syncImageField(activeField);
    });
  });

  var bindTextPreview = function (inputId, previewId, fallback) {
    var input = document.getElementById(inputId);
    var preview = document.getElementById(previewId);
    if (!input || !preview) return;
    var sync = function () { preview.textContent = (input.value || '').trim() || fallback; };
    input.addEventListener('input', sync);
    sync();
  };

  var bindLengthCounter = function (inputId, counterId) {
    var input = document.getElementById(inputId);
    var counter = document.getElementById(counterId);
    if (!input || !counter) return;
    var sync = function () { counter.textContent = String((input.value || '').trim().length); };
    input.addEventListener('input', sync);
    sync();
  };

  bindTextPreview('nome_site', 'settingsSiteTitlePreview', 'Estrategia Nerd');
  bindTextPreview('descricao_site', 'settingsSiteDescriptionPreview', 'Descricao principal do portal.');
  bindTextPreview('bio_titulo', 'settingsBioTitlePreview', 'Estrategia Nerd');
  bindTextPreview('bio_descricao', 'settingsBioDescriptionPreview', 'Descricao curta da pagina de links.');
  bindTextPreview('email_contato', 'settingsEmailPreview', 'contato@portal.com');
  bindTextPreview('meta_title_padrao', 'settingsMetaTitlePreview', 'Meta title do portal');
  bindTextPreview('meta_description_padrao', 'settingsMetaDescriptionPreview', 'Descricao SEO padrao do portal.');
  bindTextPreview('footer_texto', 'settingsFooterPreview', 'Estrategia Nerd - Conteudo, links e ofertas geek');

  var socialInputs = ['instagram_url', 'tiktok_url', 'kwai_url', 'youtube_url', 'telegram_url', 'whatsapp_url'].map(function (id) {
    return document.getElementById(id);
  }).filter(Boolean);
  var socialCountPreview = document.getElementById('settingsSocialCountPreview');
  var socialPendingPreview = document.getElementById('settingsSocialPendingPreview');
  var syncSocialCount = function () {
    var total = socialInputs.filter(function (input) { return (input.value || '').trim() !== ''; }).length;
    var pending = socialInputs.length - total;

    if (socialCountPreview) {
      socialCountPreview.textContent = total + ' canal' + (total === 1 ? ' ativo' : 's ativos');
    }

    if (socialPendingPreview) {
      socialPendingPreview.textContent = pending + ' pendente' + (pending === 1 ? '' : 's');
    }

    socialInputs.forEach(function (input) {
      var key = input.id;
      var card = document.querySelector('[data-settings-social-card="' + key + '"]');
      var state = document.querySelector('[data-settings-social-state="' + key + '"]');
      var filled = (input.value || '').trim() !== '';
      if (card) card.classList.toggle('is-active', filled);
      if (state) state.textContent = filled ? 'Preenchido' : 'Vazio';
    });
  };
  socialInputs.forEach(function (input) { input.addEventListener('input', syncSocialCount); });
  syncSocialCount();

  ['logo_url', 'favicon_url', 'bio_avatar_url'].forEach(function (field) { syncImageField(field); });
});
</script>
