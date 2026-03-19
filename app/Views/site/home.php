<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Views/site/home.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Página inicial do site
 * @description Home pública com status de autenticação.
 * @usage       View::render('site.home', [...], 'site')
 * @notes       Não usar $content aqui (isso é do layout).
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

use App\Support\Auth;

$user = Auth::user();
?>

<h1>Home</h1>

<?php if (Auth::check()): ?>
    <p>Você está logado como: <strong><?= htmlspecialchars((string)($user['usuario'] ?? 'usuário'), ENT_QUOTES, 'UTF-8') ?></strong></p>
    <p><a href="<?= htmlspecialchars(url('/admin'), ENT_QUOTES, 'UTF-8') ?>">Ir para Admin</a></p>
<?php else: ?>
    <p>Você não está logado.</p>
    <p><a href="<?= htmlspecialchars(url('/login'), ENT_QUOTES, 'UTF-8') ?>">Ir para Login</a></p>
<?php endif; ?>

<p><a href="<?= htmlspecialchars(url('/dev'), ENT_QUOTES, 'UTF-8') ?>">Abrir Painel DEV</a></p>