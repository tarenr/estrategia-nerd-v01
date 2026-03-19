<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Views/site/dev.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Painel DEV (diagnóstico)
 * @description Exibe diagnósticos do ambiente, rotas, banco e camada Support da arquitetura nova.
 * @usage       Renderizado por Site\DevController em GET /dev.
 * @notes       Usa App\Support\Auth/Csrf e namespaces completos do projeto.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

use App\Support\Auth;
use App\Support\Csrf;

$phpVersion = PHP_VERSION;
$timezone = date_default_timezone_get();
$currentUrl = ($_SERVER['REQUEST_METHOD'] ?? 'GET') . ' ' . (($_SERVER['REQUEST_URI'] ?? '/') ?: '/');

$pdoOk = false;
$pdoError = null;

try {
    $pdo = $GLOBALS['pdo'] ?? null;
    $pdoOk = $pdo instanceof PDO;
    if (!$pdoOk) {
        $pdoError = 'PDO não inicializado em $GLOBALS[\'pdo\'].';
    }
} catch (Throwable $e) {
    $pdoError = $e->getMessage();
}

$autoloaders = spl_autoload_functions() ?: [];
$autoloadCount = count($autoloaders);

$repoClass = \App\Repositories\UsuarioRepository::class;
$repoFileExpected = base_path('app/Repositories/UsuarioRepository.php');
$repoFileExists = is_file($repoFileExpected);
$repoClassExists = class_exists($repoClass);

$repoOk = false;
$repoError = null;
try {
    if ($pdoOk && $repoClassExists) {
        new \App\Repositories\UsuarioRepository($GLOBALS['pdo']);
        $repoOk = true;
    } else {
        $repoError = !$repoClassExists ? 'Classe não carregou (namespace/autoload).' : 'PDO não disponível.';
    }
} catch (Throwable $e) {
    $repoError = $e->getMessage();
}
?>

<div class="max-w-6xl mx-auto px-4 py-10">
  <h1 class="font-orbitron text-2xl font-black tracking-wider mb-1">
    Header do Painel <span class="text-cyan-400">DEV</span>
  </h1>
  <p class="text-slate-400 text-sm mb-6">Rotas: Home / Login / Admin / Dev</p>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <section class="rounded-2xl border border-slate-800/70 bg-slate-950/40 backdrop-blur p-6">
      <h2 class="font-orbitron text-lg font-bold tracking-wider mb-3">Ambiente</h2>

      <div class="space-y-2 text-sm">
        <div class="flex items-center justify-between gap-4">
          <span class="text-slate-400">PHP:</span>
          <span class="text-slate-200 font-semibold"><?= htmlspecialchars($phpVersion) ?></span>
        </div>
        <div class="flex items-center justify-between gap-4">
          <span class="text-slate-400">Timezone:</span>
          <span class="text-slate-200 font-semibold"><?= htmlspecialchars($timezone) ?></span>
        </div>
        <div class="flex items-center justify-between gap-4">
          <span class="text-slate-400">URL atual:</span>
          <span class="text-slate-200 font-semibold"><?= htmlspecialchars($currentUrl) ?></span>
        </div>
      </div>
    </section>

    <section class="rounded-2xl border border-slate-800/70 bg-slate-950/40 backdrop-blur p-6">
      <h2 class="font-orbitron text-lg font-bold tracking-wider mb-3">Banco / PDO</h2>

      <?php if ($pdoOk): ?>
        <div class="text-sm text-emerald-300 font-semibold">OK</div>
        <div class="text-xs text-slate-500 mt-2">PDO inicializado em <code>$GLOBALS['pdo']</code>.</div>
      <?php else: ?>
        <div class="text-sm text-red-300 font-semibold">FAIL</div>
        <div class="text-xs text-slate-400 mt-2">Erro: <?= htmlspecialchars((string)$pdoError) ?></div>
      <?php endif; ?>
    </section>

    <section class="rounded-2xl border border-slate-800/70 bg-slate-950/40 backdrop-blur p-6">
      <h2 class="font-orbitron text-lg font-bold tracking-wider mb-3">Support / Auth</h2>

      <ul class="text-sm text-slate-300 space-y-2">
        <li><span class="text-slate-400">check():</span> <span class="font-semibold"><?= Auth::check() ? 'true' : 'false' ?></span></li>
        <li><span class="text-slate-400">id():</span> <span class="font-semibold"><?= htmlspecialchars((string)(Auth::id() ?? 'null')) ?></span></li>
        <li><span class="text-slate-400">user():</span> <span class="font-semibold"><?= htmlspecialchars((string)((Auth::user()['usuario'] ?? null) ?? 'null')) ?></span></li>
      </ul>
    </section>

    <section class="rounded-2xl border border-slate-800/70 bg-slate-950/40 backdrop-blur p-6">
      <h2 class="font-orbitron text-lg font-bold tracking-wider mb-3">CSRF</h2>

      <div class="text-sm text-emerald-300 font-semibold">OK</div>
      <div class="text-xs text-slate-400 mt-2">field() pronto (name=_csrf_token)</div>

      <div class="mt-3 text-xs text-slate-400">
        Exemplo:
        <div class="mt-2 rounded-xl border border-slate-800/70 bg-slate-950/60 p-3 overflow-auto">
          <code><?= htmlspecialchars(Csrf::field()) ?></code>
        </div>
      </div>
    </section>

    <section class="rounded-2xl border border-slate-800/70 bg-slate-950/40 backdrop-blur p-6 lg:col-span-2">
      <h2 class="font-orbitron text-lg font-bold tracking-wider mb-3">Autoload / Diagnóstico</h2>

      <div class="text-sm <?= $autoloadCount > 0 ? 'text-emerald-300' : 'text-red-300' ?> font-semibold">
        <?= $autoloadCount > 0 ? 'OK' : 'FAIL' ?>
      </div>

      <div class="mt-2 text-sm text-slate-300 space-y-1">
        <div><span class="text-slate-400">autoload registrado(s):</span> <span class="font-semibold"><?= (int)$autoloadCount ?></span></div>
        <div><span class="text-slate-400">Arquivo esperado:</span> <code><?= htmlspecialchars($repoFileExpected) ?></code></div>
        <div><span class="text-slate-400">Existe arquivo?:</span> <span class="font-semibold"><?= $repoFileExists ? 'sim' : 'não' ?></span></div>
        <div><span class="text-slate-400">class_exists(App\\Repositories\\UsuarioRepository):</span> <span class="font-semibold"><?= $repoClassExists ? 'true' : 'false' ?></span></div>
      </div>
    </section>

    <section class="rounded-2xl border border-slate-800/70 bg-slate-950/40 backdrop-blur p-6 lg:col-span-2">
      <h2 class="font-orbitron text-lg font-bold tracking-wider mb-3">Repository / UsuarioRepository</h2>

      <?php if ($repoOk): ?>
        <div class="text-sm text-emerald-300 font-semibold">OK</div>
      <?php else: ?>
        <div class="text-sm text-red-300 font-semibold">FAIL</div>
        <div class="text-xs text-slate-400 mt-2">Erro: <?= htmlspecialchars((string)$repoError) ?></div>
      <?php endif; ?>
    </section>
  </div>

  <div class="mt-8 text-xs text-slate-500">
    Footer do Painel DEV<br>
    Tudo ok até aqui.<br>
    © <?= date('Y') ?> Estratégia Nerd
  </div>
</div>