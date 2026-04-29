<?php

declare(strict_types=1);

$embedMode = (bool) ($embed_mode ?? false);
$adminEmbed = (bool) ($admin_embed ?? false);
$projectVersion = trim((string) ($project_version ?? 'local'));
$generatedAt = trim((string) ($generated_at ?? date('Y-m-d H:i:s')));
$projectName = (string) config('app.name', 'Estrategia Nerd');
$projectType = 'Portal editorial + conversao';
$projectStack = 'PHP (MVC leve), MySQL, Tailwind (CDN)';
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
    'dropbox' => '10. Dropbox e backup em nuvem',
    'ambiente' => '11. Configuracao de ambiente',
    'governanca-releases' => '12. Governanca de releases',
    'diagnostico' => '13. Erros comuns e diagnostico',
    'evolucao' => '14. Evolucao tecnica',
    'governanca' => '15. Governanca da documentacao',
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
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">3) Rotas principais do sistema</h2>
          <div class="mt-4 overflow-x-auto"><table class="doc-table"><thead><tr><th>Rota</th><th>Controller</th><th>Metodo</th></tr></thead><tbody>
            <tr class="doc-row"><td class="text-white">/</td><td class="text-slate-300">HomeController</td><td class="text-slate-400">index</td></tr>
            <tr class="doc-row"><td class="text-white">/blog</td><td class="text-slate-300">BlogController</td><td class="text-slate-400">index</td></tr>
            <tr class="doc-row"><td class="text-white">/post/{slug}</td><td class="text-slate-300">PostController</td><td class="text-slate-400">show</td></tr>
            <tr class="doc-row"><td class="text-white">/central-nerd</td><td class="text-slate-300">CentralController</td><td class="text-slate-400">index</td></tr>
            <tr class="doc-row"><td class="text-white">/admin</td><td class="text-slate-300">DashboardController</td><td class="text-slate-400">index</td></tr>
            <tr class="doc-row"><td class="text-white">/local/backup</td><td class="text-slate-300">BackupToolsController</td><td class="text-slate-400">index / handle</td></tr>
            <tr class="doc-row"><td class="text-white">/local/conteudo</td><td class="text-slate-300">ContentSyncToolsController</td><td class="text-slate-400">index / handle</td></tr>
            <tr class="doc-row"><td class="text-white">/local/documentacao</td><td class="text-slate-300">LocalDocsController</td><td class="text-slate-400">index</td></tr>
          </tbody></table></div>
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

        <section id="seguranca" data-doc-panel="seguranca" class="doc-panel rounded-3xl border border-slate-800 bg-slate-900/80 p-6" <?= $docSection === 'seguranca' ? '' : 'hidden' ?>><h2 class="font-orbitron text-xl font-bold text-cyan-200">6) Seguranca do sistema</h2><div class="doc-card doc-alert mt-4"><p class="doc-label">Alerta critico</p><p class="mt-2 text-sm text-amber-100">Todo deploy deve garantir que rotas e arquivos internos nao fiquem expostos publicamente.</p></div><ul class="mt-4 list-disc space-y-1 pl-5 text-sm text-slate-300"><li>Rotas locais (`/local/*` e `/dev`) devem ficar restritas ao ambiente local.</li><li>`.env` nao pode ser servido pelo web server.</li><li>Upload deve validar tipo, MIME, extensao e tamanho.</li><li>Sessao administrativa e formularios sensiveis devem usar CSRF.</li><li>`public/index.php` e `public/.htaccess` sao arquivos criticos e exigem validacao separada.</li><li>Uploads nao devem permitir execucao de scripts no servidor web.</li></ul></section>

        <section id="padrao-codigo" data-doc-panel="padrao-codigo" class="doc-panel rounded-3xl border border-slate-800 bg-slate-900/80 p-6" <?= $docSection === 'padrao-codigo' ? '' : 'hidden' ?>><h2 class="font-orbitron text-xl font-bold text-cyan-200">7) Padrao de codigo e nomenclatura</h2><div class="doc-card doc-rule mt-4"><ul class="list-disc space-y-1 pl-5 text-sm text-slate-200"><li>Classes em PascalCase.</li><li>Metodos em camelCase.</li><li>Controllers terminam com `Controller`.</li><li>Services terminam com `Service`.</li><li>Repositories terminam com `Repository`.</li><li>Variaveis devem ser descritivas.</li><li>Nome de arquivo deve refletir responsabilidade.</li></ul></div></section>

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

        <section id="backup" data-doc-panel="backup" class="doc-panel rounded-3xl border border-slate-800 bg-slate-900/80 p-6" <?= $docSection === 'backup' ? '' : 'hidden' ?>><h2 class="font-orbitron text-xl font-bold text-cyan-200">9) Backup e restore</h2><div class="doc-card doc-rule mt-4"><p class="doc-label">Visao geral</p><p class="mt-2 text-sm text-slate-200">Backup e etapa obrigatoria antes de qualquer deploy ou restore em ambiente sensivel.</p></div><ul class="mt-4 list-disc space-y-1 pl-5 text-sm text-slate-300"><li>Inclui dump do banco, pacote de uploads e manifesto de integridade.</li><li>Deve ser executado antes de deploy, restore ou mudanca estrutural.</li><li>Producao usa credenciais `BACKUP_PRODUCTION_*` para DB remoto e FTP remoto.</li><li>Restore so deve ocorrer com confirmacao explicita e backup anterior preservado.</li><li>A modal de Backup e Restore agora acompanha as etapas reais da rotina: exportacao do banco, coleta de uploads, compactacao, verificacao, restore de banco e restore de uploads.</li><li>O envio para nuvem continua sendo uma trilha separada, na subaba `Nuvem`, para nao misturar recovery local com transporte remoto.</li></ul>
          <div class="mt-4 grid gap-4 xl:grid-cols-2">
            <div class="doc-card">
              <p class="doc-label">Tipos de acao</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                <li><strong>Backup de ambiente</strong>: gera novo backup completo de banco + uploads.</li>
                <li><strong>Verificar ultimo</strong>: checa integridade do manifesto, banco e uploads do ultimo backup.</li>
                <li><strong>Restore completo</strong>: recompõe o ambiente com banco + uploads de um backup escolhido.</li>
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
              <li>Executar o backup e acompanhar na modal as etapas de banco, uploads, compactacao e manifesto.</li>
              <li>Confirmar o novo <code>backup_id</code> no resumo e no historico paginado.</li>
              <li>Executar `Verificar ultimo` se o backup for entrar em ciclo critico de deploy ou restore.</li>
            </ol>
          </div>
          <div class="doc-card mt-4">
            <p class="doc-label">Passo a passo - restore</p>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-200">
              <li>Preservar um backup anterior do estado atual antes de restaurar.</li>
              <li>Escolher o <code>backup_id</code> correto e confirmar o destino exato.</li>
              <li>Acompanhar a rotina pelas etapas reais de restore do banco, extracao e restore de uploads.</li>
              <li>Executar validacao funcional minima do ambiente apos concluir.</li>
            </ol>
          </div>
          <div class="doc-card doc-alert mt-4">
            <p class="doc-label">Cuidados</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-100">
              <li>Restore completo sobrescreve banco e uploads do destino.</li>
              <li>Nao usar restore para "testar" fluxo em producao.</li>
              <li>Se a verificacao falhar, nao reutilizar o backup ate corrigir a integridade.</li>
            </ul>
          </div>
        </section>

        <section id="dropbox" data-doc-panel="dropbox" class="doc-panel rounded-3xl border border-slate-800 bg-slate-900/80 p-6" <?= $docSection === 'dropbox' ? '' : 'hidden' ?>>
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">10) Dropbox e backup em nuvem</h2>
          <div class="doc-card doc-rule mt-4">
            <p class="doc-label">Objetivo</p>
            <p class="mt-2 text-sm text-slate-200">A rotina de nuvem envia o backup de ambiente ja validado para o Dropbox sem remover a copia local. Ela existe para ampliar recuperacao, retenção e seguranca operacional fora da maquina local.</p>
          </div>
          <div class="mt-4 grid gap-4 xl:grid-cols-2">
            <div class="doc-card">
              <p class="doc-label">O que sobe</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                <li><code>manifest.json</code> do backup</li>
                <li><code>database.sql</code> do ambiente</li>
                <li><code>uploads.zip</code> do ambiente</li>
                <li>Metadados locais de envio e confirmacao</li>
              </ul>
            </div>
            <div class="doc-card">
              <p class="doc-label">Modos de uso</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                <li>Conexao OAuth com a conta Dropbox</li>
                <li>Envio manual do ultimo backup</li>
                <li>Envio manual de backup especifico</li>
                <li>Envio automatico logo apos gerar backup</li>
              </ul>
            </div>
          </div>
          <div class="doc-card doc-example mt-4">
            <p class="doc-label">Estrutura remota</p>
            <pre class="mt-2 overflow-x-auto text-xs leading-6 text-slate-200"><code>/Estrategia Nerd/backups-ambiente/local/{BACKUP_ID}
/Estrategia Nerd/backups-ambiente/stage/{BACKUP_ID}
/Estrategia Nerd/backups-ambiente/production/{BACKUP_ID}</code></pre>
            <p class="mt-3 text-sm text-slate-300">Cada pasta de backup remoto recebe o manifesto, o dump do banco e o zip de uploads do ambiente correspondente.</p>
          </div>
          <div class="doc-card mt-4">
            <p class="doc-label">Historico e rastreabilidade</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
              <li>A subaba <strong>Nuvem</strong> separa <strong>Conexao e envio</strong> de <strong>Historico de envios</strong>.</li>
              <li>O manifesto local passa a registrar provedor, destino remoto, horario e status do envio.</li>
              <li>Ao concluir, a confirmacao permanece na propria tela da nuvem, sem jogar o usuario para fora do contexto.</li>
              <li>A modal do envio mostra etapas reais, incluindo pasta remota, manifesto, banco, uploads e gravacao do historico local.</li>
            </ul>
          </div>
          <div class="doc-card mt-4">
            <p class="doc-label">Passo a passo - primeira conexao</p>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-200">
              <li>Criar o app no Dropbox com acesso <strong>App folder</strong>.</li>
              <li>Configurar <code>BACKUP_DROPBOX_APP_KEY</code>, <code>BACKUP_DROPBOX_APP_SECRET</code> e <code>BACKUP_DROPBOX_REDIRECT_URI</code>.</li>
              <li>Marcar os scopes necessarios de arquivos e metadados.</li>
              <li>Conectar a conta pela subaba `Nuvem` e confirmar que o painel saiu de "Configuracao pendente".</li>
            </ol>
          </div>
          <div class="doc-card mt-4">
            <p class="doc-label">Passo a passo - envio</p>
            <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-200">
              <li>Garantir que existe um backup valido no ambiente desejado.</li>
              <li>Usar `Enviar ultimo backup` ou escolher um backup especifico.</li>
              <li>Acompanhar manifesto, banco, uploads e finalizacao do historico pela modal de progresso real.</li>
              <li>Conferir o item novo no `Historico de envios` com destino e horario.</li>
            </ol>
          </div>
          <div class="doc-card doc-alert mt-4">
            <p class="doc-label">Cuidados</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-100">
              <li>Dropbox complementa o backup local; nao substitui a copia local nem o restore controlado.</li>
              <li>Credenciais OAuth e tokens devem ficar apenas no ambiente local, nunca em codigo versionado.</li>
              <li>Se mudar permissao do app no Dropbox, a conta pode precisar reconectar para renovar os scopes.</li>
            </ul>
          </div>
        </section>

        <section id="ambiente" data-doc-panel="ambiente" class="doc-panel rounded-3xl border border-slate-800 bg-slate-900/80 p-6" <?= $docSection === 'ambiente' ? '' : 'hidden' ?>><h2 class="font-orbitron text-xl font-bold text-cyan-200">11) Configuracoes de ambiente (.env)</h2><div class="mt-4 overflow-x-auto"><table class="doc-table"><thead><tr><th>Variavel</th><th>Finalidade</th><th>Obs</th></tr></thead><tbody><tr class="doc-row"><td class="text-white">APP_ENV / APP_DEBUG / APP_URL</td><td class="text-slate-300">Controle de ambiente, debug e URL base.</td><td class="text-slate-400">Producao deve estar com debug desligado.</td></tr><tr class="doc-row"><td class="text-white">DB_HOST / DB_PORT / DB_DATABASE / DB_USERNAME / DB_PASSWORD</td><td class="text-slate-300">Conexao principal do sistema.</td><td class="text-slate-400">Nao versionar segredo real.</td></tr><tr class="doc-row"><td class="text-white">BACKUP_PRODUCTION_*</td><td class="text-slate-300">Acesso remoto para backup de producao.</td><td class="text-slate-400">Obrigatorio para backup remoto.</td></tr><tr class="doc-row"><td class="text-white">BACKUP_DROPBOX_APP_KEY / BACKUP_DROPBOX_APP_SECRET</td><td class="text-slate-300">Credenciais OAuth do app Dropbox.</td><td class="text-slate-400">Devem ficar apenas no `.env` local.</td></tr><tr class="doc-row"><td class="text-white">BACKUP_DROPBOX_REDIRECT_URI</td><td class="text-slate-300">Callback local da autorizacao.</td><td class="text-slate-400">Precisa bater exatamente com o app no Dropbox.</td></tr><tr class="doc-row"><td class="text-white">BACKUP_DROPBOX_REMOTE_ROOT</td><td class="text-slate-300">Raiz remota do backup em nuvem.</td><td class="text-slate-400">Base da estrutura `local / stage / production`.</td></tr><tr class="doc-row"><td class="text-white">CONTENT_SYNC_CURRENT_SOURCE</td><td class="text-slate-300">Identifica a origem atual da operacao (`local`, `stage`, etc.).</td><td class="text-slate-400">Usado para travar publish inseguro.</td></tr><tr class="doc-row"><td class="text-white">CONTENT_SYNC_APPROVED_PACKAGE_SOURCE</td><td class="text-slate-300">Origem autorizada para pacote de producao.</td><td class="text-slate-400">Padrao recomendado: `stage`.</td></tr><tr class="doc-row"><td class="text-white">CONTENT_SYNC_STAGE_LABEL</td><td class="text-slate-300">Nome humano da stage oficial.</td><td class="text-slate-400">Ex.: `estrategia-nerd-stage`.</td></tr><tr class="doc-row"><td class="text-white">CONTENT_SYNC_STAGE_DB_*</td><td class="text-slate-300">Banco remoto da stage para homologacao.</td><td class="text-slate-400">Nao reaproveitar credencial de producao.</td></tr><tr class="doc-row"><td class="text-white">CONTENT_SYNC_STAGE_FTP_*</td><td class="text-slate-300">Uploads remotos da stage.</td><td class="text-slate-400">Mantem midias e banco homologados no mesmo ambiente.</td></tr><tr class="doc-row"><td class="text-white">CONTENT_SYNC_STAGE_CODE_*</td><td class="text-slate-300">Deploy tecnico dedicado da stage.</td><td class="text-slate-400">Separar raiz tecnica da stage da raiz de producao.</td></tr><tr class="doc-row"><td class="text-white">CONTENT_SYNC_PRODUCTION_*</td><td class="text-slate-300">Sync de conteudo e deploy de codigo.</td><td class="text-slate-400">Separar dados de conteudo e codigo.</td></tr></tbody></table></div><p class="mt-3 text-sm text-slate-300">Seguranca: `.env` nao pode ser commitado com credencial real. Usar `.env.example` como referencia limpa.</p></section>

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
        </section>

        <section id="diagnostico" data-doc-panel="diagnostico" class="doc-panel rounded-3xl border border-slate-800 bg-slate-900/80 p-6" <?= $docSection === 'diagnostico' ? '' : 'hidden' ?>><h2 class="font-orbitron text-xl font-bold text-cyan-200">13) Erros comuns e diagnostico rapido</h2><div class="mt-4 overflow-x-auto"><table class="doc-table"><thead><tr><th>Problema</th><th>Possivel causa</th><th>Acao recomendada</th></tr></thead><tbody><tr class="doc-row"><td class="text-white">Imagem nao aparece</td><td class="text-slate-300">Path quebrado ou arquivo fora de uploads.</td><td class="text-slate-400">Revisar referencia no banco e em `public/uploads`.</td></tr><tr class="doc-row"><td class="text-white">Rota nao carrega</td><td class="text-slate-300">Regra em `config/routes.php`, `public/index.php` ou `.htaccess` incorreta.</td><td class="text-slate-400">Rodar preflight e validar entrada publica.</td></tr><tr class="doc-row"><td class="text-white">Conteudo nao sincroniza</td><td class="text-slate-300">Pacote invalido, lock ativo ou alvo remoto incompleto.</td><td class="text-slate-400">Verificar manifesto, lock e credenciais `CONTENT_SYNC_PRODUCTION_*`.</td></tr><tr class="doc-row"><td class="text-white">Pacote falha na validacao</td><td class="text-slate-300">JSON ausente, upload faltando ou manifesto inconsistente.</td><td class="text-slate-400">Executar verificacao do pacote e revisar arquivos referenciados.</td></tr><tr class="doc-row"><td class="text-white">Dropbox falha no envio</td><td class="text-slate-300">Scope ausente, redirect URI divergente ou token invalido.</td><td class="text-slate-400">Revisar app no Dropbox, scopes, callback e reconectar a conta.</td></tr><tr class="doc-row"><td class="text-white">Texto com acentuacao quebrada</td><td class="text-slate-300">Arquivo salvo com encoding invalido ou copia com mojibake.</td><td class="text-slate-400">Executar preflight e corrigir na origem antes do deploy.</td></tr></tbody></table></div>
          <div class="mt-4 grid gap-4 xl:grid-cols-3">
            <div class="doc-card">
              <p class="doc-label">Troubleshooting - backup</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                <li>Checar <code>backup_root</code> e perfil do ambiente.</li>
                <li>Confirmar manifesto, <code>database.sql</code> e <code>uploads.zip</code>.</li>
                <li>Se a verificacao falhar, nao usar o backup no restore nem no Dropbox.</li>
              </ul>
            </div>
            <div class="doc-card">
              <p class="doc-label">Troubleshooting - conteudo</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                <li>Checar origem real do pacote e destino aplicado.</li>
                <li>Validar lock, manifesto, JSONs e uploads referenciados.</li>
                <li>Conferir "Ultimas aplicacoes editoriais" para evidenciar o alvo final.</li>
              </ul>
            </div>
            <div class="doc-card">
              <p class="doc-label">Troubleshooting - codigo</p>
              <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
                <li>Rodar preflight e lint antes de reaplicar pacote tecnico.</li>
                <li>Conferir backup tecnico associado e ultimo deploy tecnico visivel na Central.</li>
                <li>Se o problema persistir, restaurar pacote estavel antes de novo hotfix.</li>
              </ul>
            </div>
          </div>
        </section>

        <section id="evolucao" data-doc-panel="evolucao" class="doc-panel rounded-3xl border border-slate-800 bg-slate-900/80 p-6" <?= $docSection === 'evolucao' ? '' : 'hidden' ?>><h2 class="font-orbitron text-xl font-bold text-cyan-200">14) Evolucao tecnica</h2><div class="doc-card doc-example mt-4"><ul class="list-disc space-y-1 pl-5 text-sm text-slate-200"><li>Versao inicial do portal editorial e dashboard admin.</li><li>Criacao da Central Nerd como pagina de conversao.</li><li>Adicao da rotina local de backup com restore controlado.</li><li>Implementacao da sincronizacao de conteudo/codigo com paridade.</li><li>Refino dos hubs tecnicos no admin local com navegacao assincrona.</li><li>Modal operacional de Conteudo com progresso real e rastreabilidade de aplicacao de pacotes.</li><li>Modal operacional de Backup e Restore com progresso real para backup, verificacao e restore.</li><li>Integracao Dropbox/Nuvem para backup de ambiente, com OAuth, envio manual, envio automatico e historico paginado.</li><li>Reforco de preflight, documentacao interna, governanca de releases e bloqueio por origem aprovada.</li></ul></div></section>

        <section id="governanca" data-doc-panel="governanca" class="doc-panel rounded-3xl border border-fuchsia-500/20 bg-slate-900/80 p-6" <?= $docSection === 'governanca' ? '' : 'hidden' ?>><h2 class="font-orbitron text-xl font-bold text-fuchsia-200">15) Governanca da documentacao</h2><p class="mt-3 text-sm leading-7 text-slate-300">Esta pagina e a documentacao principal local do portal. Toda mudanca estrutural, operacional, de release ou de deploy deve atualizar esta base no mesmo ciclo de trabalho.</p><div class="mt-4 rounded-2xl border border-fuchsia-400/20 bg-fuchsia-500/10 p-4 text-sm text-fuchsia-50"><ol class="list-decimal space-y-1 pl-5"><li>Atualizar a secao impactada da documentacao principal.</li><li>Revisar compatibilidade com `docs/CODEX-REGRAS-PERMANENTES.txt`.</li><li>Revisar tambem `docs/governanca/priorizacao-tecnica.md` quando a alteracao mudar risco, prioridade ou criterio de execucao.</li><li>Atualizar feature/release/checklist/rollback quando a alteracao exigir.</li><li>Executar preflight antes de qualquer empacotamento.</li><li>Registrar observacao operacional no fechamento da tarefa.</li></ol></div></section>
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
