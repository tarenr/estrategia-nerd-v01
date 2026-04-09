<?php

declare(strict_types=1);

use App\Support\Csrf;

$sections = is_array($sections ?? null) ? $sections : [];
$errors = is_array($errors ?? null) ? $errors : [];
$action = (string) ($action ?? url('/admin/home-e-menus'));
?>

<section class="admin-panel home-menus-panel">
  <form method="POST" action="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="space-y-5">
    <?= Csrf::field() ?>

    <div class="posts-table-head">
      <div>
        <h3 class="font-orbitron text-xl font-black text-white">Estrutura publica</h3>
        <div class="text-xs text-slate-400 mt-1">A home, o menu e os modulos publicos passam a obedecer exatamente esta tabela.</div>
      </div>
      <button type="submit" class="admin-btn admin-btn-primary">Salvar estrutura</button>
    </div>

    <div class="home-menus-note-grid">
      <article class="home-menus-note-card">
        <h4>Blog</h4>
        <p>Quando o modulo publico do blog for desligado, o portal remove <strong>/blog</strong>, <strong>/post/{slug}</strong>, curtidas e comentarios do publico.</p>
      </article>
      <article class="home-menus-note-card">
        <h4>Central Nerd</h4>
        <p>Quando a Central Nerd for desligada, o portal remove <strong>/central-nerd</strong> e tambem bloqueia <strong>/link/{slug}</strong> no publico.</p>
      </article>
    </div>

    <div class="posts-table-wrap home-menus-table-wrap">
      <table class="home-menus-table">
        <colgroup>
          <col class="home-menus-col-module">
          <col class="home-menus-col-label">
          <col class="home-menus-col-order">
          <col class="home-menus-col-flag">
          <col class="home-menus-col-flag">
          <col class="home-menus-col-flag">
        </colgroup>
        <thead class="posts-table-thead">
          <tr>
            <th class="posts-table-th posts-table-th-left">Modulo</th>
            <th class="posts-table-th posts-table-th-left">Rotulo publico</th>
            <th class="posts-table-th posts-table-th-center">Ordem</th>
            <th class="posts-table-th posts-table-th-center">Home</th>
            <th class="posts-table-th posts-table-th-center">Menu</th>
            <th class="posts-table-th posts-table-th-center">Modulo publico</th>
          </tr>
        </thead>
        <tbody class="posts-table-body">
          <?php foreach ($sections as $key => $section): ?>
            <?php
            $labelError = (string) ($errors[$key . '.label'] ?? '');
            $supportsHome = (bool) ($section['supports_home'] ?? false);
            $supportsMenu = (bool) ($section['supports_menu'] ?? false);
            $targetPreview = (string) ($section['route_type'] ?? 'route') === 'anchor'
                ? rtrim(url('/'), '/') . (string) ($section['path'] ?? '')
                : url((string) ($section['path'] ?? '/'));
            ?>
            <tr class="posts-table-row">
              <td class="posts-table-td home-menus-module-cell">
                <div class="home-menus-module-title"><?= htmlspecialchars((string) ($section['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="home-menus-module-desc"><?= htmlspecialchars((string) ($section['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="home-menus-module-meta">
                  <span class="home-menus-module-chip"><?= htmlspecialchars($targetPreview, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                  <span class="home-menus-module-note"><?= htmlspecialchars((string) ($section['public_note'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                </div>
              </td>
              <td class="posts-table-td home-menus-label-cell">
                <input
                  type="text"
                  name="sections[<?= htmlspecialchars((string) $key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>][label]"
                  value="<?= htmlspecialchars((string) ($section['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                  maxlength="60"
                  class="home-menus-text-input<?= $labelError !== '' ? ' is-error' : '' ?>"
                >
                <?php if ($labelError !== ''): ?>
                  <div class="home-menus-field-error"><?= htmlspecialchars($labelError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <?php endif; ?>
              </td>
              <td class="posts-table-td posts-table-td-center home-menus-order-cell">
                <input
                  type="number"
                  min="1"
                  max="99"
                  name="sections[<?= htmlspecialchars((string) $key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>][order]"
                  value="<?= (int) ($section['order'] ?? 1) ?>"
                  class="home-menus-order-input"
                >
              </td>
              <td class="posts-table-td posts-table-td-center">
                <?php if ($supportsHome): ?>
                  <div class="home-menus-switch-cell">
                    <input type="hidden" name="sections[<?= htmlspecialchars((string) $key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>][show_home]" value="0">
                    <label class="home-menus-switch">
                      <input type="checkbox" name="sections[<?= htmlspecialchars((string) $key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>][show_home]" value="1" <?= !empty($section['show_home']) ? 'checked' : '' ?>>
                      <span class="home-menus-switch-track"></span>
                    </label>
                    <span class="home-menus-switch-note"><?= !empty($section['show_home']) ? 'Visivel' : 'Oculto' ?></span>
                  </div>
                <?php else: ?>
                  <span class="home-menus-static-badge">Fora da home</span>
                <?php endif; ?>
              </td>
              <td class="posts-table-td posts-table-td-center">
                <?php if ($supportsMenu): ?>
                  <div class="home-menus-switch-cell">
                    <input type="hidden" name="sections[<?= htmlspecialchars((string) $key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>][show_menu]" value="0">
                    <label class="home-menus-switch">
                      <input type="checkbox" name="sections[<?= htmlspecialchars((string) $key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>][show_menu]" value="1" <?= !empty($section['show_menu']) ? 'checked' : '' ?>>
                      <span class="home-menus-switch-track"></span>
                    </label>
                    <span class="home-menus-switch-note"><?= !empty($section['show_menu']) ? 'Visivel' : 'Oculto' ?></span>
                  </div>
                <?php else: ?>
                  <span class="home-menus-static-badge">Sem menu</span>
                <?php endif; ?>
              </td>
              <td class="posts-table-td posts-table-td-center">
                <div class="home-menus-switch-cell">
                  <input type="hidden" name="sections[<?= htmlspecialchars((string) $key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>][public_active]" value="0">
                  <label class="home-menus-switch">
                    <input type="checkbox" name="sections[<?= htmlspecialchars((string) $key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>][public_active]" value="1" <?= !empty($section['public_active']) ? 'checked' : '' ?>>
                    <span class="home-menus-switch-track"></span>
                  </label>
                  <span class="home-menus-switch-note"><?= !empty($section['public_active']) ? 'Ativo' : 'Desligado' ?></span>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="flex justify-end">
      <button type="submit" class="admin-btn admin-btn-primary">Salvar estrutura</button>
    </div>
  </form>
</section>