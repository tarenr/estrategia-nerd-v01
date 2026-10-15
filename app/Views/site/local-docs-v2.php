<?php

declare(strict_types=1);

$embedMode = (bool) ($embed_mode ?? false);
$adminEmbed = (bool) ($admin_embed ?? false);
$projectVersion = trim((string) ($project_version ?? 'local'));
$generatedAt = trim((string) ($generated_at ?? date('Y-m-d H:i:s')));
$projectName = (string) config('app.name', 'Estrategia Nerd');
$projectType = 'Portal editorial + conversao';
$projectStack = 'PHP 8.2 (MVC leve), MySQL/MariaDB, Tailwind (arquivo local tailwind.min.css), FontAwesome 6, Node + Chrome (render dos Reels)';
$projectEnvironment = 'Local / homologacao operacional';
$projectOwner = (string) ($_ENV['PROJECT_OWNER'] ?? 'Taren');
$projectScope = 'Documentacao interna, backup, validacao local, stage e governanca de deploy';
$currentSource = strtolower(trim((string) ($_ENV['CONTENT_SYNC_CURRENT_SOURCE'] ?? 'local')));
$approvedSource = strtolower(trim((string) ($_ENV['CONTENT_SYNC_APPROVED_PACKAGE_SOURCE'] ?? 'stage')));
$stageLabel = trim((string) ($_ENV['CONTENT_SYNC_STAGE_LABEL'] ?? 'estrategia-nerd-stage'));
$productionAllowed = $currentSource !== '' && $approvedSource !== '' && $currentSource === $approvedSource;
$docSections = [
    'identidade' => '1. Identidade do projeto',
    'arquitetura' => '2. Arquitetura tecnica',
    'rotas-principais' => '3. Rotas principais do sistema',
    'pastas' => '4. Responsabilidade de pastas',
    'regras-dev' => '5. Regras de desenvolvimento',
    'seguranca' => '6. Seguranca do sistema',
    'padrao-codigo' => '7. Padrao de codigo e nomenclatura',
    'deploy' => '8. Deploy operacional',
    'backup' => '9. Backup e restore',
    'dropbox' => '10. Backup em nuvem (Google Drive)',
    'ambiente' => '11. Configuracao de ambiente',
    'governanca-releases' => '12. Governanca de releases',
    'diagnostico' => '13. Erros comuns e diagnostico',
    'evolucao' => '14. Evolucao tecnica',
    'governanca' => '15. Governanca da documentacao',
    'instagram' => '16. Instagram e Reels',
    'central-nerd' => '17. Central Nerd e cliques',
];
$docSection = strtolower(trim((string) ($_GET['docs_secao'] ?? 'identidade')));
if (!array_key_exists($docSection, $docSections)) {
    $docSection = 'identidade';
}
$docsBaseUrl = $adminEmbed ? url('/admin/base-tecnica?aba=documentacao') : url('/local/documentacao');
$docsSectionUrl = static function (string $section) use ($docsBaseUrl): string {
    $separator = str_contains($docsBaseUrl, '?') ? '&' : '?';
    return $docsBaseUrl . $separator . 'docs_secao=' . rawurlencode($section);
};
$technicalPriorityDocUrl = url('/local/mudancas/documento?grupo=governanca&arquivo=priorizacao-tecnica.md&redirect_to=' . rawurlencode($docsSectionUrl('governanca-releases')));
?>
<section class="<?= $adminEmbed ? 'text-slate-100' : 'min-h-screen bg-slate-950 px-4 py-8 text-slate-100' ?>">
  <style>
    .doc-card { border: 1px solid rgba(51, 65, 85, 0.9); background: rgba(2, 6, 23, 0.65); border-radius: 1rem; padding: 1rem; }
    .doc-label { font-family: Orbitron, ui-sans-serif, system-ui; font-size: 0.66rem; letter-spacing: 0.2em; text-transform: uppercase; color: rgba(148, 163, 184, 0.95); }
    .doc-rule { border: 1px solid rgba(34, 211, 238, 0.25); background: rgba(8, 47, 73, 0.45); }
    .doc-alert { border: 1px solid rgba(251, 191, 36, 0.28); background: rgba(120, 53, 15, 0.28); }
    .doc-example { border: 1px solid rgba(192, 132, 252, 0.26); background: rgba(59, 7, 100, 0.23); }
    .doc-table { min-width: 100%; border-collapse: separate; border-spacing: 0 0.55rem; font-size: 0.875rem; }
    .doc-table th { text-align: left; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.2em; color: rgba(100, 116, 139, 1); padding: 0.5rem 0.75rem; }
    .doc-table td { padding: 0.7rem 0.75rem; vertical-align: top; }
    .doc-row { border: 1px solid rgba(51, 65, 85, 0.95); background: rgba(2, 6, 23, 0.7); }
    .doc-panel[hidden] { display: none; }
  </style>
  <div class="<?= $adminEmbed ? 'space-y-6' : 'mx-auto max-w-7xl space-y-6' ?>">
    <?php if (!$embedMode): ?>
      <?php \App\Support\View::component('site/local-tools-nav', ['active' => 'docs']); ?>
    <?php endif; ?>

    <header class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_40px_rgba(6,182,212,0.08)]">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Documentacao Interna Local</p>
      <h1 class="mt-2 font-orbitron text-3xl font-black tracking-tight text-white">Base Tecnica Operacional - <?= htmlspecialchars($projectName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
      <p class="mt-3 max-w-4xl text-sm leading-7 text-slate-300">Painel de referencia tecnica para desenvolvimento, manutencao, backup, deploy e operacao editorial. Esta documentacao e interna/local e deve evoluir junto com o sistema e com as regras permanentes do portal.</p>
    </header>

    <article class="grid gap-6 xl:grid-cols-[0.92fr_2fr]">
      <aside class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <h2 class="font-orbitron text-base font-bold text-white">Indice</h2>
        <nav class="mt-4 grid gap-2 text-sm" data-doc-nav>
          <?php foreach ($docSections as $sectionKey => $sectionLabel): ?>
            <?php $isActive = $sectionKey === $docSection; ?>
            <a
              href="<?= htmlspecialchars($docsSectionUrl($sectionKey), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
              data-doc-trigger="<?= htmlspecialchars($sectionKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
              class="block rounded-xl border px-3 py-2 transition <?= $isActive ? 'border-cyan-300/70 bg-cyan-500/15 text-cyan-100 shadow-[0_0_20px_rgba(34,211,238,0.12)]' : 'border-slate-700 bg-slate-950/70 text-slate-300 hover:border-cyan-400/60 hover:bg-cyan-500/10 hover:text-white' ?>"
              aria-current="<?= $isActive ? 'page' : 'false' ?>"
            ><?= htmlspecialchars($sectionLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
          <?php endforeach; ?>
        </nav>
      </aside>

      <div class="space-y-6">
        <section id="identidade" data-doc-panel="identidade" class="doc-panel rounded-3xl border border-slate-800 bg-slate-900/80 p-6" <?= $docSection === 'identidade' ? '' : 'hidden' ?>>
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">1) Identidade do projeto</h2>
          <div class="doc-card doc-rule mt-4"><p class="doc-label">Visao geral</p><p class="mt-2 text-sm text-slate-200">Esta base e interna/local, com foco em confiabilidade operacional, paridade entre ambientes e evolucao controlada do sistema.</p></div>
          <div class="mt-4 overflow-x-auto"><table class="doc-table"><tbody>
            <tr class="doc-row"><td class="text-slate-500">Projeto</td><td class="text-white"><?= htmlspecialchars($projectName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
            <tr class="doc-row"><td class="text-slate-500">Tipo</td><td class="text-white"><?= htmlspecialchars($projectType, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
            <tr class="doc-row"><td class="text-slate-500">Stack</td><td class="text-white"><?= htmlspecialchars($projectStack, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
            <tr class="doc-row"><td class="text-slate-500">Ambiente</td><td class="text-white"><?= htmlspecialchars($projectEnvironment, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
            <tr class="doc-row"><td class="text-slate-500">Responsavel</td><td class="text-white"><?= htmlspecialchars($projectOwner, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
            <tr class="doc-row"><td class="text-slate-500">Versao</td><td class="text-white"><?= htmlspecialchars($projectVersion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
            <tr class="doc-row"><td class="text-slate-500">Ultima atualizacao</td><td class="text-white"><?= htmlspecialchars($generatedAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
            <tr class="doc-row"><td class="text-slate-500">Escopo</td><td class="text-white"><?= htmlspecialchars($projectScope, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
          </tbody></table></div>
        </section>

        <section id="arquitetura" data-doc-panel="arquitetura" class="doc-panel rounded-3xl border border-slate-800 bg-slate-900/80 p-6" <?= $docSection === 'arquitetura' ? '' : 'hidden' ?>>
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">2) Arquitetura tecnica</h2>
          <p class="mt-2 text-sm leading-7 text-slate-300">Arquitetura MVC leve com separacao entre orquestracao (Controller), regra de negocio (Service), acesso a dados (Repository), persistencia (Database) e renderizacao (View).</p>
          <div class="doc-card doc-rule mt-4"><p class="doc-label">Fluxo base</p><pre class="mt-2 overflow-x-auto text-xs leading-6 text-slate-200"><code>Request -> Router -> Controller -> Service -> Repository -> Database
                                                -> ViewModel -> View -> Response</code></pre></div>
          <div class="doc-card doc-example mt-4"><p class="doc-label">ViewModel</p><ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200"><li>Nasce no Service para preparar dados de exibicao.</li><li>Evita dependencia direta da View com Repository/SQL.</li><li>Nao deve concentrar regra de negocio pesada.</li></ul></div>
        </section>

        <section id="rotas-principais" data-doc-panel="rotas-principais" class="doc-panel rounded-3xl border border-slate-800 bg-slate-900/80 p-6" <?= $docSection === 'rotas-principais' ? '' : 'hidden' ?>>
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">3) Rotas do sistema (geradas de config/routes.php)</h2>
          <?php
          // A tabela e montada a partir do proprio config/routes.php: nunca fica desatualizada.
          $routeGroups = [];
          $routeDefinitions = is_file(base_path('config/routes.php')) ? (array) require base_path('config/routes.php') : [];
          foreach ($routeDefinitions as $routeDefinition) {
              if (!is_array($routeDefinition) || count($routeDefinition) < 3) {
                  continue;
              }
              $routePath = (string) $routeDefinition[1];
              $segments = array_values(array_filter(explode('/', $routePath), static fn (string $part): bool => $part !== ''));
              $first = $segments[0] ?? '';
              $second = $segments[1] ?? '';
              if ($first === 'admin') {
                  $known = ['instagram', 'central-operacional-v2', 'central-tecnica', 'base-tecnica', 'midia', 'links', 'newsletter', 'usuarios', 'testes', 'health', 'auditoria-geral', 'home-e-menus'];
                  $groupName = in_array($second, $known, true) ? 'Admin: /admin/' . $second : 'Admin: posts, categorias, comentarios e demais';
              } elseif ($first === 'local') {
                  $groupName = 'Local: /local (somente neste PC)';
              } elseif ($first === 'api') {
                  $groupName = 'API: /api';
              } else {
                  $groupName = 'Publico (site)';
              }
              $handler = $routeDefinition[2];
              $handlerLabel = is_array($handler) ? (substr(strrchr('\\' . (string) $handler[0], '\\'), 1) . '::' . (string) ($handler[1] ?? '')) : (string) $handler;
              $middleware = $routeDefinition[3] ?? null;
              $accessLabel = $middleware === null ? 'livre' : (is_array($middleware) ? implode(', ', array_map('strval', $middleware)) : (string) $middleware);
              $routeGroups[$groupName][] = [(string) $routeDefinition[0], $routePath, $handlerLabel, $accessLabel];
          }
          ksort($routeGroups);
          $routeTotal = array_sum(array_map('count', $routeGroups));
          ?>
          <p class="mt-2 text-sm leading-7 text-slate-300"><?= (int) $routeTotal ?> rotas registradas, agrupadas por modulo. Coluna "Acesso": <code>auth</code> exige login; <code>capability:*</code> exige a permissao do perfil; <code>livre</code> e publico (rotas <code>/local</code> livres ainda passam por <code>ensureLocalOnly</code>/<code>EnvironmentGuard</code> no controller).</p>
          <?php foreach ($routeGroups as $groupName => $groupRoutes): ?>
            <details class="doc-card mt-3">
              <summary class="cursor-pointer text-sm font-semibold text-white"><?= htmlspecialchars($groupName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> <span class="text-slate-400">(<?= count($groupRoutes) ?>)</span></summary>
              <div class="mt-3 overflow-x-auto"><table class="doc-table"><thead><tr><th>Metodo</th><th>Rota</th><th>Controller::metodo</th><th>Acesso</th></tr></thead><tbody>
                <?php foreach ($groupRoutes as [$routeMethod, $routePathLabel, $routeHandler, $routeAccess]): ?>
                  <tr class="doc-row"><td class="text-slate-400"><?= htmlspecialchars($routeMethod, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td><td class="text-white"><?= htmlspecialchars($routePathLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td><td class="text-slate-300"><?= htmlspecialchars($routeHandler, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td><td class="text-slate-400"><?= htmlspecialchars($routeAccess, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
                <?php endforeach; ?>
              </tbody></table></div>
            </details>
          <?php endforeach; ?>
        </section>

        <section id="pastas" data-doc-panel="pastas" class="doc-panel rounded-3xl border border-slate-800 bg-slate-900/80 p-6" <?= $docSection === 'pastas' ? '' : 'hidden' ?>>
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">4) Responsabilidade de cada pasta</h2>
          <div class="mt-4 overflow-x-auto"><table class="doc-table"><thead><tr><th>Diretorio</th><th>Responsabilidade</th><th>Regra de uso</th></tr></thead><tbody>
            <tr class="doc-row"><td class="text-white">app/Controllers</td><td class="text-slate-300">Entrada HTTP, validacao basica, dispatch e resposta.</td><td class="text-slate-400">Nao conter SQL direto.</td></tr>
            <tr class="doc-row"><td class="text-white">app/Services</td><td class="text-slate-300">Regra de negocio, composicao de repositorios e view model.</td><td class="text-slate-400">Evitar dependencia de camada de view.</td></tr>
            <tr class="doc-row"><td class="text-white">app/Repositories</td><td class="text-slate-300">Acesso ao MySQL (queries, filtros e agregacoes).</td><td class="text-slate-400">Nao renderizar HTML e nao responder HTTP.</td></tr>
            <tr class="doc-row"><td class="text-white">app/Views</td><td class="text-slate-300">Layouts, paginas e componentes.</td><td class="text-slate-400">Sem regra complexa de negocio.</td></tr>
            <tr class="doc-row"><td class="text-white">config</td><td class="text-slate-300">Rotas e configuracoes de app, banco, backup e content-sync.</td><td class="text-slate-400">Nao incluir segredo fixo versionado.</td></tr>
            <tr class="doc-row"><td class="text-white">public/assets</td><td class="text-slate-300">CSS, JS e recursos estaticos da interface.</td><td class="text-slate-400">Arquivos publicos criticos pedem validacao separada.</td></tr>
            <tr class="doc-row"><td class="text-white">public/uploads</td><td class="text-slate-300">Midias editoriais e de campanha.</td><td class="text-slate-400">Limpeza apenas por rotina segura.</td></tr>
            <tr class="doc-row"><td class="text-white">scripts</td><td class="text-slate-300">Rotinas de operacao local.</td><td class="text-slate-400">Executar com contexto, confirmacao e evidencias.</td></tr>
            <tr class="doc-row"><td class="text-white">storage</td><td class="text-slate-300">Artefatos locais: backups e pacotes.</td><td class="text-slate-400">Nao publicar diretamente para web.</td></tr>
          </tbody></table></div>
        </section>

        <section id="regras-dev" data-doc-panel="regras-dev" class="doc-panel rounded-3xl border border-slate-800 bg-slate-900/80 p-6" <?= $docSection === 'regras-dev' ? '' : 'hidden' ?>><h2 class="font-orbitron text-xl font-bold text-cyan-200">5) Regras de desenvolvimento</h2><div class="doc-card doc-rule mt-4"><ul class="list-disc space-y-1 pl-5 text-sm text-slate-200"><li>Controller nao acessa banco diretamente.</li><li>Service concentra regra de negocio e coerencia entre modulos.</li><li>Repository concentra acesso a dados.</li><li>View nao carrega regra de negocio complexa.</li><li>Scripts locais sao para operacao controlada.</li><li>Hotfix em producao deve voltar para local/stage no mesmo ciclo.</li><li>Toda alteracao funcional deve atualizar esta documentacao.</li></ul></div></section>

        <section id="seguranca" data-doc-panel="seguranca" class="doc-panel rounded-3xl border border-slate-800 bg-slate-900/80 p-6" <?= $docSection === 'seguranca' ? '' : 'hidden' ?>><h2 class="font-orbitron text-xl font-bold text-cyan-200">6) Seguranca do sistema</h2><div class="doc-card doc-alert mt-4"><p class="doc-label">Alerta critico</p><p class="mt-2 text-sm text-amber-100">Todo deploy deve garantir que rotas e arquivos internos nao fiquem expostos publicamente.</p></div><ul class="mt-4 list-disc space-y-1 pl-5 text-sm text-slate-300"><li>Rotas locais (`/local/*` e `/dev`) devem ficar restritas ao ambiente local.</li><li>`.env` nao pode ser servido pelo web server.</li><li>Upload deve validar tipo, MIME, extensao e tamanho.</li><li>Sessao administrativa e formularios sensiveis devem usar CSRF.</li><li>`public/index.php` e `public/.htaccess` sao arquivos criticos e exigem validacao separada.</li><li>Uploads nao devem permitir execucao de scripts no servidor web (<code>public/uploads/.htaccess</code> bloqueia PHP).</li><li>O site publico (<code>https://nerd.tfr-info.com.br</code>) e servido desta maquina via Cloudflare Tunnel; <code>/admin</code> e <code>/local/*</code> exigem o login do <strong>Cloudflare Access</strong> (<code>CF_ACCESS_TEAM_DOMAIN</code>, <code>CF_ACCESS_AUD</code>).</li><li>Controllers locais chamam <code>ensureLocalOnly()</code> ou <code>EnvironmentGuard::requireLocal()</code>: aceitam so localhost, CLI ou LAN privada quando <code>ALLOW_LAN_ACCESS=true</code>.</li><li>Rotas admin usam o middleware <code>auth</code> e, quando sensiveis, <code>capability:*</code> (perfil); todas as rotas POST validam <code>Csrf::validate</code>.</li><li><code>APP_DEBUG</code> e falso por padrao (<code>config/app.php</code>).</li></ul></section>

        <section id="padrao-codigo" data-doc-panel="padrao-codigo" class="doc-panel rounded-3xl border border-slate-800 bg-slate-900/80 p-6" <?= $docSection === 'padrao-codigo' ? '' : 'hidden' ?>><h2 class="font-orbitron text-xl font-bold text-cyan-200">7) Padrao de codigo e nomenclatura</h2><div class="doc-card doc-rule mt-4"><ul class="list-disc space-y-1 pl-5 text-sm text-slate-200"><li>Classes em PascalCase.</li><li>Metodos em camelCase.</li><li>Controllers terminam com `Controller`.</li><li>Services terminam com `Service`.</li><li>Repositories terminam com `Repository`.</li><li>Classes de apoio em <code>app/Services</code> podem usar sufixos que descrevem o papel: `Renderer`, `Publisher`, `Presenter`, `Reconciler`, `Fitter`, `Exception`.</li><li>Codigo novo: SQL fica no Repository. Ao alterar um service que ja consulta o banco direto, mover o SQL para o repository na mesma tarefa.</li><li>Variaveis devem ser descritivas.</li><li>Nome de arquivo deve refletir responsabilidade.</li></ul></div></section>

        <section id="deploy" data-doc-panel="deploy" class="doc-panel rounded-3xl border border-slate-800 bg-slate-900/80 p-6" <?= $docSection === 'deploy' ? '' : 'hidden' ?>>
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">8) Deploy operacional (codigo e conteudo)</h2>
          <div class="doc-card <?= $productionAllowed ? 'doc-rule' : 'doc-alert' ?> mt-4"><p class="doc-label">Politica atual</p><p class="mt-2 text-sm <?= $productionAllowed ? 'text-slate-100' : 'text-amber-100' ?>">Origem atual: <strong><?= htmlspecialchars($currentSource, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>. Origem aprovada para pacote de producao: <strong><?= htmlspecialchars($approvedSource, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong> (<?= htmlspecialchars($stageLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>).</p><p class="mt-2 text-sm <?= $productionAllowed ? 'text-slate-300' : 'text-amber-100' ?>"><?= $productionAllowed ? 'Este ambiente esta autorizado para gerar/publicar pacote de producao.' : 'Este ambiente nao pode publicar em producao. O local continua valido para desenvolvimento, teste e validacao.' ?></p></div>
          <ol class="mt-4 list-decimal space-y-1 pl-5 text-sm text-slate-300"><li>Alteracao no ambiente local</li><li>Teste local</li><li>Aplicar a mesma mudanca na stage (`<?= htmlspecialchars($stageLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>`)</li><li>Teste em stage</li><li>Registrar alteracao (commit/log)</li><li>Backup obrigatorio</li><li>Gerar pacote apenas da stage</li><li>Publicar em producao</li><li>Executar pos-check e restaurar paridade</li></ol>
          <div class="doc-card doc-rule mt-4"><p class="doc-label">Rastreabilidade operacional</p><p class="mt-2 text-sm text-slate-200">A Central Operacional passou a mostrar o destino dos pacotes aplicados. Em Conteudo, a tela exibe "Ultimas aplicacoes editoriais" e "Ultimos deploys tecnicos", deixando explicito qual pacote foi para stage, producao ou local e em qual horario.</p></div>
          <div class="mt-4 grid gap-4 xl:grid-cols-2">
            <div class="doc-card">
              <p class="doc-label">Quando usar</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                <li>Deploy tecnico: alteracoes em controllers, services, repositories, views, config ou assets do sistema.</li>
                <li>Deploy editorial: sincronizacao de posts, categorias, links, configuracoes publicas e uploads referenciados.</li>
                <li>Promocao para producao: apenas apos validacao em stage e backup valido.</li>
              </ul>
            </div>
            <div class="doc-card">
              <p class="doc-label">Evidencias esperadas</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                <li><code>package_id</code> registrado</li>
                <li><code>backup_id</code> associado ao ciclo</li>
                <li>Destino final visivel em "Ultimas aplicacoes" ou "Ultimos deploys"</li>
                <li>Pos-check sem erro critico em login, admin e rotas publicas afetadas</li>
              </ul>
            </div>
          </div>
          <div class="doc-card doc-example mt-4"><p class="doc-label">Checklist minimo antes de empacotar</p><ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200"><li>Executar `php scripts/preflight-check.php`.</li><li>Confirmar origem atual x origem aprovada.</li><li>Validar `public/index.php` e `public/.htaccess`.</li><li>Conferir rotas criticas, assets, uploads e encoding.</li><li>Garantir pacote sem dados ficticios e sem artefatos locais.</li></ul></div>
          <div class="doc-card mt-4">
            <p class="doc-label">Runbook rapido - conteudo editorial</p>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-200">
              <li>Gerar pacote da origem correta (`stage` ou `producao`, conforme o fluxo).</li>
              <li>Executar verificacao do pacote e confirmar manifesto, JSONs e uploads.</li>
              <li>Aplicar no destino controlado (`local`, `stage` ou `producao`).</li>
              <li>Conferir o bloco de ultimas aplicacoes para validar origem, destino e horario.</li>
              <li>Se houver falha, revisar lock, credenciais, manifesto e uploads referenciados antes de repetir.</li>
            </ol>
          </div>
          <div class="mt-4 grid gap-4 xl:grid-cols-2">
            <div class="doc-card">
              <p class="doc-label">Conteudo editorial - fluxos oficiais</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                <li><strong>Stage -> Local</strong>: espelho de homologacao para validacao interna.</li>
                <li><strong>Stage -> Producao</strong>: promocao oficial de conteudo homologado.</li>
                <li><strong>Producao -> Stage</strong>: restauracao de paridade da homologacao.</li>
                <li><strong>Producao -> Local</strong>: espelho do que esta publicado de fato.</li>
              </ul>
            </div>
            <div class="doc-card">
              <p class="doc-label">Conteudo editorial - pre-requisitos</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                <li>Origem correta escolhida antes de gerar o pacote.</li>
                <li>Uploads da origem acessiveis e consistentes.</li>
                <li>Manifesto valido, com contagem coerente de posts, categorias, links e midias.</li>
                <li>Destino sem lock ativo e com credenciais correspondentes.</li>
              </ul>
            </div>
          </div>
          <div class="doc-card mt-4">
            <p class="doc-label">Conteudo editorial - o que conferir depois</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
              <li>Ultimo pacote da origem atualizado na tela.</li>
              <li>Destino correto registrado em <strong>Ultimas aplicacoes editoriais</strong>.</li>
              <li>Posts, categorias, links e uploads visiveis no ambiente alvo.</li>
              <li>Sem divergencia entre pacote aplicado e ambiente exibido no painel.</li>
            </ul>
          </div>
          <div class="doc-card mt-4">
            <p class="doc-label">Runbook rapido - codigo tecnico</p>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-200">
              <li>Rodar preflight e lint basico dos arquivos alterados.</li>
              <li>Gerar pacote tecnico da origem aprovada.</li>
              <li>Associar backup tecnico correspondente.</li>
              <li>Aplicar no destino e acompanhar a rotina pelo progresso real da modal.</li>
              <li>Registrar a entrega em feature/release e validar o deploy tecnico exibido na Central.</li>
            </ol>
          </div>
          <div class="mt-4 grid gap-4 xl:grid-cols-2">
            <div class="doc-card">
              <p class="doc-label">Codigo tecnico - o que entra no pacote</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                <li>Controllers, services, repositories e support do sistema.</li>
                <li>Views e componentes administrativos/publicos.</li>
                <li>Arquivos de configuracao, scripts operacionais e assets tecnicos.</li>
                <li>Nunca incluir segredos locais, temporarios ou dados ficticios.</li>
              </ul>
            </div>
            <div class="doc-card">
              <p class="doc-label">Codigo tecnico - validacao minima</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                <li>Lint dos arquivos PHP alterados.</li>
                <li>Preflight sem erro critico.</li>
                <li>Rotas criticas e login/admin validos no alvo.</li>
                <li>Deploy visivel em <strong>Ultimos deploys tecnicos</strong>.</li>
              </ul>
            </div>
          </div>
          <div class="doc-card mt-4">
            <p class="doc-label">Codigo tecnico - rollback dirigido</p>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-200">
              <li>Congelar novos deploys tecnicos e editoriais.</li>
              <li>Identificar o ultimo pacote tecnico estavel e o backup associado.</li>
              <li>Restaurar o ambiente somente se o pacote estavel nao resolver sozinho.</li>
              <li>Revalidar login, admin, homepage e rota critica afetada.</li>
            </ol>
          </div>
          <div class="doc-card doc-example mt-4"><p class="doc-label">Rollback e hotfix</p><ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-200"><li>Interromper novas publicacoes.</li><li>Restaurar ultimo backup valido.</li><li>Reaplicar pacote tecnico estavel.</li><li>Validar paginas criticas e dashboard.</li><li>Replicar hotfix em stage/local antes de encerrar a tarefa.</li></ol></div>
        </section>

        <section id="backup" data-doc-panel="backup" class="doc-panel rounded-3xl border border-slate-800 bg-slate-900/80 p-6" <?= $docSection === 'backup' ? '' : 'hidden' ?>><h2 class="font-orbitron text-xl font-bold text-cyan-200">9) Backup e restore</h2><div class="doc-card doc-rule mt-4"><p class="doc-label">Visao geral</p><p class="mt-2 text-sm text-slate-200">Backup e etapa obrigatoria antes de qualquer deploy ou restore em ambiente sensivel.</p></div><ul class="mt-4 list-disc space-y-1 pl-5 text-sm text-slate-300"><li>Inclui dump do banco, pacote de uploads, pacote de arquivos do sistema e manifesto de integridade.</li><li>Deve ser executado antes de deploy, restore ou mudanca estrutural.</li><li>Producao usa credenciais `BACKUP_PRODUCTION_*` para DB remoto, FTP de uploads e FTP dos arquivos do sistema.</li><li>Restore so deve ocorrer com confirmacao explicita e backup anterior preservado.</li><li>A modal de Backup e Restore agora acompanha as etapas reais da rotina: exportacao do banco, coleta de uploads, coleta dos arquivos do sistema, compactacao, verificacao e restore completo.</li><li>O envio para nuvem continua sendo uma trilha separada, na subaba `Nuvem`, e envia o backup completo para o Google Drive.</li></ul>
          <div class="mt-4 grid gap-4 xl:grid-cols-2">
            <div class="doc-card">
              <p class="doc-label">Tipos de acao</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                <li><strong>Backup de ambiente</strong>: gera novo backup completo de banco + uploads + arquivos do sistema.</li>
                <li><strong>Verificar ultimo</strong>: checa integridade do manifesto, banco, uploads e arquivos do sistema do ultimo backup.</li>
                <li><strong>Restore completo</strong>: recompoe o ambiente com banco + uploads + arquivos do sistema de um backup escolhido.</li>
              </ul>
            </div>
            <div class="doc-card">
              <p class="doc-label">Ambientes cobertos</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                <li><strong>Local</strong>: ambiente de desenvolvimento e validacao interna.</li>
                <li><strong>Stage</strong>: homologacao operacional e comparacao com producao.</li>
                <li><strong>Producao</strong>: recovery remoto, com maior criticidade e confirmacoes mais fortes.</li>
              </ul>
            </div>
          </div>
          <div class="doc-card mt-4">
            <p class="doc-label">Passo a passo - backup</p>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-200">
              <li>Escolher o ambiente correto e confirmar que a rotina nao esta travada por outra operacao.</li>
              <li>Executar o backup e acompanhar na modal as etapas de banco, uploads, arquivos do sistema, compactacao e manifesto.</li>
              <li>Confirmar o novo <code>backup_id</code> no resumo e no historico paginado.</li>
              <li>Executar `Verificar ultimo` se o backup for entrar em ciclo critico de deploy ou restore.</li>
            </ol>
          </div>
          <div class="doc-card mt-4">
            <p class="doc-label">Passo a passo - restore</p>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-200">
              <li>Preservar um backup anterior do estado atual antes de restaurar.</li>
              <li>Escolher o <code>backup_id</code> correto e confirmar o destino exato.</li>
              <li>Acompanhar a rotina pelas etapas reais de restore do banco, extracao, restore de uploads e restore dos arquivos do sistema.</li>
              <li>Executar validacao funcional minima do ambiente apos concluir.</li>
            </ol>
          </div>
          <div class="doc-card doc-alert mt-4">
            <p class="doc-label">Cuidados</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-100">
              <li>Restore completo sobrescreve banco, uploads e arquivos do sistema do destino.</li>
              <li>Nao usar restore para "testar" fluxo em producao.</li>
              <li>Se a verificacao falhar, nao reutilizar o backup ate corrigir a integridade.</li>
            </ul>
          </div>
          <div class="doc-card doc-rule mt-4">
            <p class="doc-label">Rotina diaria e teste de restauracao</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
              <li>A tarefa agendada do Windows <code>EstrategiaNerd-BackupDiario</code> (21:30) roda <code>scripts/backup/Invoke-DailyBackup.ps1</code>: backup sistemico de local, stage e producao (<code>scripts/backup.php run</code>) e exportacao editorial de local e producao, enviando tudo ao Google Drive (secao 10). Log: <code>storage/logs/daily-backup-AAAA-MM.log</code>.</li>
              <li>Pasta local: <code>BACKUP_ROOT</code> (hoje <code>C:\Users\WINDOWS\ProjectBackup\estrategia-nerd</code>; ate 15/10/2026 era <code>E:\Taren\Documents\Backup\Estrategia Nerd</code>, mantida como copia), com <code>01-local</code>, <code>02-stage</code>, <code>03-prod</code> e <code>04-logs</code>; retencao por <code>BACKUP_RETENTION</code> (hoje 7; era 14 ate 15/10/2026).</li>
              <li>Backup diario (<code>scripts/backup/Invoke-DailyBackup.ps1</code>, 21:30): o ambiente local roda <code>--no-uploads</code> de segunda a sabado e completo (com <code>uploads.zip</code>) aos domingos, porque os uploads ja ficam no backup geral (restic); stage e producao sempre completos. Teste sem executar: <code>-DiaDaSemana 0..6 -SomenteMostrarComandos</code>.</li>
              <li>O restore oficial so restaura <strong>por cima</strong> de local, stage ou producao. Para provar que um backup e restauravel sem tocar em nada, use <code>php scripts/verify-backup-restore.php &lt;local|stage|production&gt; [BACKUP_ID] [--skip-uploads]</code>: confere os SHA-256 do manifesto, le todos os arquivos dos zips conferindo o CRC e importa o <code>database.sql</code> em um banco temporario <code>en_restore_check_*</code>, que e apagado no fim. Relatorio em <code>storage/logs/backup-restore-check/</code>.</li>
              <li>Ultima prova (13/10/2026): producao <code>BS-PROD-20261013-220056</code> (19 tabelas, 288 arquivos de sistema, 242 uploads) e local <code>BS-LOCAL-20261013-213002</code> (25 tabelas, 3.396 arquivos de sistema, 4.022 uploads) restauraveis, sem divergencias.</li>
            </ul>
          </div>
        </section>

        <section id="dropbox" data-doc-panel="dropbox" class="doc-panel rounded-3xl border border-slate-800 bg-slate-900/80 p-6" <?= $docSection === 'dropbox' ? '' : 'hidden' ?>>
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">10) Backup em nuvem (Google Drive)</h2>
          <div class="doc-card doc-rule mt-4">
            <p class="doc-label">Situacao atual (IMP-023)</p>
            <p class="mt-2 text-sm text-slate-200">Desde o IMP-023 o destino de nuvem dos backups e o <strong>Google Drive</strong> (mesma conta do backup editorial). No IMP-033 o Dropbox foi removido de vez: servico, acoes, rota de retorno e configuracao saíram do codigo, e as telas de backup do painel (Backup Sistemico, Backup em Nuvem e Backup Editorial) passaram a enviar e excluir no Google Drive. A copia local continua sendo a principal; a nuvem e a copia fora da maquina.</p>
          </div>
          <div class="mt-4 grid gap-4 xl:grid-cols-2">
            <div class="doc-card">
              <p class="doc-label">O que sobe e para onde</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                <li>Sistemico: <code>manifest.json</code>, <code>database.sql</code>, <code>uploads.zip</code> e <code>system-files.zip</code> em <code>Estrategia Nerd - Backups de Conteudo/Sistema/&lt;Local|Stage|Producao&gt;/&lt;BACKUP_ID&gt;</code>.</li>
                <li>Editorial (pacotes de conteudo): <code>Estrategia Nerd - Backups de Conteudo/&lt;Local|Producao&gt;/&lt;PACOTE&gt;</code>.</li>
                <li>O manifesto local registra <code>cloud_provider = google_drive</code>, destino, id remoto e arquivos enviados.</li>
              </ul>
            </div>
            <div class="doc-card">
              <p class="doc-label">Como operar</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                <li>Automatico: rotina diaria <code>EstrategiaNerd-BackupDiario</code> (secao 9).</li>
                <li>Manual: <code>php scripts/backup.php upload-cloud latest</code> (sistemico) e <code>php scripts/content-sync.php upload-cloud latest</code> (editorial).</li>
                <li>Pelo painel (so local; em stage/producao o admin da 404): <code>/admin/central-operacional-v2/backup-em-nuvem</code> (conexao, envio, historico e exclusao controlada) e <code>/local/backup?backup_secao=nuvem</code> (enviar ultimo, enviar especifico e excluir da nuvem). Os botoes usam as acoes <code>google_drive_upload_latest</code>, <code>google_drive_upload_backup</code> e <code>google_drive_delete_backup</code>; a exclusao exige digitar o ID do backup.</li>
                <li>Servico: <code>app/Services/Site/GoogleDriveBackupService.php</code>; configuracao em <code>config/backup-cloud.php</code> (secao <code>google_drive</code>).</li>
              </ul>
            </div>
          </div>
          <div class="doc-card mt-4">
            <p class="doc-label">Conexao (OAuth)</p>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-200">
              <li>Variaveis no <code>.env</code> local: <code>BACKUP_GOOGLE_DRIVE_CLIENT_*</code>, <code>BACKUP_GOOGLE_DRIVE_REDIRECT_*</code> e <code>BACKUP_GOOGLE_DRIVE_EDITORIAL_FOLDER</code> (somente nomes aqui; valores ficam no <code>.env</code> e na copia central de credenciais).</li>
              <li>Callback local da autorizacao: <code>/local/backup/google-drive/callback</code>.</li>
              <li>Se o token expirar ou a permissao mudar, reconectar pela pagina Backup em Nuvem.</li>
            </ol>
          </div>
          <div class="doc-card doc-alert mt-4">
            <p class="doc-label">Cuidados</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-100">
              <li>A nuvem complementa o backup local; nao substitui a copia local nem o restore controlado.</li>
              <li>A rotina roda nesta maquina Windows (faz o FTP-pull de stage/producao): se ela ficar desligada, nao ha backup novo de stage/producao. Conferir o log diario.</li>
              <li>Credenciais OAuth e tokens ficam apenas no ambiente local, nunca em codigo versionado.</li>
            </ul>
          </div>
        </section>

        <section id="ambiente" data-doc-panel="ambiente" class="doc-panel rounded-3xl border border-slate-800 bg-slate-900/80 p-6" <?= $docSection === 'ambiente' ? '' : 'hidden' ?>><h2 class="font-orbitron text-xl font-bold text-cyan-200">11) Configuracoes de ambiente (.env)</h2><div class="mt-4 overflow-x-auto"><table class="doc-table"><thead><tr><th>Variavel</th><th>Finalidade</th><th>Obs</th></tr></thead><tbody><tr class="doc-row"><td class="text-white">APP_ENV / APP_DEBUG / APP_URL</td><td class="text-slate-300">Controle de ambiente, debug e URL base.</td><td class="text-slate-400">Producao deve estar com debug desligado.</td></tr><tr class="doc-row"><td class="text-white">DB_HOST / DB_PORT / DB_DATABASE / DB_USERNAME / DB_PASSWORD</td><td class="text-slate-300">Conexao principal do sistema.</td><td class="text-slate-400">Nao versionar segredo real.</td></tr><tr class="doc-row"><td class="text-white">BACKUP_PRODUCTION_*</td><td class="text-slate-300">Acesso remoto para backup de producao.</td><td class="text-slate-400">Obrigatorio para backup remoto.</td></tr><tr class="doc-row"><td class="text-white">BACKUP_ROOT / BACKUP_RETENTION / BACKUP_MYSQL_BINARY / BACKUP_MYSQLDUMP_BINARY</td><td class="text-slate-300">Pasta local dos backups, retencao e binarios do MySQL.</td><td class="text-slate-400">Usados pela rotina diaria e por verify-backup-restore.php.</td></tr><tr class="doc-row"><td class="text-white">BACKUP_GOOGLE_DRIVE_CLIENT_* / BACKUP_GOOGLE_DRIVE_REDIRECT_* / BACKUP_GOOGLE_DRIVE_EDITORIAL_FOLDER</td><td class="text-slate-300">OAuth e pasta do backup em nuvem (Google Drive).</td><td class="text-slate-400">Somente no .env local.</td></tr><tr class="doc-row"><td class="text-white">BACKUP_STAGE_SYSTEM_* / BACKUP_PRODUCTION_SYSTEM_*</td><td class="text-slate-300">FTP dos arquivos do sistema de stage/producao para o backup.</td><td class="text-slate-400">Separado do FTP de uploads.</td></tr><tr class="doc-row"><td class="text-white">CONTENT_SYNC_CURRENT_SOURCE</td><td class="text-slate-300">Identifica a origem atual da operacao (`local`, `stage`, etc.).</td><td class="text-slate-400">Usado para travar publish inseguro.</td></tr><tr class="doc-row"><td class="text-white">CONTENT_SYNC_APPROVED_PACKAGE_SOURCE</td><td class="text-slate-300">Origem autorizada para pacote de producao.</td><td class="text-slate-400">Padrao recomendado: `stage`.</td></tr><tr class="doc-row"><td class="text-white">CONTENT_SYNC_STAGE_LABEL</td><td class="text-slate-300">Nome humano da stage oficial.</td><td class="text-slate-400">Ex.: `estrategia-nerd-stage`.</td></tr><tr class="doc-row"><td class="text-white">CONTENT_SYNC_STAGE_DB_*</td><td class="text-slate-300">Banco remoto da stage para homologacao.</td><td class="text-slate-400">Nao reaproveitar credencial de producao.</td></tr><tr class="doc-row"><td class="text-white">CONTENT_SYNC_STAGE_FTP_*</td><td class="text-slate-300">Uploads remotos da stage.</td><td class="text-slate-400">Mantem midias e banco homologados no mesmo ambiente.</td></tr><tr class="doc-row"><td class="text-white">CONTENT_SYNC_STAGE_CODE_*</td><td class="text-slate-300">Deploy tecnico dedicado da stage.</td><td class="text-slate-400">Separar raiz tecnica da stage da raiz de producao.</td></tr><tr class="doc-row"><td class="text-white">CONTENT_SYNC_PRODUCTION_*</td><td class="text-slate-300">Sync de conteudo e deploy de codigo.</td><td class="text-slate-400">Separar dados de conteudo e codigo.</td></tr><tr class="doc-row"><td class="text-white">CF_ACCESS_TEAM_DOMAIN / CF_ACCESS_AUD / ALLOW_LAN_ACCESS</td><td class="text-slate-300">Cloudflare Access e acesso por LAN as rotas locais.</td><td class="text-slate-400">Ver secao 6.</td></tr><tr class="doc-row"><td class="text-white">INSTAGRAM_PUBLIC_MEDIA_URL</td><td class="text-slate-300">Endereco publico de onde a Meta baixa videos e capas.</td><td class="text-slate-400">Nao pode ser localhost.</td></tr><tr class="doc-row"><td class="text-white">GEMINI_API_KEY / GEMINI_TEXT_MODEL / GEMINI_TEXT_MODEL_FALLBACK</td><td class="text-slate-300">Legendas geradas no cross-post Blog → Instagram.</td><td class="text-slate-400">Somente no .env.</td></tr><tr class="doc-row"><td class="text-white">MATOMO_URL / MATOMO_SITE_ID</td><td class="text-slate-300">Analytics do portal.</td><td class="text-slate-400">Matomo em conta separada (IMP-022).</td></tr><tr class="doc-row"><td class="text-white">GOOGLE_SEARCH_CONSOLE_CLIENT_* / _REFRESH_* / _SITE_*</td><td class="text-slate-300">Monitoramento de SEO (<code>/local/monitoramento</code> e seo-tecnico).</td><td class="text-slate-400">OAuth local.</td></tr><tr class="doc-row"><td class="text-white">HOSTINGER_API_* / HOSTINGER_API_BASE_*</td><td class="text-slate-300">Painel Hostinger API na Central Operacional V2.</td><td class="text-slate-400">Somente no .env.</td></tr><tr class="doc-row"><td class="text-white">SHOPEE_APP_* / SHOPEE_SUB_ID_DEFAULT</td><td class="text-slate-300">Afiliados Shopee (IMP-029).</td><td class="text-slate-400">Somente no .env.</td></tr><tr class="doc-row"><td class="text-white">NERDOPS_STATS_TOKEN</td><td class="text-slate-300">Leitura de estatisticas pelo NERD // OPS.</td><td class="text-slate-400">Token; nunca versionar.</td></tr><tr class="doc-row"><td class="text-white">SMOKE_* / AUTOMATED_TEST_*</td><td class="text-slate-300">Usuarios e alvos dos testes automatizados.</td><td class="text-slate-400">Contas de teste restritas.</td></tr></tbody></table></div><p class="mt-3 text-sm text-slate-300">Seguranca: `.env` nao pode ser commitado com credencial real. Usar `.env.example` como referencia limpa.</p></section>

        <section id="governanca-releases" data-doc-panel="governanca-releases" class="doc-panel rounded-3xl border border-slate-800 bg-slate-900/80 p-6" <?= $docSection === 'governanca-releases' ? '' : 'hidden' ?>>
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">12) Governanca oficial de releases</h2>
          <div class="doc-card doc-rule mt-4">
            <p class="doc-label">Objetivo</p>
            <p class="mt-2 text-sm text-slate-200">
              Toda alteracao relevante do EN deve ter rastreabilidade, documentacao minima, teste minimo, impacto conhecido, origem validada e paridade entre ambientes.
            </p>
          </div>
          <div class="mt-4 overflow-x-auto">
            <table class="doc-table">
              <thead>
                <tr><th>Diretorio</th><th>Responsabilidade</th><th>Uso oficial</th></tr>
              </thead>
              <tbody>
                <tr class="doc-row"><td class="text-white">docs/features</td><td class="text-slate-300">Documento de cada feature, fix, melhoria ou hotfix.</td><td class="text-slate-400">Usar um arquivo por entrega com ID oficial.</td></tr>
                <tr class="doc-row"><td class="text-white">docs/releases</td><td class="text-slate-300">Documento de cada release.</td><td class="text-slate-400">Registrar o que entra, o que fica fora e o estado do deploy.</td></tr>
                <tr class="doc-row"><td class="text-white">docs/checklists</td><td class="text-slate-300">Checklist pre e pos deploy.</td><td class="text-slate-400">Obrigatorio em toda publicacao controlada.</td></tr>
                <tr class="doc-row"><td class="text-white">docs/rollback</td><td class="text-slate-300">Roteiro de retorno controlado.</td><td class="text-slate-400">Toda release deve saber como voltar.</td></tr>
                <tr class="doc-row"><td class="text-white">docs/templates</td><td class="text-slate-300">Modelos oficiais do EN.</td><td class="text-slate-400">Base para features, releases, rollback e prompt operacional.</td></tr>
                <tr class="doc-row"><td class="text-white">docs/governanca</td><td class="text-slate-300">Diretrizes oficiais de risco, execucao e prioridade tecnica.</td><td class="text-slate-400">Usar para orientar backlog, execucao e revisoes periodicas.</td></tr>
              </tbody>
            </table>
          </div>
          <div class="doc-card mt-4">
            <p class="doc-label">Diretriz oficial</p>
            <div class="mt-2 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
              <div>
                <p class="text-sm text-slate-100">A matriz oficial de risco e execucao tecnica do projeto esta registrada em <code>docs/governanca/priorizacao-tecnica.md</code>.</p>
                <p class="mt-1 text-sm text-slate-300">Esse documento organiza criterios, blocos criticos e evidencias minimas para orientar backlog, operacao e validacao de entregas.</p>
              </div>
              <a href="<?= htmlspecialchars($technicalPriorityDocUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-2xl border border-cyan-400/40 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200 transition hover:border-cyan-300 hover:bg-cyan-500/20">
                Abrir diretriz
              </a>
            </div>
          </div>
          <div class="doc-card doc-example mt-4">
            <p class="doc-label">Campos obrigatorios</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
              <li>Impacto em producao: baixo, medio ou alto</li>
              <li>Afeta rotas criticas: sim ou nao</li>
              <li>Mudanca de schema: sim ou nao</li>
              <li>Mudanca de dados: sim ou nao</li>
              <li>Origem validada: estrategia-nerd-stage</li>
              <li>Paridade local -> stage: validada ou nao validada</li>
              <li>Paridade stage -> pacote: validada ou nao validada</li>
            </ul>
          </div>
          <div class="doc-card doc-alert mt-4">
            <p class="doc-label">Regra de ouro</p>
            <p class="mt-2 text-sm text-amber-100">
              Nada sobe sem ID, documentacao minima, teste minimo, impacto conhecido, origem validada e paridade confirmada.
            </p>
          </div>
          <div class="doc-card doc-rule mt-4">
            <p class="doc-label">Validacao obrigatoria antes de concluir ou commitar</p>
            <p class="mt-2 text-sm text-slate-200"><code>C:\xampp\php\php.exe scripts/verify-changes.php</code> precisa terminar em <strong>APROVADO COM SUCESSO! (0 FALHAS)</strong> (lint, PHPStan nivel 5, dashboard nos 3 ambientes e modulo Instagram). A documentacao central afetada tambem precisa estar atualizada (secao 15).</p>
          </div>
        </section>

        <section id="diagnostico" data-doc-panel="diagnostico" class="doc-panel rounded-3xl border border-slate-800 bg-slate-900/80 p-6" <?= $docSection === 'diagnostico' ? '' : 'hidden' ?>><h2 class="font-orbitron text-xl font-bold text-cyan-200">13) Erros comuns e diagnostico rapido</h2><div class="mt-4 overflow-x-auto"><table class="doc-table"><thead><tr><th>Problema</th><th>Possivel causa</th><th>Acao recomendada</th></tr></thead><tbody><tr class="doc-row"><td class="text-white">Imagem nao aparece</td><td class="text-slate-300">Path quebrado ou arquivo fora de uploads.</td><td class="text-slate-400">Revisar referencia no banco e em `public/uploads`.</td></tr><tr class="doc-row"><td class="text-white">Capa pesada degradando o site</td><td class="text-slate-300">Capa do artigo salva em PNG de alto peso (~2 a 3 MB).</td><td class="text-slate-400">Obrigatorio converter para WebP (~150-250 KB) e apontar `posts.imagem_capa` para `capa.webp`.</td></tr><tr class="doc-row"><td class="text-white">Colisao de slugs no sync de posts</td><td class="text-slate-300">Post no destino capturado por historico antigo de outro post do lote.</td><td class="text-slate-400">Reivindicacao direta tem precedencia absoluta; limpar redirecionamentos obsoletos em `post_slug_history`.</td></tr><tr class="doc-row"><td class="text-white">Post ou Reel preso em rascunho</td><td class="text-slate-300">Item movido para rascunho para ajuste de midia e esquecido sem reagendamento.</td><td class="text-slate-400">Revisao so termina quando o status volta para `agendado` com data e horario validos.</td></tr><tr class="doc-row"><td class="text-white">Post agendado posterior ao fato</td><td class="text-slate-300">Falta de checagem do prazo do evento (ex.: fim de suporte).</td><td class="text-slate-400">Conteudo com deadline deve ser agendado com 1 a 3 dias de antecedencia do fato, nunca depois.</td></tr><tr class="doc-row"><td class="text-white">Rota nao carrega</td><td class="text-slate-300">Regra em `config/routes.php`, `public/index.php` ou `.htaccess` incorreta.</td><td class="text-slate-400">Rodar preflight e validar entrada publica.</td></tr><tr class="doc-row"><td class="text-white">Conteudo nao sincroniza</td><td class="text-slate-300">Pacote invalido, lock ativo ou alvo remoto incompleto.</td><td class="text-slate-400">Verificar manifesto, lock e credenciais `CONTENT_SYNC_PRODUCTION_*`.</td></tr><tr class="doc-row"><td class="text-white">Pacote falha na validacao</td><td class="text-slate-300">JSON ausente, upload faltando ou manifesto inconsistente.</td><td class="text-slate-400">Executar verificacao do pacote e revisar arquivos referenciados.</td></tr><tr class="doc-row"><td class="text-white">Google Drive falha no envio</td><td class="text-slate-300">Token expirado ou revogado, conta desconectada ou callback divergente.</td><td class="text-slate-400">Reconectar em Backup em Nuvem e conferir <code>BACKUP_GOOGLE_DRIVE_*</code> (secao 10).</td></tr><tr class="doc-row"><td class="text-white">Texto com acentuacao quebrada</td><td class="text-slate-300">Arquivo salvo com encoding invalido ou scanner gerando falso-positivo.</td><td class="text-slate-400">Usar o scanner estrito por bytes de continuacao UTF-8 (`scripts/preflight-encoding.php`).</td></tr><tr class="doc-row"><td class="text-white">Reel bloqueado: "Capa do Reel ausente ou invalida"</td><td class="text-slate-300">Falta o <code>.jpg</code> com o mesmo nome do <code>.mp4</code>.</td><td class="text-slate-400">Gerar a capa JPEG ao lado do video; nunca publicar sem capa (<code>scripts/verify-reel-cover.php</code>).</td></tr><tr class="doc-row"><td class="text-white">Reel publicado com o video antigo</td><td class="text-slate-300">A fila aponta para outro arquivo em <code>video_rendered_path</code>.</td><td class="text-slate-400">Seguir o procedimento de troca de video (secao 16).</td></tr><tr class="doc-row"><td class="text-white">Render dos Reels derruba o Chrome ao capturar quadro em PNG</td><td class="text-slate-300">Fundo muito detalhado deixa a captura PNG grande demais.</td><td class="text-slate-400">Renderizar o video (JPEG) e extrair os quadros do proprio video.</td></tr><tr class="doc-row"><td class="text-white">Backup gerado, mas sem prova de restore</td><td class="text-slate-300">O restore oficial so restaura por cima de um ambiente real.</td><td class="text-slate-400">Usar <code>scripts/verify-backup-restore.php</code> (banco temporario).</td></tr><tr class="doc-row"><td class="text-white">Acentos corrompidos em tarefas do Forge</td><td class="text-slate-300">Texto enviado pelo shell em codificacao errada.</td><td class="text-slate-400">Enviar o JSON a partir de arquivo UTF-8 (<code>curl.exe --data-binary @arquivo.json</code>).</td></tr></tbody></table></div>
          <div class="mt-4 grid gap-4 xl:grid-cols-2 lg:grid-cols-2">
            <div class="doc-card">
              <p class="doc-label">Troubleshooting - posts & reels</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                <li>Reels revisados devem retornar imediatamente para <code>agendado</code> com <code>render_status = ready</code>.</li>
                <li>Verificar prazos: noticias e encerramentos de suporte nunca saem apos a data limite.</li>
                <li>Grade oficial do canal: 2 publicacoes diarias nos horarios de pico (<strong>12:00</strong> e <strong>19:30</strong>).</li>
                <li>Todas as capas do blog devem ser salvas no padrao WebP otimizado (~150-250 KB).</li><li>Texto de Reel so dentro da area segura do Instagram (secao 16); imagem pode ficar sob os icones.</li><li>Video de Reel: padrao <code>public/uploads/posts/&lt;slug&gt;/reels/reel.mp4</code> com <code>reel.jpg</code> e <code>capa.jpg</code> ao lado (varios Reels no post: <code>reels/reel-&lt;id&gt;/</code>), versoes antigas em <code>reels/anteriores/</code> (ver secao 16).</li>
              </ul>
            </div>
            <div class="doc-card">
              <p class="doc-label">Troubleshooting - sincronizacao de conteudo</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                <li>Checar origem real do pacote e destino aplicado (nunca gerar pacote de producao a partir do local).</li>
                <li>Central Operacional lenta (~12 s) ou com timeout no <code>smoke-test.php</code>: a causa era <code>ContentSyncManager::allPackages()</code> verificar cada pacote da pasta de backup (disco externo) tres vezes por abertura. Hoje a lista e memorizada na requisicao e guardada em <code>storage/cache/content-sync-packages.json</code> (valida por 5 min enquanto as pastas e os <code>manifest.json</code> nao mudarem; <code>writeManifest()</code> e a exclusao de pacote limpam o cache). Primeira abertura apos o prazo: ~4 s. Para forcar a releitura, apagar esse arquivo.</li>
                <li>Sincronizador isola IDs ja reivindicados para impedir que posts distintos colidam no mesmo ID de destino.</li>
                <li>Validar integridade de todos os slugs com <code>assertAppliedPostIntegrity</code> antes do commit da transacao.</li>
                <li>Em caso de falha, o rollback automatico protege o banco de dados de producao.</li>
              </ul>
            </div>
            <div class="doc-card">
              <p class="doc-label">Troubleshooting - backup & restore</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                <li>Checar <code>backup_root</code> e perfil do ambiente antes de qualquer deploy.</li>
                <li>Confirmar manifesto, <code>database.sql</code>, <code>uploads.zip</code> e <code>system-files.zip</code>.</li>
                <li>Se a verificacao falhar, nao usar o backup no restore nem envia-lo ao Google Drive.</li>
              </ul>
            </div>
            <div class="doc-card">
              <p class="doc-label">Troubleshooting - codigo & deploy</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                <li>Rodar preflight e lint antes de reaplicar pacote tecnico.</li>
                <li>Conferir backup tecnico associado e ultimo deploy tecnico visivel na Central.</li>
                <li>Se o problema persistir, restaurar pacote estavel antes de novo hotfix.</li>
              </ul>
            </div>
          </div>
        </section>

        <section id="evolucao" data-doc-panel="evolucao" class="doc-panel rounded-3xl border border-slate-800 bg-slate-900/80 p-6" <?= $docSection === 'evolucao' ? '' : 'hidden' ?>><h2 class="font-orbitron text-xl font-bold text-cyan-200">14) Evolucao tecnica</h2><div class="doc-card doc-example mt-4"><ul class="list-disc space-y-1 pl-5 text-sm text-slate-200"><li>Versao inicial do portal editorial e dashboard admin.</li><li>Criacao da Central Nerd como pagina de conversao.</li><li>Adicao da rotina local de backup com restore controlado.</li><li>Implementacao da sincronizacao de conteudo/codigo com paridade.</li><li>Refino dos hubs tecnicos no admin local com navegacao assincrona.</li><li>Modal operacional de Conteudo com progresso real e rastreabilidade de aplicacao de pacotes.</li><li>Modal operacional de Backup e Restore com progresso real para backup, verificacao e restore.</li><li>Integracao Dropbox/Nuvem para backup de ambiente (substituida pelo Google Drive no IMP-023; Dropbox removido do codigo e das telas no IMP-033).</li><li>Reforco de preflight, documentacao interna, governanca de releases e bloqueio por origem aprovada.</li><li>Modulo Instagram (FEAT-010): contas, fila de posts, publicacao agendada, confirmacao na Meta, cross-post Blog → Instagram e feed publico automatico na home.</li><li>Reels: Smart Canvas, Konva (reels-konva), biblioteca de trilhas (IMP-032) e Reels em tipografia cinetica com a skill motionmaxxing (reels-motionmaxxing).</li><li>Central Operacional V2 (backups sistemico/editorial, nuvem, observabilidade, Hostinger API, SEO tecnico) e Central Tecnica (Base de Conhecimento).</li><li>Backup diario automatico com Google Drive (IMP-023) e prova de restauracao em banco temporario (<code>verify-backup-restore.php</code>).</li><li>Afiliados e links (IMP-027 a IMP-031), monitoramento de links e Search Console.</li><li>Ofertas relacionadas no fim dos posts com rastreio por post e filtro de cliques falsos (FEAT-013).</li><li>Qualidade: PHPStan nivel 5 e suite <code>verify-changes.php</code>; Cloudflare Access na frente do admin.</li><li>Tarefas somente no Forge; documentacao central com guia proprio (<code>docs/governanca/documentacao-central.md</code>).</li></ul></div></section>

        <section id="governanca" data-doc-panel="governanca" class="doc-panel rounded-3xl border border-fuchsia-500/20 bg-slate-900/80 p-6" <?= $docSection === 'governanca' ? '' : 'hidden' ?>><h2 class="font-orbitron text-xl font-bold text-fuchsia-200">15) Governanca da documentacao</h2><p class="mt-3 text-sm leading-7 text-slate-300">Esta pagina e a documentacao principal local do portal. Toda mudanca estrutural, operacional, de release ou de deploy deve atualizar esta base no mesmo ciclo de trabalho.</p><div class="mt-4 rounded-2xl border border-fuchsia-400/20 bg-fuchsia-500/10 p-4 text-sm text-fuchsia-50"><p class="mb-2">Guia obrigatorio para qualquer IA ou pessoa: <code>docs/governanca/documentacao-central.md</code> (qual arquivo alimenta cada area da Central Tecnica, quando atualizar e como validar). Citado no <code>AGENTS.md</code>.</p><ol class="list-decimal space-y-1 pl-5"><li>Atualizar a secao impactada da documentacao principal na mesma tarefa da alteracao.</li><li>Revisar compatibilidade com `docs/CODEX-REGRAS-PERMANENTES.txt`.</li><li>Revisar tambem `docs/governanca/priorizacao-tecnica.md` quando a alteracao mudar risco, prioridade ou criterio de execucao.</li><li>Atualizar feature/release/checklist/rollback quando a alteracao exigir.</li><li>Executar preflight antes de qualquer empacotamento e <code>verify-changes.php</code> antes de concluir.</li><li>Conferir a pagina renderizada (rotas <code>/local/*</code>) depois de editar.</li><li>Registrar a tarefa e concluir no Forge (projeto Estrategia Nerd) so depois da validacao.</li><li>Revisao mensal: tarefa recorrente no Forge.</li></ol></div></section>

        <section id="instagram" data-doc-panel="instagram" class="doc-panel rounded-3xl border border-slate-800 bg-slate-900/80 p-6" <?= $docSection === 'instagram' ? '' : 'hidden' ?>>
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">16) Instagram e Reels</h2>
          <div class="doc-card doc-rule mt-4">
            <p class="doc-label">Visao geral (FEAT-010)</p>
            <p class="mt-2 text-sm text-slate-200">Modulo de ambiente unico: tokens, fila e cache ficam <strong>somente no MySQL local</strong> (<code>$GLOBALS['pdo']</code>), nas tabelas <code>instagram_accounts</code>, <code>instagram_posts</code>, <code>instagram_post_media</code>, <code>instagram_insights_cache</code> e <code>instagram_audio_tracks</code>. Tela: <code>/admin/instagram</code>. Documento completo: <code>docs/features/FEAT-010.md</code>.</p>
          </div>
          <div class="mt-4 grid gap-4 xl:grid-cols-2">
            <div class="doc-card">
              <p class="doc-label">Publicacao agendada</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                <li>Tarefa do Windows <code>EstrategiaNerd-InstagramPublicarAgendados</code> (a cada 5 min) roda <code>scripts/en-instagram-publish-scheduled.php</code>.</li>
                <li>Grade: 12:00 e 19:30. Lock atomico <code>agendado</code> → <code>publicando</code>; confirmacao na Meta antes de marcar publicado.</li>
                <li>Log: <code>storage/logs/instagram/publish-AAAA-MM.log</code>.</li>
                <li>A Meta baixa video e capa de <code>INSTAGRAM_PUBLIC_MEDIA_URL</code> (hoje <code>https://nerd.tfr-info.com.br</code>, servido por esta maquina).</li>
              </ul>
            </div>
            <div class="doc-card">
              <p class="doc-label">Qual video sai</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                <li>Reel com trilha: usa <code>instagram_posts.video_rendered_path</code> quando <code>render_status = ready</code> e o arquivo existe; senao, regenera pelo renderer editorial (Konva) com a trilha cadastrada.</li>
                <li>A capa <code>.jpg</code> com o mesmo nome do <code>.mp4</code> e obrigatoria; sem ela o envio e bloqueado.</li>
                <li>Feed publico da home: <code>scripts/en-instagram-publish-feed.php</code>; para pausar sem apagar nada, criar <code>storage/instagram-feed-publish/auto.disabled</code>.</li>
              </ul>
            </div>
          </div>
          <div class="doc-card mt-4">
            <p class="doc-label">Trocar o video de um Reel ja agendado</p>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-200">
              <li>Padrao de arquivos (nome fixo, como as capas de artigos): <code>public/uploads/posts/&lt;slug&gt;/reels/reel.mp4</code> com <code>reel.jpg</code> e <code>capa.jpg</code> ao lado; post com mais de um Reel usa <code>reels/reel-&lt;id&gt;/reel.mp4</code>. O banco aponta sempre para esse nome fixo.</li>
              <li>Antes de trocar, so se o post estiver <code>agendado</code> e sem <code>creation_id</code>/<code>ig_media_id</code>: mover o <code>reel.mp4</code>, o <code>reel.jpg</code> e o <code>capa.jpg</code> atuais para <code>reels/anteriores/</code>, com data e hash no nome (<code>reel-AAAAMMDD-&lt;hash8&gt;.mp4</code>). Eles sao o rollback.</li>
              <li>Copiar o novo <code>.mp4</code> (H.264 + AAC, 1080x1920, 30 fps) para <code>reel.mp4</code> e a capa para <code>reel.jpg</code> e <code>capa.jpg</code>; conferir hash e duracao. Como o caminho no banco nao muda, nao ha UPDATE; se um dia precisar mudar <code>instagram_posts.video_rendered_path</code> e <code>instagram_post_media.caminho</code>, fazer em transacao, mantendo <code>render_status = ready</code>.</li>
              <li>Validar: reler o post, rodar <code>AudioReelGeneratorService::readyVideoPath</code> e <code>InstagramApiService::reelCoverParams</code>, abrir o endereco publico do video/capa (HTTP 200) e rodar <code>verify-changes.php</code>.</li>
              <li>Rollback: voltar os arquivos de <code>reels/anteriores/</code> para <code>reel.mp4</code>, <code>reel.jpg</code> e <code>capa.jpg</code>.</li>
            </ol>
          </div>
          <div class="mt-4 grid gap-4 xl:grid-cols-2">
            <div class="doc-card doc-alert">
              <p class="doc-label">Area segura do texto (Reels 1080x1920)</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-100">
                <li>Vale so para <strong>texto</strong>; imagem e fundo podem ficar sob os icones.</li>
                <li>Nenhum texto acima de Y 170 nem abaixo de Y 1540.</li>
                <li>Entre Y 970 e 1790 (coluna de icones), texto ate X 925; margem lateral minima X 72.</li>
                <li>Medir no navegador com o texto assentado; letras em movimento podem passar por instantes.</li>
              </ul>
            </div>
            <div class="doc-card">
              <p class="doc-label">Producao de Reels</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                <li>Padrao aprovado (out/2026): imagem do post inteira na moldura, texto como foco com animacao que representa o sentido, fontes ligadas ao tema, som escolhido. Detalhes: <code>docs/features/reels-motionmaxxing.md</code>.</li>
                <li>Renderers do sistema: <code>KonvaReelRenderer</code>, <code>EditorialMotionReelRenderer</code>, <code>SmartCanvasRenderer</code>; trilhas em <code>public/uploads/audio</code> (IMP-032).</li>
                <li>Tempo de render medido em 14/10/2026 (1080x1920, 20 s, <code>--shutter 180 --subframes 8 --grain 0.03</code>): cerca de 9 a 10 min por video. Ate 3 renders ao mesmo tempo levam o mesmo tempo de 1 (3 videos de 20 s: 657,7 s contra ~656 s sozinho; lote real de 3 filmes: CPU media 44%, pico 100%, RAM livre minima 3,3 GB). Em trecho de 5 s, 4, 5 e 6 renders juntos levaram 175, 178 e 177 s (RAM livre minima 3,4, 2,7 e 1,3 GB); 4 e a sugestao pratica e 6 o maximo, ainda sem aprovacao como padrao.</li>
                <li>Rascunho para conferir layout: sem shutter leva ~21 s para 5 s de video; <code>--scale 0.5</code> com shutter ganha so ~27%. Com 4 sub-frames o tempo cai pela metade (SSIM 0,992 contra 8), mas o padrao continua em 8.</li>
                <li>No Windows definir <code>CHROME_PATH</code> (o <code>render.mjs</code> so procura caminhos de Mac e Linux), por exemplo <code>C:\Program Files\Google\Chrome\Application\chrome.exe</code>. O render em paralelo sai um pouco diferente do render sozinho (SSIM 0,985 a 0,99), mas iguais entre si; o render sozinho e deterministico.</li>
              </ul>
            </div>
          </div>
        </section>
        <section id="central-nerd" data-doc-panel="central-nerd" class="doc-panel rounded-3xl border border-slate-800 bg-slate-900/80 p-6" <?= $docSection === 'central-nerd' ? '' : 'hidden' ?>>
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">17) Central Nerd e cliques</h2>
          <div class="doc-card mt-4">
            <p class="doc-label">Como funciona</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
              <li>Pagina <code>/central-nerd</code> (<code>CentralController</code> / <code>CentralService</code>) lista as ofertas da tabela <code>links</code>, agrupadas por prateleira (<code>subgrupo_publico</code>).</li>
              <li>Todo botao de oferta passa por <code>/link/{slug}?origem=...</code>: <code>LinkRedirectService::resolve</code> registra o clique em <code>link_clicks</code> (<code>LinkClickRepository::registerClick</code>) e redireciona para a loja.</li>
              <li>Origens em uso: <code>central-{prateleira}</code>, <code>central-destaque</code>, <code>central-cupons</code> e <code>post-{id}</code> (ofertas no fim dos artigos).</li>
            </ul>
          </div>
          <div class="mt-4 grid gap-4 xl:grid-cols-2">
            <div class="doc-card">
              <p class="doc-label">Ofertas no fim dos posts (FEAT-013)</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                <li><code>RelatedOffersService</code> monta a lista da categoria (prateleiras ligadas a categoria do post + produtos com palavra em comum com titulo/tags) e <strong>sorteia 3 a cada visita</strong>, sem repetir no bloco. Mapa: hardware &rarr; PC Gamer Inicial/Intermediario/Topo e Upgrade Monstro; games &rarr; Setup Gamer/Action Figures; cultura-geek &rarr; Action Figures/Gadgets; gadgets &rarr; Gadgets/Setup Gamer; dicas &rarr; Setup Estrategia Nerd/Setup Gamer.</li>
                <li>Os produtos vem sempre do banco do proprio ambiente (producao le os produtos de producao); o clique em <code>/link/{slug}</code> e resolvido no mesmo banco. A pagina do post sai com <code>Cache-Control: no-store</code>, entao o sorteio vale por visita.</li>
                <li>Sem campo novo no banco: para mudar o que aparece, ajustar a prateleira da oferta no admin de Links ou o mapa <code>CATEGORY_SHELVES</code>.</li>
                <li>Bloco so aparece com a secao Central Nerd ativa e com ao menos 1 oferta; erro no banco nao quebra o artigo.</li>
                <li>Dashboard: quadro "Cliques vindos dos posts" (<code>LinkClickRepository::topPostsByRange</code>).</li>
              </ul>
            </div>
            <div class="doc-card doc-alert">
              <p class="doc-label">Cliques falsos (scanner)</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-100">
                <li>Scanners de SQL injection chamavam <code>/link/...?origem=</code> com SQL na origem. Nao havia risco (consulta parametrizada), mas os cliques inflavam as estatisticas (106 de 361 em producao ate 12/10/2026).</li>
                <li>Desde a FEAT-013, origem com caractere fora de <code>[a-z0-9:_-]</code> (espaco, aspas, parenteses) redireciona normalmente e <strong>nao conta</strong> o clique. User-agents de robo ja eram ignorados.</li>
                <li>Limpeza dos registros antigos: export JSON em <code>storage/logs/limpeza-cliques/</code> antes de apagar; rollback = reimportar o JSON.</li>
              </ul>
            </div>
          </div>
        </section>
      </div>
    </article>
  </div>
  <script>
    (() => {
      const nav = document.querySelector('[data-doc-nav]');
      if (!nav) {
        return;
      }

      const triggers = Array.from(nav.querySelectorAll('[data-doc-trigger]'));
      const panels = Array.from(document.querySelectorAll('[data-doc-panel]'));
      if (triggers.length === 0 || panels.length === 0) {
        return;
      }

      const activate = (target, push = true) => {
        triggers.forEach((trigger) => {
          const active = trigger.dataset.docTrigger === target;
          trigger.classList.toggle('border-cyan-300/70', active);
          trigger.classList.toggle('bg-cyan-500/15', active);
          trigger.classList.toggle('text-cyan-100', active);
          trigger.classList.toggle('shadow-[0_0_20px_rgba(34,211,238,0.12)]', active);
          trigger.classList.toggle('border-slate-700', !active);
          trigger.classList.toggle('bg-slate-950/70', !active);
          trigger.classList.toggle('text-slate-300', !active);
          trigger.setAttribute('aria-current', active ? 'page' : 'false');
        });

        panels.forEach((panel) => {
          panel.hidden = panel.dataset.docPanel !== target;
        });

        if (push) {
          const next = new URL(window.location.href);
          next.searchParams.set('docs_secao', target);
          window.history.pushState({ docsSection: target }, '', next.toString());
        }
      };

      triggers.forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
          event.preventDefault();
          activate(trigger.dataset.docTrigger || 'identidade', true);
        });
      });

      window.addEventListener('popstate', () => {
        const current = new URL(window.location.href);
        activate(current.searchParams.get('docs_secao') || 'identidade', false);
      });

      activate(<?= json_encode($docSection, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, false);
    })();
  </script>
</section>
