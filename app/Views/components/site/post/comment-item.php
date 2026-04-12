<?php
declare(strict_types=1);

use App\Support\Csrf;
use App\Support\View;

$comment = is_array($comment ?? null) ? $comment : [];
$level = (int) ($level ?? 0);
$isHidden = (bool) ($is_hidden ?? false);
$postSlug = (string) ($post_slug ?? '');
$brandSymbol = (string) ($brand_symbol ?? '');
$isAdmin = (bool) ($comment['is_admin'] ?? false);
$commentName = trim((string) ($comment['nome'] ?? 'Leitor'));
if ($commentName === '') {
    $commentName = 'Leitor';
}
$initialsSource = function_exists('mb_substr') ? mb_substr($commentName, 0, 2) : substr($commentName, 0, 2);
$commentInitials = strtoupper((string) $initialsSource);

$adminAvatarType = trim((string) ($comment['admin_avatar_type'] ?? ''));
$adminAvatarIcon = trim((string) ($comment['admin_avatar_icon'] ?? 'fa-solid fa-user'));
$adminAvatarColor = trim((string) ($comment['admin_avatar_color'] ?? '#38bdf8'));
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $adminAvatarColor)) {
    $adminAvatarColor = '#38bdf8';
}
$adminAvatarImage = trim((string) ($comment['admin_avatar_image'] ?? ''));
$adminAvatarFocalX = max(0.0, min(100.0, (float) ($comment['admin_avatar_focal_x'] ?? 50.0)));
$adminAvatarFocalY = max(0.0, min(100.0, (float) ($comment['admin_avatar_focal_y'] ?? 50.0)));
$adminAvatarObjectPosition = 'object-position: ' . number_format($adminAvatarFocalX, 2, '.', '') . '% ' . number_format($adminAvatarFocalY, 2, '.', '') . '%;';

$avatarClassName = 'comment-avatar' . ($isAdmin ? ' comment-avatar-admin' : '');
$avatarStyle = '';
if ($isAdmin && $adminAvatarType === 'foto' && $adminAvatarImage !== '') {
    $avatarClassName .= ' has-photo';
} elseif ($isAdmin) {
    $avatarClassName .= ' has-icon';
    $avatarStyle = '--comment-admin-avatar-color: ' . $adminAvatarColor . ';';
}

$className = 'comment';
if ($level > 0) {
    $className .= ' comment-child';
}
if ($isAdmin) {
    $className .= ' comment-admin';
}
if ($isHidden) {
    $className .= ' comment-hidden';
}
?>
<div class="<?= htmlspecialchars($className, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-comment-item data-comment-level="<?= $level ?>"<?= $level === 0 ? ' data-comment-root' : '' ?><?= $isHidden ? ' hidden' : '' ?>>
  <div class="comment-header">
    <div class="comment-header-top">
      <div class="comment-author">
        <div class="<?= htmlspecialchars($avatarClassName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= $avatarStyle !== '' ? ' style="' . htmlspecialchars($avatarStyle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : '' ?>>
          <?php if ($isAdmin && $adminAvatarType === 'foto' && $adminAvatarImage !== ''): ?>
            <img src="<?= htmlspecialchars($adminAvatarImage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($commentName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="comment-admin-user-photo" style="<?= htmlspecialchars($adminAvatarObjectPosition, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
          <?php elseif ($isAdmin && $adminAvatarIcon !== ''): ?>
            <i class="<?= htmlspecialchars($adminAvatarIcon, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> comment-admin-user-icon" aria-hidden="true"></i>
          <?php elseif ($isAdmin && $brandSymbol !== ''): ?>
            <img src="<?= htmlspecialchars($brandSymbol, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="Equipe Estratégia Nerd" class="comment-admin-mark">
          <?php else: ?>
            <?= htmlspecialchars($commentInitials, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
          <?php endif; ?>
        </div>
        <div>
          <div class="comment-author-name">
            <?= htmlspecialchars($commentName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            <?php if ($isAdmin): ?>
              <span class="comment-admin-badge">Equipe</span>
            <?php endif; ?>
          </div>
          <div class="comment-date"><?= htmlspecialchars((string) ($comment['data'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        </div>
      </div>
      <?php if ($level === 0): ?>
        <div class="comment-actions comment-actions-top">
          <button type="button" class="comment-reply-toggle" data-reply-toggle="<?= (int) ($comment['id'] ?? 0) ?>">Responder</button>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="comment-body"><?= (string) ($comment['comentario'] ?? '') ?></div>

  <?php if ($level === 0): ?>
    <form method="POST" action="<?= htmlspecialchars(url('/post/' . $postSlug . '/comentarios'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="comment-reply-form" data-reply-form="<?= (int) ($comment['id'] ?? 0) ?>" hidden>
      <?= Csrf::field() ?>
      <input type="hidden" name="parent_id" value="<?= (int) ($comment['id'] ?? 0) ?>">
      <div class="grid md:grid-cols-2 gap-3 mb-3">
        <input type="text" name="nome" placeholder="Seu nome" class="site-form-input">
        <input type="email" name="email" placeholder="seu@email.com" class="site-form-input">
      </div>
      <textarea name="comentario" rows="3" placeholder="Escreva sua resposta..." class="site-form-textarea"></textarea>
      <div class="mt-3 flex justify-end">
        <button type="submit" class="site-nav-cta">Publicar resposta</button>
      </div>
    </form>
  <?php endif; ?>

  <?php if (($comment['children'] ?? []) !== []): ?>
    <div class="comment-children" data-comment-children="<?= (int) ($comment['id'] ?? 0) ?>">
      <?php foreach (($comment['children'] ?? []) as $child): ?>
        <?php View::component('site/post/comment-item', [
            'comment' => $child,
            'level' => $level + 1,
            'post_slug' => $postSlug,
            'is_hidden' => false,
            'brand_symbol' => $brandSymbol,
        ]); ?>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="comment-children" data-comment-children="<?= (int) ($comment['id'] ?? 0) ?>"></div>
  <?php endif; ?>
</div>