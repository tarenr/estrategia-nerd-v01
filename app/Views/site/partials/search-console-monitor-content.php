<?php

declare(strict_types=1);

$monitorSection = (string) ($monitor_section ?? 'resumo');
$searchConsole = is_array($search_console ?? null) ? $search_console : [];
$inspectionUrl = trim((string) ($inspection_url ?? ''));
$monitorBaseUrl = (string) ($monitor_base_url ?? url('/local/monitoramento'));

$configured = (bool) ($searchConsole['configured'] ?? false);
$siteUrl = trim((string) ($searchConsole['site_url'] ?? ''));
$tokenStatus = trim((string) ($searchConsole['token_status'] ?? 'Pendente'));
$connectedProperty = is_array($searchConsole['connected_property'] ?? null) ? $searchConsole['connected_property'] : null;
$availableSites = array_values((array) ($searchConsole['available_sites'] ?? []));
$sitemaps = array_values((array) ($searchConsole['sitemaps'] ?? []));
$performance = is_array($searchConsole['performance'] ?? null) ? $searchConsole['performance'] : [];
$summary = is_array($performance['summary'] ?? null) ? $performance['summary'] : null;
$topQueries = array_values((array) ($performance['top_queries'] ?? []));
$topPages = array_values((array) ($performance['top_pages'] ?? []));
$criticalUrls = array_values((array) ($searchConsole['critical_urls'] ?? []));
$nonIndexedPosts = array_values((array) ($searchConsole['non_indexed_posts'] ?? []));
$inspection = is_array($searchConsole['inspection'] ?? null) ? $searchConsole['inspection'] : [];
$inspectionResult = is_array($inspection['result'] ?? null) ? $inspection['result'] : null;
$screenError = trim((string) ($searchConsole['error'] ?? ''));
$cache = is_array($searchConsole['cache'] ?? null) ? $searchConsole['cache'] : [];
$cacheEnabled = (bool) ($cache['enabled'] ?? false);
$cacheHit = (bool) ($cache['hit'] ?? false);
$cacheForcedRefresh = (bool) ($cache['forced_refresh'] ?? false);
$cacheCachedAt = trim((string) ($cache['cached_at'] ?? ''));
$cacheExpiresAt = trim((string) ($cache['expires_at'] ?? ''));

$sectionUrl = static function (string $section, array $extra = []) use ($monitorBaseUrl): string {
    $separator = str_contains($monitorBaseUrl, '?') ? '&' : '?';
    $url = $monitorBaseUrl . $separator . 'monitor_secao=' . rawurlencode($section);

    foreach ($extra as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }

        $url .= '&' . rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
    }

    return $url;
};

$pill = static function (string $label, string $tone = 'default'): string {
    $classes = match ($tone) {
        'ok' => 'border-emerald-400/30 bg-emerald-500/10 text-emerald-200',
        'warn' => 'border-amber-400/30 bg-amber-500/10 text-amber-200',
        'error' => 'border-rose-400/30 bg-rose-500/10 text-rose-200',
        default => 'border-slate-700 bg-slate-950/70 text-slate-300',
    };

    return '<span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold ' . $classes . '">' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
};

$metricTile = static function (string $label, string $value): string {
    return '<span class="rounded-xl border border-slate-800 bg-slate-900/80 px-3 py-2">'
        . '<span class="block font-orbitron text-[10px] uppercase tracking-[0.18em] text-slate-500">' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>'
        . '<span class="mt-1 block text-base font-bold text-slate-100">' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>'
        . '</span>';
};
?>
<section data-search-console-panel class="space-y-6">
  <?php if ($screenError !== ''): ?>
    <div class="rounded-2xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
      <?= htmlspecialchars($screenError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <?php if ($monitorSection === 'resumo' && $cacheEnabled): ?>
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-800 bg-slate-900/80 px-4 py-3 text-sm text-slate-300">
      <div>
        <strong class="text-slate-100"><?= $cacheHit ? 'Dados em cache' : ($cacheForcedRefresh ? 'Busca atualizada agora' : 'Busca recente') ?></strong>
        <?php if ($cacheCachedAt !== ''): ?>
          <span class="ml-2 text-slate-400">Atualizado em <?= htmlspecialchars($cacheCachedAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><?= $cacheExpiresAt !== '' ? ' | expira em ' . htmlspecialchars($cacheExpiresAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '' ?></span>
        <?php endif; ?>
      </div>
      <a href="<?= htmlspecialchars($sectionUrl('resumo', ['search_console_refresh' => '1']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-2xl border border-cyan-400/40 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200 transition hover:border-cyan-300 hover:bg-cyan-500/20">Atualizar agora</a>
    </div>
  <?php endif; ?>

  <?php if ($monitorSection === 'resumo'): ?>
    <div class="grid gap-4 xl:grid-cols-4">
      <div class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Conexao</p>
        <div class="mt-4 text-3xl font-bold text-white"><?= htmlspecialchars($configured ? $tokenStatus : 'Pendente', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <div class="mt-3 flex flex-wrap gap-2">
          <?= $pill($configured ? 'Configurado' : 'Sem credencial', $configured ? 'ok' : 'warn') ?>
        </div>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Propriedade</p>
        <div class="mt-4 text-lg font-semibold text-white break-all"><?= htmlspecialchars($siteUrl !== '' ? $siteUrl : 'Nao definida', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <p class="mt-3 text-sm text-slate-400">Configurada no `.env` como base das consultas.</p>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Sitemaps</p>
        <div class="mt-4 text-3xl font-bold text-white"><?= count($sitemaps) ?></div>
        <div class="mt-3 flex flex-wrap gap-2">
          <?= $pill($sitemaps === [] ? 'Leitura pendente' : 'Processado', $sitemaps === [] ? 'warn' : 'ok') ?>
          <span class="inline-flex items-center rounded-full border border-slate-700 bg-slate-950/70 px-3 py-1 text-xs font-semibold text-slate-300">Erros: <?= array_sum(array_map(static fn(array $item): int => (int) ($item['errors'] ?? 0), $sitemaps)) ?></span>
          <span class="inline-flex items-center rounded-full border border-slate-700 bg-slate-950/70 px-3 py-1 text-xs font-semibold text-slate-300">Avisos: <?= array_sum(array_map(static fn(array $item): int => (int) ($item['warnings'] ?? 0), $sitemaps)) ?></span>
        </div>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Sites visiveis</p>
        <div class="mt-4 text-3xl font-bold text-white"><?= count($availableSites) ?></div>
        <p class="mt-3 text-sm text-slate-400">Total de propriedades retornadas para a conta autenticada.</p>
      </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <div class="flex items-center justify-between gap-4">
          <div>
            <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Propriedade conectada</p>
            <h3 class="mt-2 font-orbitron text-xl font-bold text-white">Status atual</h3>
          </div>
          <?php if ($connectedProperty !== null): ?>
            <?= $pill((string) ($connectedProperty['permission_level'] ?? 'Sem permissao'), 'ok') ?>
          <?php endif; ?>
        </div>

        <?php if ($connectedProperty === null): ?>
          <p class="mt-5 text-sm text-slate-400">A propriedade configurada ainda nao apareceu entre os sites autorizados da conta.</p>
        <?php else: ?>
          <div class="mt-5 space-y-3 text-sm">
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <div class="text-slate-500">Site URL</div>
              <div class="mt-2 break-all font-semibold text-slate-100"><?= htmlspecialchars((string) ($connectedProperty['site_url'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <div class="text-slate-500">Permissao</div>
              <div class="mt-2 font-semibold text-slate-100"><?= htmlspecialchars((string) ($connectedProperty['permission_level'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Resumo de performance</p>
        <h3 class="mt-2 font-orbitron text-xl font-bold text-white"><?= htmlspecialchars((string) ($performance['range_label'] ?? 'Periodo recente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>

        <?php if ($summary === null): ?>
          <p class="mt-5 text-sm text-slate-400">Sem dados agregados para o periodo consultado.</p>
        <?php else: ?>
          <div class="mt-5 grid gap-3 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4"><div class="text-slate-500">Cliques</div><div class="mt-2 text-2xl font-bold text-white"><?= number_format((float) ($summary['clicks'] ?? 0), 0, ',', '.') ?></div></div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4"><div class="text-slate-500">Impressoes</div><div class="mt-2 text-2xl font-bold text-white"><?= number_format((float) ($summary['impressions'] ?? 0), 0, ',', '.') ?></div></div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4"><div class="text-slate-500">CTR</div><div class="mt-2 text-2xl font-bold text-white"><?= number_format(((float) ($summary['ctr'] ?? 0)) * 100, 2, ',', '.') ?>%</div></div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4"><div class="text-slate-500">Posicao media</div><div class="mt-2 text-2xl font-bold text-white"><?= number_format((float) ($summary['position'] ?? 0), 2, ',', '.') ?></div></div>
          </div>
          <p class="mt-4 text-sm text-slate-400">Janela consultada: <?= htmlspecialchars((string) ($summary['start_date'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> ate <?= htmlspecialchars((string) ($summary['end_date'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <div class="flex items-center justify-between gap-4">
        <div>
          <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">URLs criticas</p>
          <h3 class="mt-2 font-orbitron text-xl font-bold text-white">Indexacao das paginas principais</h3>
        </div>
        <?= $pill(count($criticalUrls) . ' URLs', $criticalUrls === [] ? 'warn' : 'default') ?>
      </div>

      <?php if ($criticalUrls === []): ?>
        <div class="mt-5 rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3 text-sm text-slate-400">Nenhuma URL critica foi montada para inspecao automatica.</div>
      <?php else: ?>
        <div class="mt-5 grid gap-4 xl:grid-cols-2">
          <?php foreach ($criticalUrls as $item): ?>
            <?php
              $result = is_array($item['result'] ?? null) ? $item['result'] : [];
              $tone = (string) ($item['tone'] ?? 'default');
            ?>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <div class="text-base font-semibold text-slate-100"><?= htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  <div class="mt-1 text-[13px] uppercase tracking-[0.18em] text-slate-500"><?= htmlspecialchars((string) ($item['source'] ?? 'site'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </div>
                <?= $pill((string) ($result['verdict'] ?? 'Sem verdict'), $tone) ?>
              </div>
              <div class="mt-3 break-all text-base font-medium text-slate-300"><?= htmlspecialchars((string) ($item['url'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <div class="mt-4 grid gap-3 text-[15px] text-slate-300 md:grid-cols-2">
                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-3">
                  <div class="text-[13px] text-slate-500">Coverage</div>
                  <div class="mt-1 text-base font-semibold text-slate-100"><?= htmlspecialchars((string) ($result['coverage_state'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </div>
                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-3">
                  <div class="text-[13px] text-slate-500">Indexing</div>
                  <div class="mt-1 text-base font-semibold text-slate-100"><?= htmlspecialchars((string) ($result['indexing_state'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </div>
                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-3 md:col-span-2">
                  <div class="text-[13px] text-slate-500">Google canonical</div>
                  <div class="mt-1 break-all text-base font-semibold text-slate-100"><?= htmlspecialchars((string) ($result['google_canonical'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </div>
                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-3">
                  <div class="text-[13px] text-slate-500">Ultimo crawl</div>
                  <div class="mt-1 text-base font-semibold text-slate-100"><?= htmlspecialchars((string) ($result['last_crawl_time'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </div>
                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-3">
                  <div class="text-[13px] text-slate-500">Robots.txt</div>
                  <div class="mt-1 text-base font-semibold text-slate-100"><?= htmlspecialchars((string) ($result['robots_txt_state'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </div>
              </div>
              <div class="mt-4">
                <a href="<?= htmlspecialchars($sectionUrl('inspecao', ['inspection_url' => (string) ($item['url'] ?? '')]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-2xl border border-cyan-400/40 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200 transition hover:border-cyan-300 hover:bg-cyan-500/20">Abrir inspecao desta URL</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Performance organica</p>
        <h3 class="mt-2 font-orbitron text-xl font-bold text-white">Top consultas e paginas</h3>
        <div class="mt-5 grid gap-4">
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <div class="text-sm font-semibold text-white">Top consultas</div>
            <div class="mt-3 space-y-3">
              <?php if ($topQueries === []): ?>
                <div class="text-sm text-slate-400">Sem consultas retornadas para o periodo.</div>
              <?php else: ?>
                <?php foreach ($topQueries as $item): ?>
                  <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-4">
                    <div class="text-base font-semibold text-slate-100"><?= htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                    <div class="mt-3 grid gap-2 sm:grid-cols-3">
                      <?= $metricTile('Cliques', number_format((float) ($item['clicks'] ?? 0), 0, ',', '.')) ?>
                      <?= $metricTile('CTR', number_format(((float) ($item['ctr'] ?? 0)) * 100, 2, ',', '.') . '%') ?>
                      <?= $metricTile('Posicao', number_format((float) ($item['position'] ?? 0), 2, ',', '.')) ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <div class="text-sm font-semibold text-white">Top paginas</div>
            <div class="mt-3 space-y-3">
              <?php if ($topPages === []): ?>
                <div class="text-sm text-slate-400">Sem paginas retornadas para o periodo.</div>
              <?php else: ?>
                <?php foreach ($topPages as $item): ?>
                  <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-4">
                    <div class="break-all text-base font-semibold text-slate-100"><?= htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                    <div class="mt-3 grid gap-2 sm:grid-cols-3">
                      <?= $metricTile('Cliques', number_format((float) ($item['clicks'] ?? 0), 0, ',', '.')) ?>
                      <?= $metricTile('Impressoes', number_format((float) ($item['impressions'] ?? 0), 0, ',', '.')) ?>
                      <?= $metricTile('Posicao', number_format((float) ($item['position'] ?? 0), 2, ',', '.')) ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_40px_rgba(6,182,212,0.08)]">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Inspecao de URL</p>
      <h2 class="mt-2 font-orbitron text-3xl font-black tracking-tight text-white">Consultar URL especifica</h2>
      <p class="mt-3 max-w-4xl text-sm leading-7 text-slate-300">A API de URL Inspection retorna o status da versao indexada pelo Google para a URL informada. Ela nao testa a indexabilidade ao vivo; mostra o que o Google conhece da versao ja indexada.</p>
    </div>

    <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <form method="GET" action="<?= htmlspecialchars($monitorBaseUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="space-y-4" data-monitor-inspection-form="true">
        <input type="hidden" name="monitor_secao" value="inspecao">
        <div>
          <label for="inspection-url" class="block text-sm font-semibold text-slate-200">URL para inspecionar</label>
          <input id="inspection-url" name="inspection_url" type="url" required value="<?= htmlspecialchars($inspectionUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" placeholder="https://estrategianerd.com.br/" class="mt-2 w-full rounded-2xl border border-slate-700 bg-slate-950/70 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-400/60 focus:ring-2 focus:ring-cyan-400/20">
        </div>
        <div class="flex flex-wrap gap-3">
          <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-cyan-400/40 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200 transition hover:border-cyan-300 hover:bg-cyan-500/20">Inspecionar URL</button>
          <a href="<?= htmlspecialchars($sectionUrl('inspecao'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-2xl border border-slate-700 bg-slate-950/70 px-4 py-2 text-sm font-semibold text-slate-300 transition hover:border-cyan-400/50 hover:text-white">Limpar</a>
        </div>
      </form>
    </div>

    <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <div class="flex items-center justify-between gap-4">
        <div>
          <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Posts nao indexados</p>
          <h3 class="mt-2 font-orbitron text-xl font-bold text-white">URLs publicadas com atencao</h3>
        </div>
        <?= $pill(count($nonIndexedPosts) . ' URLs', $nonIndexedPosts === [] ? 'ok' : 'warn') ?>
      </div>

      <?php if ($nonIndexedPosts === []): ?>
        <div class="mt-5 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">Nenhum post nao indexado apareceu no lote recente inspecionado pela API.</div>
      <?php else: ?>
        <div class="mt-5 overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-800 text-sm">
            <thead>
              <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                <th class="px-4 py-3 font-medium">Post</th>
                <th class="px-4 py-3 font-medium">Motivo</th>
                <th class="px-4 py-3 font-medium">Indexacao</th>
                <th class="px-4 py-3 font-medium">Ultimo crawl</th>
                <th class="px-4 py-3 font-medium">Inspecao</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
              <?php foreach ($nonIndexedPosts as $item): ?>
                <?php $result = is_array($item['result'] ?? null) ? $item['result'] : []; ?>
                <tr class="align-top">
                  <td class="px-4 py-4">
                    <div class="font-semibold text-slate-100"><?= htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                    <div class="mt-1 break-all text-xs text-slate-400"><?= htmlspecialchars((string) ($item['url'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                    <?php if (trim((string) ($item['lastmod'] ?? '')) !== ''): ?>
                      <div class="mt-1 text-xs text-slate-500">Ultima alteracao: <?= htmlspecialchars((string) $item['lastmod'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-4 text-slate-300">
                    <div class="font-medium text-slate-100"><?= htmlspecialchars((string) ($item['reason'] ?? 'Motivo nao informado'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                    <div class="mt-1 text-xs text-slate-500">Coverage: <?= htmlspecialchars((string) ($result['coverage_state'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  </td>
                  <td class="px-4 py-4">
                    <div class="flex flex-wrap gap-2">
                      <?= $pill((string) ($result['verdict'] ?? 'Sem verdict'), (string) ($item['tone'] ?? 'warn')) ?>
                    </div>
                    <div class="mt-2 text-xs text-slate-400"><?= htmlspecialchars((string) ($result['indexing_state'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  </td>
                  <td class="px-4 py-4 text-slate-300"><?= htmlspecialchars((string) ($result['last_crawl_time'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                  <td class="px-4 py-4">
                    <a href="<?= htmlspecialchars($sectionUrl('inspecao', ['inspection_url' => (string) ($item['url'] ?? '')]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-2xl border border-cyan-400/40 bg-cyan-500/10 px-3 py-2 text-xs font-semibold text-cyan-200 transition hover:border-cyan-300 hover:bg-cyan-500/20">Inspecionar</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($inspectionResult !== null): ?>
      <div class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Status indexado</p>
          <h3 class="mt-2 font-orbitron text-xl font-bold text-white"><?= htmlspecialchars((string) ($inspectionResult['verdict'] ?? 'Sem verdict'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
          <div class="mt-5 space-y-3 text-sm text-slate-300">
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">Coverage state: <strong class="text-slate-100"><?= htmlspecialchars((string) ($inspectionResult['coverage_state'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">Indexing state: <strong class="text-slate-100"><?= htmlspecialchars((string) ($inspectionResult['indexing_state'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">Robots.txt: <strong class="text-slate-100"><?= htmlspecialchars((string) ($inspectionResult['robots_txt_state'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">Page fetch: <strong class="text-slate-100"><?= htmlspecialchars((string) ($inspectionResult['page_fetch_state'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">Ultimo crawl: <strong class="text-slate-100"><?= htmlspecialchars((string) ($inspectionResult['last_crawl_time'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></div>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Canonical e referencias</p>
          <div class="mt-5 space-y-3 text-sm text-slate-300">
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <div class="text-slate-500">Google canonical</div>
              <div class="mt-2 break-all text-slate-100"><?= htmlspecialchars((string) ($inspectionResult['google_canonical'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <div class="text-slate-500">User canonical</div>
              <div class="mt-2 break-all text-slate-100"><?= htmlspecialchars((string) ($inspectionResult['user_canonical'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <div class="text-slate-500">Sitemaps</div>
              <div class="mt-2 space-y-1">
                <?php foreach ((array) ($inspectionResult['sitemaps'] ?? []) as $item): ?>
                  <div class="break-all text-slate-100"><?= htmlspecialchars((string) $item, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <?php endforeach; ?>
                <?php if ((array) ($inspectionResult['sitemaps'] ?? []) === []): ?>
                  <div class="text-slate-400">Nenhum sitemap listado para a URL.</div>
                <?php endif; ?>
              </div>
            </div>
            <?php if (trim((string) ($inspectionResult['inspection_result_link'] ?? '')) !== ''): ?>
              <a href="<?= htmlspecialchars((string) $inspectionResult['inspection_result_link'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center rounded-2xl border border-cyan-400/40 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200 transition hover:border-cyan-300 hover:bg-cyan-500/20">Abrir resultado no Google</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>
