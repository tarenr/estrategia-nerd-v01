<?php
/**
 * @file        app/Views/admin/instagram/index.php
 * @project     Estrategia Nerd
 * @purpose     Dashboard principal do módulo Instagram (FEAT-010)
 *
 * Variáveis disponíveis (injetadas pelo InstagramController::index()):
 * @var string                        $title
 * @var array<string,mixed>|null      $account      Conta ativa ou null se não configurada
 * @var array<int,array<string,mixed>> $scheduled    Posts agendados
 * @var array<int,array<string,mixed>> $drafts       Rascunhos
 * @var array<int,array<string,mixed>> $published    Publicados locais
 * @var array<int,array<string,mixed>> $feed         Feed ao vivo da Meta API
 * @var array<string,mixed>|null      $insights7     Insights 7d (cache)
 * @var array<string,mixed>|null      $insights30    Insights 30d (cache)
 */

declare(strict_types=1);

$account    = $account ?? null;
$scheduled  = $scheduled ?? [];
$drafts     = $drafts ?? [];
$published  = $published ?? [];
$feed       = $feed ?? [];
$insights7  = $insights7 ?? null;
$insights30 = $insights30 ?? null;

$fmt = static fn (int $v): string => number_format($v, 0, ',', '.');
$esc = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$excerpt = static function (?string $v, int $len = 60): string {
    $v = trim((string) $v);
    if ($v === '') {
        return '—';
    }
    return mb_strlen($v) > $len ? mb_substr($v, 0, $len) . '…' : $v;
};

$csrfToken = \App\Support\Csrf::token();

$statusLabels = ['rascunho' => 'Rascunho', 'agendado' => 'Agendado', 'publicando' => 'Publicando', 'publicado' => 'Publicado', 'erro' => 'Erro'];
$tipoLabels   = ['imagem' => 'Imagem', 'carrossel' => 'Carrossel', 'reels' => 'Reels', 'story' => 'Story'];

$totalPosts = count($scheduled) + count($drafts) + count($published);

$saved = isset($_GET['saved']) && (string) $_GET['saved'] === '1';
$publishedFlag = isset($_GET['published']) && (string) $_GET['published'] === '1';

// Normaliza o feed: prioriza a API ao vivo; se vazia, usa os publicados locais (formato diferente).
$feedItems = [];
if (!empty($feed)) {
    foreach ($feed as $m) {
        $feedItems[] = [
            'thumb'     => (string) ($m['thumbnail_url'] ?? $m['media_url'] ?? ''),
            'caption'   => (string) ($m['caption'] ?? ''),
            'likes'     => isset($m['like_count']) ? (int) $m['like_count'] : null,
            'comments'  => isset($m['comments_count']) ? (int) $m['comments_count'] : null,
            'permalink' => (string) ($m['permalink'] ?? ''),
        ];
    }
} else {
    foreach ($published as $p) {
        $paths     = array_values(array_filter(explode('|', (string) ($p['medias'] ?? ''))));
        $firstPath = $paths[0] ?? '';
        $thumb     = $firstPath === '' ? '' : (str_starts_with($firstPath, 'http') ? $firstPath : url('/' . ltrim($firstPath, '/')));
        $feedItems[] = [
            'thumb'     => $thumb,
            'caption'   => (string) ($p['legenda'] ?? ''),
            'likes'     => null,
            'comments'  => null,
            'permalink' => (string) ($p['permalink'] ?? ''),
        ];
    }
}
?>

<div class="max-w-7xl mx-auto" data-instagram-index-root>
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title"><i class="fa-brands fa-instagram text-pink-400" aria-hidden="true"></i> Instagram</h1>
      <div class="admin-page-subtitle">Conta, métricas, feed e fila de publicação do Instagram direto do admin.</div>
    </div>

    <div class="admin-page-actions">
      <div class="admin-chip">Total de posts: <?= $fmt($totalPosts) ?></div>
      <a href="<?= url('/admin/instagram/posts/criar') ?>" class="admin-btn admin-btn-primary">
        <i class="fa-solid fa-plus" aria-hidden="true"></i> Novo Post
      </a>
    </div>
  </div>

  <div class="space-y-6 mt-6">
    <?php if ($saved): ?>
      <section class="admin-panel border border-emerald-500/30"><div class="text-sm font-bold text-emerald-300">Post salvo com sucesso.</div></section>
    <?php endif; ?>
    <?php if ($publishedFlag): ?>
      <section class="admin-panel border border-emerald-500/30"><div class="text-sm font-bold text-emerald-300">Post enviado para publicação. Acompanhe o status na lista abaixo.</div></section>
    <?php endif; ?>

    <?php if ($account === null): ?>
      <section class="admin-panel border border-amber-500/30">
        <div class="admin-panel-title"><i class="fa-solid fa-triangle-exclamation text-amber-300" aria-hidden="true"></i><span>Nenhuma conta Instagram configurada</span></div>
        <div class="admin-panel-subtitle mt-2">
          Insira a conta diretamente no banco de dados para ativar o módulo:
        </div>
        <pre class="mt-3 rounded-xl border border-slate-800 bg-slate-950/70 p-4 text-xs text-slate-300 overflow-x-auto">INSERT INTO instagram_accounts (ig_user_id, username, access_token, ativo)
VALUES ('SEU_IG_USER_ID', 'estrategia_nerd', 'SEU_ACCESS_TOKEN_PERMANENTE', 1);</pre>
        <div class="mt-3 text-xs text-slate-400">O token precisa ser um Long-Lived Token da Graph API. Detalhes em <code>docs/features/FEAT-010.md</code>.</div>
      </section>
    <?php else: ?>

      <!-- Status da conexão + Sincronizar -->
      <section class="admin-panel">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div class="flex items-center gap-3">
            <?php $synced = trim((string) ($account['synced_at'] ?? '')); ?>
            <span class="admin-chip <?= $synced !== '' ? 'border-emerald-500/40 text-emerald-200' : 'border-slate-600 text-slate-300' ?>">
              <i class="fa-solid fa-circle <?= $synced !== '' ? 'text-emerald-400' : 'text-slate-500' ?> text-[8px]" aria-hidden="true"></i>
              <?= $synced !== '' ? 'Conectado' : 'Nunca sincronizado' ?>
            </span>
            <span class="text-xs text-slate-400" data-ig-synced-label>
              <?= $synced !== '' ? 'Última sincronização: ' . $esc(date('d/m/Y H:i', strtotime($synced) ?: time())) : 'Sincronize para trazer os dados mais recentes da Meta.' ?>
            </span>
          </div>
          <button type="button" class="admin-btn admin-btn-secondary" data-ig-sync-btn data-csrf="<?= $esc($csrfToken) ?>">
            <i class="fa-solid fa-rotate" aria-hidden="true"></i> <span data-ig-sync-label>Sincronizar Agora</span>
          </button>
        </div>
        <div class="mt-2 text-xs" data-ig-sync-feedback></div>
      </section>

      <!-- Header do perfil -->
      <section class="admin-panel">
        <div class="flex flex-wrap items-center gap-5">
          <div class="h-16 w-16 shrink-0 rounded-full overflow-hidden border border-slate-700 bg-slate-800 flex items-center justify-center">
            <?php $pic = trim((string) ($account['profile_picture'] ?? '')); ?>
            <?php if ($pic !== ''): ?>
              <img src="<?= $esc($pic) ?>" alt="Foto de perfil" class="h-full w-full object-cover">
            <?php else: ?>
              <i class="fa-brands fa-instagram text-2xl text-slate-500" aria-hidden="true"></i>
            <?php endif; ?>
          </div>
          <div class="min-w-0 flex-1">
            <div class="text-lg font-black text-white">@<?= $esc((string) ($account['username'] ?? '')) ?></div>
            <?php $bio = trim((string) ($account['bio'] ?? '')); ?>
            <?php if ($bio !== ''): ?>
              <div class="text-sm text-slate-300 mt-1"><?= $esc($excerpt($bio, 140)) ?></div>
            <?php endif; ?>
          </div>
          <div class="flex items-center gap-5 text-center">
            <div>
              <div class="flex items-center justify-center gap-1.5">
                <span class="text-lg font-black text-white"><?= $fmt((int) ($account['followers_count'] ?? 0)) ?></span>
                <?php
                $delta7 = (int) ($insights7['variacao_seguidores'] ?? 0);
                if ($delta7 > 0): ?>
                  <span class="text-xs font-bold text-emerald-400" title="Variação recente">+<?= $fmt($delta7) ?></span>
                <?php elseif ($delta7 < 0): ?>
                  <span class="text-xs font-bold text-rose-400" title="Variação recente"><?= $fmt($delta7) ?></span>
                <?php endif; ?>
              </div>
              <div class="text-[11px] uppercase tracking-wide text-slate-400">Seguidores</div>
            </div>
            <div>
              <div class="text-lg font-black text-white"><?= $fmt((int) ($account['follows_count'] ?? 0)) ?></div>
              <div class="text-[11px] uppercase tracking-wide text-slate-400">Seguindo</div>
            </div>
            <div>
              <div class="text-lg font-black text-white"><?= $fmt((int) ($account['media_count'] ?? 0)) ?></div>
              <div class="text-[11px] uppercase tracking-wide text-slate-400">Mídias</div>
            </div>
          </div>
        </div>
      </section>

      <!-- Métricas -->
      <section class="admin-panel">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="admin-panel-title"><i class="fa-solid fa-chart-simple text-cyan-300" aria-hidden="true"></i><span>Métricas</span></div>
          <div class="inline-flex rounded-xl border border-slate-700 overflow-hidden text-xs font-bold" data-ig-period-toggle>
            <button type="button" class="px-3 py-1.5 bg-cyan-500/20 text-cyan-200" data-ig-period="7" aria-pressed="true">7 dias</button>
            <button type="button" class="px-3 py-1.5 text-slate-400" data-ig-period="30" aria-pressed="false">30 dias</button>
          </div>
        </div>

        <?php
        $metricsMap = [
            '7'  => $insights7,
            '30' => $insights30,
        ];
        ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
          <?php foreach (['alcance' => ['Alcance', 'fa-eye', '#00d4ff'], 'impressoes' => ['Impressões', 'fa-chart-column', '#facc15'], 'visitas_perfil' => ['Visitas ao perfil', 'fa-user-check', '#34d399'], 'interacoes' => ['Interações', 'fa-heart', '#f472b6']] as $metricKey => [$metricLabel, $metricIcon, $metricColor]): ?>
            <div class="stat-card stat-card-compact admin-summary-card" data-ig-metric-card="<?= $esc($metricKey) ?>">
              <div class="stat-icon" style="background: <?= $esc($metricColor) ?>22; color: <?= $esc($metricColor) ?>;">
                <i class="fa-solid <?= $esc($metricIcon) ?>" aria-hidden="true"></i>
              </div>
              <div class="stat-value neon-text" style="color: <?= $esc($metricColor) ?>;" data-ig-metric-value="7">
                <?= $fmt((int) ($metricsMap['7'][$metricKey] ?? 0)) ?>
              </div>
              <div class="stat-value neon-text hidden" style="color: <?= $esc($metricColor) ?>;" data-ig-metric-value="30">
                <?= $fmt((int) ($metricsMap['30'][$metricKey] ?? 0)) ?>
              </div>
              <div class="stat-label"><?= $esc($metricLabel) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if ($insights7 === null && $insights30 === null): ?>
          <div class="mt-3 text-xs text-slate-400">Sem dados de métricas ainda — sincronize a conta para trazer o primeiro snapshot.</div>
        <?php endif; ?>
      </section>

      <!-- Feed visual -->
      <section class="admin-panel">
        <div class="admin-panel-title"><i class="fa-solid fa-images text-violet-300" aria-hidden="true"></i><span>Feed recente</span></div>
        <?php if ($feedItems === []): ?>
          <div class="mt-3 text-sm text-slate-400">Nenhuma mídia encontrada ainda.</div>
        <?php else: ?>
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 mt-4">
            <?php foreach ($feedItems as $item): ?>
              <a href="<?= $item['permalink'] !== '' ? $esc($item['permalink']) : '#' ?>" target="_blank" rel="noopener noreferrer" class="block rounded-xl border border-slate-800 bg-slate-900/60 overflow-hidden hover:border-cyan-500/40 transition">
                <div class="aspect-square bg-slate-800 flex items-center justify-center overflow-hidden">
                  <?php if ($item['thumb'] !== ''): ?>
                    <img src="<?= $esc($item['thumb']) ?>" alt="" class="h-full w-full object-cover" loading="lazy">
                  <?php else: ?>
                    <i class="fa-brands fa-instagram text-3xl text-slate-600" aria-hidden="true"></i>
                  <?php endif; ?>
                </div>
                <div class="p-2.5">
                  <div class="text-xs text-slate-300 leading-4"><?= $esc($excerpt($item['caption'], 50)) ?></div>
                  <?php if ($item['likes'] !== null || $item['comments'] !== null): ?>
                    <div class="mt-1.5 flex items-center gap-3 text-[11px] text-slate-500">
                      <?php if ($item['likes'] !== null): ?><span><i class="fa-solid fa-heart" aria-hidden="true"></i> <?= $fmt($item['likes']) ?></span><?php endif; ?>
                      <?php if ($item['comments'] !== null): ?><span><i class="fa-solid fa-comment" aria-hidden="true"></i> <?= $fmt($item['comments']) ?></span><?php endif; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <!-- Agendados -->
      <section class="admin-panel">
        <div class="admin-panel-title"><i class="fa-solid fa-clock text-amber-300" aria-hidden="true"></i><span>Agendados</span></div>
        <?php if ($scheduled === []): ?>
          <div class="mt-3 text-sm text-slate-400">Nenhum post agendado.</div>
        <?php else: ?>
          <div class="overflow-x-auto mt-3">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-800">
                  <th class="py-2 pr-4">Data/hora</th>
                  <th class="py-2 pr-4">Tipo</th>
                  <th class="py-2 pr-4">Legenda</th>
                  <th class="py-2 pr-4">Ação</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($scheduled as $p): ?>
                  <tr class="border-b border-slate-800/60">
                    <td class="py-2 pr-4 text-slate-300 whitespace-nowrap"><?= $esc(($p['agendado_para'] ?? null) !== null ? date('d/m/Y H:i', strtotime((string) $p['agendado_para']) ?: time()) : '—') ?></td>
                    <td class="py-2 pr-4 text-slate-300"><?= $esc($tipoLabels[(string) ($p['tipo'] ?? '')] ?? (string) ($p['tipo'] ?? '')) ?></td>
                    <td class="py-2 pr-4 text-slate-400"><?= $esc($excerpt((string) ($p['legenda'] ?? ''), 60)) ?></td>
                    <td class="py-2 pr-4"><a href="<?= url('/admin/instagram/posts/' . (int) ($p['id'] ?? 0) . '/editar') ?>" class="admin-btn admin-btn-secondary !px-3 !py-1.5 text-xs">Editar</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <!-- Rascunhos -->
      <section class="admin-panel">
        <div class="admin-panel-title"><i class="fa-solid fa-pen-to-square text-slate-300" aria-hidden="true"></i><span>Rascunhos</span></div>
        <?php if ($drafts === []): ?>
          <div class="mt-3 text-sm text-slate-400">Nenhum rascunho.</div>
        <?php else: ?>
          <div class="overflow-x-auto mt-3">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-800">
                  <th class="py-2 pr-4">Tipo</th>
                  <th class="py-2 pr-4">Legenda</th>
                  <th class="py-2 pr-4">Ação</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($drafts as $p): ?>
                  <tr class="border-b border-slate-800/60">
                    <td class="py-2 pr-4 text-slate-300"><?= $esc($tipoLabels[(string) ($p['tipo'] ?? '')] ?? (string) ($p['tipo'] ?? '')) ?></td>
                    <td class="py-2 pr-4 text-slate-400"><?= $esc($excerpt((string) ($p['legenda'] ?? ''), 60)) ?></td>
                    <td class="py-2 pr-4"><a href="<?= url('/admin/instagram/posts/' . (int) ($p['id'] ?? 0) . '/editar') ?>" class="admin-btn admin-btn-secondary !px-3 !py-1.5 text-xs">Editar</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

    <?php endif; ?>
  </div>
</div>

<?php if ($account !== null): ?>
<script>
(function () {
  var syncBtn = document.querySelector('[data-ig-sync-btn]');
  if (syncBtn) {
    syncBtn.addEventListener('click', function () {
      var label = syncBtn.querySelector('[data-ig-sync-label]');
      var feedback = document.querySelector('[data-ig-sync-feedback]');
      var originalLabel = label ? label.textContent : 'Sincronizar Agora';

      syncBtn.disabled = true;
      if (label) { label.textContent = 'Sincronizando...'; }
      if (feedback) { feedback.textContent = ''; feedback.className = 'mt-2 text-xs'; }

      var body = new URLSearchParams();
      body.set('_csrf_token', syncBtn.getAttribute('data-csrf') || '');

      fetch('<?= url('/admin/instagram/sincronizar') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
      })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
          if (result.ok && result.data && result.data.ok) {
            if (feedback) {
              feedback.textContent = 'Sincronizado com sucesso em ' + (result.data.synced_at || '') + '. Atualize a página para ver os dados novos.';
              feedback.className = 'mt-2 text-xs text-emerald-300';
            }
          } else {
            if (feedback) {
              feedback.textContent = 'Falha ao sincronizar: ' + ((result.data && result.data.error) || 'erro desconhecido.');
              feedback.className = 'mt-2 text-xs text-rose-300';
            }
          }
        })
        .catch(function () {
          if (feedback) {
            feedback.textContent = 'Falha de rede ao tentar sincronizar.';
            feedback.className = 'mt-2 text-xs text-rose-300';
          }
        })
        .finally(function () {
          syncBtn.disabled = false;
          if (label) { label.textContent = originalLabel; }
        });
    });
  }

  var toggleWrap = document.querySelector('[data-ig-period-toggle]');
  if (toggleWrap) {
    toggleWrap.addEventListener('click', function (event) {
      var btn = event.target.closest('[data-ig-period]');
      if (!btn) { return; }
      var period = btn.getAttribute('data-ig-period');

      toggleWrap.querySelectorAll('[data-ig-period]').forEach(function (b) {
        var active = b === btn;
        b.setAttribute('aria-pressed', active ? 'true' : 'false');
        b.classList.toggle('bg-cyan-500/20', active);
        b.classList.toggle('text-cyan-200', active);
        b.classList.toggle('text-slate-400', !active);
      });

      document.querySelectorAll('[data-ig-metric-value]').forEach(function (el) {
        el.classList.toggle('hidden', el.getAttribute('data-ig-metric-value') !== period);
      });
    });
  }
})();
</script>
<?php endif; ?>
