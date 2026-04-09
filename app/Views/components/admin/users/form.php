<?php

declare(strict_types=1);

use App\Support\Csrf;

$title = (string) ($title ?? 'Criar Usuario');
$mode = (string) ($mode ?? 'create');
$form = is_array($form ?? null) ? $form : [];
$errors = is_array($errors ?? null) ? $errors : [];
$usuario = is_array($usuario ?? null) ? $usuario : null;
$papelOptions = is_array($papel_options ?? null) ? $papel_options : [];
$statusOptions = is_array($status_options ?? null) ? $status_options : [];
$avatarIconOptions = is_array($avatar_icon_options ?? null) ? $avatar_icon_options : [];

$action = $mode === 'edit'
    ? url('/admin/editar-usuario?id=' . (int) ($form['id'] ?? 0))
    : url('/admin/criar-usuario');

$previewName = trim((string) ($form['nome'] ?? $form['usuario'] ?? 'Usuario'));
$avatarTipo = (string) ($form['avatar_tipo'] ?? 'icone');
$avatarImagem = trim((string) ($form['avatar_imagem'] ?? ''));
$avatarIcone = trim((string) ($form['avatar_icone'] ?? 'fa-solid fa-user'));
$avatarCor = trim((string) ($form['avatar_cor'] ?? '#38bdf8'));
$avatarFocalX = max(0.0, min(100.0, (float) ($form['avatar_focal_x'] ?? 50.0)));
$avatarFocalY = max(0.0, min(100.0, (float) ($form['avatar_focal_y'] ?? 50.0)));
$avatarUrl = $avatarImagem !== '' ? (preg_match('~^https?://~i', $avatarImagem) ? $avatarImagem : url('/' . ltrim($avatarImagem, '/'))) : '';
$avatarFocusStyle = 'object-position: ' . number_format($avatarFocalX, 2, '.', '') . '% ' . number_format($avatarFocalY, 2, '.', '') . '%;';
$hasPhoto = $avatarTipo === 'foto' && $avatarUrl !== '';

$fieldError = static function (string $key) use ($errors): string {
    return trim((string) ($errors[$key] ?? ''));
};
?>

<div class="max-w-6xl mx-auto px-4 py-6 space-y-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title"><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
      <div class="admin-page-subtitle">Cadastre acessos do painel com nome, papel, senha e identidade visual padronizada.</div>
    </div>

    <div class="admin-page-actions">
      <a href="<?= url('/admin/usuarios') ?>" class="admin-btn admin-btn-secondary"><i class="fa-solid fa-arrow-left"></i>Voltar para usuarios</a>
    </div>
  </div>

  <form method="POST" action="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" enctype="multipart/form-data" class="space-y-6" data-users-avatar-form data-avatar-current-url="<?= htmlspecialchars($avatarUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int) ($form['id'] ?? 0) ?>">
    <input type="hidden" name="avatar_imagem" value="<?= htmlspecialchars($avatarImagem, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="avatar_focal_x" value="<?= htmlspecialchars(number_format($avatarFocalX, 2, '.', ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-avatar-focal-x>
    <input type="hidden" name="avatar_focal_y" value="<?= htmlspecialchars(number_format($avatarFocalY, 2, '.', ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-avatar-focal-y>

    <section class="admin-panel space-y-6">
      <div>
        <h2 class="admin-panel-title"><i class="fa-solid fa-id-card text-cyan-300"></i><span>Informacoes principais</span></h2>
        <div class="admin-panel-subtitle">Defina a identidade e o login principal do usuario no painel.</div>
      </div>

      <div class="users-form-grid users-form-grid-main">
        <div class="users-form-field">
          <label class="admin-filter-label" for="usuario-nome">Nome</label>
          <input id="usuario-nome" name="nome" value="<?= htmlspecialchars((string) ($form['nome'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input admin-filter-control" placeholder="Nome completo ou apelido de exibicao" data-avatar-name>
          <?php if ($fieldError('nome') !== ''): ?><div class="users-form-error"><?= htmlspecialchars($fieldError('nome'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
        </div>

        <div class="users-form-field">
          <label class="admin-filter-label" for="usuario-login">Usuario</label>
          <input id="usuario-login" name="usuario" value="<?= htmlspecialchars((string) ($form['usuario'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input admin-filter-control" placeholder="login-do-admin">
          <?php if ($fieldError('usuario') !== ''): ?><div class="users-form-error"><?= htmlspecialchars($fieldError('usuario'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
        </div>

        <div class="users-form-field users-form-field-full">
          <label class="admin-filter-label" for="usuario-email">Email</label>
          <input id="usuario-email" name="email" type="email" value="<?= htmlspecialchars((string) ($form['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input admin-filter-control" placeholder="email@estrategianerd.com">
          <?php if ($fieldError('email') !== ''): ?><div class="users-form-error"><?= htmlspecialchars($fieldError('email'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
        </div>
      </div>
    </section>

    <div class="users-form-split">
      <section class="admin-panel space-y-6">
        <div>
          <h2 class="admin-panel-title"><i class="fa-solid fa-shield-halved text-cyan-300"></i><span>Acesso e seguranca</span></h2>
          <div class="admin-panel-subtitle">Controle o papel do usuario, o status da conta e a senha de acesso.</div>
        </div>

        <div class="users-form-grid">
          <div class="users-form-field">
            <label class="admin-filter-label" for="usuario-papel">Papel</label>
            <select id="usuario-papel" name="papel" class="nerd-input admin-filter-control">
              <?php foreach ($papelOptions as $value => $label): ?>
                <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" <?= (string) ($form['papel'] ?? '') === (string) $value ? 'selected' : '' ?>><?= htmlspecialchars((string) $label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($fieldError('papel') !== ''): ?><div class="users-form-error"><?= htmlspecialchars($fieldError('papel'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
          </div>

          <div class="users-form-field">
            <label class="admin-filter-label" for="usuario-status">Status</label>
            <select id="usuario-status" name="status" class="nerd-input admin-filter-control">
              <?php foreach ($statusOptions as $value => $label): ?>
                <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" <?= (string) ($form['status'] ?? '') === (string) $value ? 'selected' : '' ?>><?= htmlspecialchars((string) $label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($fieldError('status') !== ''): ?><div class="users-form-error"><?= htmlspecialchars($fieldError('status'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
          </div>

          <div class="users-form-field">
            <label class="admin-filter-label" for="usuario-senha">Senha <?= $mode === 'edit' ? '<span class="text-slate-500">(opcional)</span>' : '' ?></label>
            <input id="usuario-senha" name="senha" type="password" value="" class="nerd-input admin-filter-control" placeholder="<?= $mode === 'edit' ? 'Preencha so para trocar' : 'Minimo de 6 caracteres' ?>">
            <?php if ($fieldError('senha') !== ''): ?><div class="users-form-error"><?= htmlspecialchars($fieldError('senha'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
          </div>

          <div class="users-form-field">
            <label class="admin-filter-label" for="usuario-senha-confirmacao">Confirmar senha</label>
            <input id="usuario-senha-confirmacao" name="senha_confirmacao" type="password" value="" class="nerd-input admin-filter-control" placeholder="Repita a senha informada">
            <?php if ($fieldError('senha_confirmacao') !== ''): ?><div class="users-form-error"><?= htmlspecialchars($fieldError('senha_confirmacao'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
          </div>
        </div>
      </section>

      <section class="admin-panel space-y-6">
        <div>
          <h2 class="admin-panel-title"><i class="fa-solid fa-image-portrait text-cyan-300"></i><span>Avatar</span></h2>
          <div class="admin-panel-subtitle">Escolha entre um icone padrao ou uma foto para identificar esse acesso no admin.</div>
        </div>

        <div class="users-avatar-editor">
          <div class="users-avatar-preview-card">
            <div class="users-avatar users-avatar-preview" data-avatar-preview<?php if (!$hasPhoto): ?> style="background: linear-gradient(135deg, <?= htmlspecialchars($avatarCor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>, rgba(15, 23, 42, 0.92));"<?php endif; ?>>
              <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($previewName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-avatar-preview-photo style="<?= htmlspecialchars($avatarFocusStyle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= $hasPhoto ? '' : ' hidden' ?>>
              <i class="<?= htmlspecialchars($avatarIcone, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-avatar-preview-icon<?= $hasPhoto ? ' hidden' : '' ?>></i>
            </div>
            <div class="users-avatar-preview-name" data-avatar-preview-name><?= htmlspecialchars($previewName !== '' ? $previewName : 'Usuario', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <div class="users-avatar-preview-meta" data-avatar-preview-meta><?= htmlspecialchars($hasPhoto ? 'Foto ativa no admin' : 'Icone ativo no admin', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          </div>

          <div class="users-avatar-controls">
            <div class="users-form-grid">
              <div class="users-form-field">
                <label class="admin-filter-label" for="usuario-avatar-tipo">Tipo de avatar</label>
                <select id="usuario-avatar-tipo" name="avatar_tipo" class="nerd-input admin-filter-control" data-avatar-type>
                  <option value="icone" <?= $avatarTipo === 'icone' ? 'selected' : '' ?>>Icone</option>
                  <option value="foto" <?= $avatarTipo === 'foto' ? 'selected' : '' ?>>Foto</option>
                </select>
                <?php if ($fieldError('avatar_tipo') !== ''): ?><div class="users-form-error"><?= htmlspecialchars($fieldError('avatar_tipo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
              </div>

              <div class="users-form-field" data-avatar-icon-fields<?= $avatarTipo === 'foto' ? ' hidden' : '' ?>>
                <label class="admin-filter-label" for="usuario-avatar-icone">Icone</label>
                <select id="usuario-avatar-icone" name="avatar_icone" class="nerd-input admin-filter-control" data-avatar-icon>
                  <?php foreach ($avatarIconOptions as $value => $label): ?>
                    <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" <?= $avatarIcone === (string) $value ? 'selected' : '' ?>><?= htmlspecialchars((string) $label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if ($fieldError('avatar_icone') !== ''): ?><div class="users-form-error"><?= htmlspecialchars($fieldError('avatar_icone'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
              </div>

              <div class="users-form-field" data-avatar-icon-fields<?= $avatarTipo === 'foto' ? ' hidden' : '' ?>>
                <label class="admin-filter-label" for="usuario-avatar-cor">Cor do icone</label>
                <input id="usuario-avatar-cor" name="avatar_cor" type="color" value="<?= htmlspecialchars($avatarCor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input admin-filter-control users-color-input" data-avatar-color>
                <?php if ($fieldError('avatar_cor') !== ''): ?><div class="users-form-error"><?= htmlspecialchars($fieldError('avatar_cor'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
              </div>

              <div class="users-form-field users-form-field-full" data-avatar-photo-fields<?= $avatarTipo === 'foto' ? '' : ' hidden' ?>>
                <label class="admin-filter-label" for="usuario-avatar-upload">Foto do avatar</label>
                <input id="usuario-avatar-upload" name="avatar_upload" type="file" accept=".jpg,.jpeg,.png,.webp,.gif,.svg" class="nerd-input admin-filter-control users-file-input" data-avatar-upload>
                <?php if ($fieldError('avatar_upload') !== ''): ?><div class="users-form-error"><?= htmlspecialchars($fieldError('avatar_upload'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
              </div>
            </div>

            <div class="users-avatar-focus-panel" data-avatar-focus-panel<?= $avatarTipo === 'foto' ? '' : ' hidden' ?>>
              <div class="users-avatar-focus-head">
                <div class="users-avatar-focus-title">Foco da foto</div>
                <div class="users-avatar-focus-copy">Clique na imagem para definir a regiao que deve aparecer melhor no avatar.</div>
              </div>
              <div class="users-avatar-focus-stage<?= $hasPhoto ? ' has-image' : '' ?>" data-avatar-focus-stage>
                <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($previewName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="users-avatar-focus-image" data-avatar-focus-image style="<?= htmlspecialchars($avatarFocusStyle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= $hasPhoto ? '' : ' hidden' ?>>
                <div class="users-avatar-focus-empty" data-avatar-focus-empty<?= $hasPhoto ? ' hidden' : '' ?>>Envie ou selecione uma foto para ajustar o foco do avatar.</div>
                <span class="users-avatar-focus-marker" data-avatar-focus-marker style="left: calc(<?= htmlspecialchars(number_format($avatarFocalX, 2, '.', ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>% - 10px); top: calc(<?= htmlspecialchars(number_format($avatarFocalY, 2, '.', ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>% - 10px);"<?= $hasPhoto ? '' : ' hidden' ?>></span>
              </div>
              <div class="users-avatar-focus-meta" data-avatar-focus-meta>Foco: <?= number_format($avatarFocalX, 0, ',', '.') ?>% x <?= number_format($avatarFocalY, 0, ',', '.') ?>%</div>
            </div>

            <?php if ($avatarUrl !== ''): ?>
              <div class="users-avatar-current-photo" data-avatar-photo-fields<?= $avatarTipo === 'foto' ? '' : ' hidden' ?>>
                <a href="<?= htmlspecialchars($avatarUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="admin-btn admin-btn-secondary"><i class="fa-solid fa-up-right-from-square"></i>Abrir foto atual</a>
                <label class="users-avatar-clear-toggle"><input type="checkbox" name="limpar_avatar_imagem" value="1" <?= (int) ($form['limpar_avatar_imagem'] ?? 0) === 1 ? 'checked' : '' ?>>Limpar foto salva</label>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </section>
    </div>

    <section class="admin-panel users-form-actions-panel">
      <div class="admin-page-actions">
        <a href="<?= url('/admin/usuarios') ?>" class="admin-btn admin-btn-secondary">Cancelar</a>
        <button type="submit" class="admin-btn admin-btn-primary"><i class="fa-solid fa-floppy-disk"></i><?= $mode === 'edit' ? 'Salvar alteracoes' : 'Criar usuario' ?></button>
      </div>
    </section>
  </form>
</div>
