<?php

declare(strict_types=1);

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
?>
<section class="min-h-screen bg-slate-950 px-4 py-8 text-slate-100">
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
  </style>
  <div class="mx-auto max-w-7xl space-y-6">
    <?php \App\Support\View::component('site/local-tools-nav', ['active' => 'docs']); ?>

    <header class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_40px_rgba(6,182,212,0.08)]">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Documentacao Interna Local</p>
      <h1 class="mt-2 font-orbitron text-3xl font-black tracking-tight text-white">Base Tecnica Operacional - <?= htmlspecialchars($projectName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
      <p class="mt-3 max-w-4xl text-sm leading-7 text-slate-300">Painel de referencia tecnica para desenvolvimento, manutencao, backup, deploy e operacao editorial. Esta documentacao e interna/local e deve evoluir junto com o sistema e com as regras permanentes do portal.</p>
    </header>

    <article class="grid gap-6 xl:grid-cols-[0.92fr_2fr]">
      <aside class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <h2 class="font-orbitron text-base font-bold text-white">Indice</h2>
        <nav class="mt-4 space-y-2 text-sm">
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#identidade">1. Identidade do projeto</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#arquitetura">2. Arquitetura tecnica</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#rotas-principais">3. Rotas principais do sistema</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#pastas">4. Responsabilidade de pastas</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#regras-dev">5. Regras de desenvolvimento</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#seguranca">6. Seguranca do sistema</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#padrao-codigo">7. Padrao de codigo e nomenclatura</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#deploy">8. Deploy operacional</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#backup">9. Backup e restore</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#ambiente">10. Configuracao de ambiente</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#diagnostico">11. Erros comuns e diagnostico</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#evolucao">12. Evolucao tecnica</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#governanca">13. Governanca da documentacao</a>
        </nav>
      </aside>

      <div class="space-y-6">
        <section id="identidade" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
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

        <section id="arquitetura" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">2) Arquitetura tecnica</h2>
          <p class="mt-2 text-sm leading-7 text-slate-300">Arquitetura MVC leve com separacao entre orquestracao (Controller), regra de negocio (Service), acesso a dados (Repository), persistencia (Database) e renderizacao (View).</p>
          <div class="doc-card doc-rule mt-4"><p class="doc-label">Fluxo base</p><pre class="mt-2 overflow-x-auto text-xs leading-6 text-slate-200"><code>Request -> Router -> Controller -> Service -> Repository -> Database
                                                -> ViewModel -> View -> Response</code></pre></div>
          <div class="doc-card doc-example mt-4"><p class="doc-label">ViewModel</p><ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200"><li>Nasce no Service para preparar dados de exibicao.</li><li>Evita dependencia direta da View com Repository/SQL.</li><li>Nao deve concentrar regra de negocio pesada.</li></ul></div>
        </section>

        <section id="rotas-principais" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
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

        <section id="pastas" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
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

        <section id="regras-dev" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6"><h2 class="font-orbitron text-xl font-bold text-cyan-200">5) Regras de desenvolvimento</h2><div class="doc-card doc-rule mt-4"><ul class="list-disc space-y-1 pl-5 text-sm text-slate-200"><li>Controller nao acessa banco diretamente.</li><li>Service concentra regra de negocio e coerencia entre modulos.</li><li>Repository concentra acesso a dados.</li><li>View nao carrega regra de negocio complexa.</li><li>Scripts locais sao para operacao controlada.</li><li>Hotfix em producao deve voltar para local/stage no mesmo ciclo.</li><li>Toda alteracao funcional deve atualizar esta documentacao.</li></ul></div></section>

        <section id="seguranca" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6"><h2 class="font-orbitron text-xl font-bold text-cyan-200">6) Seguranca do sistema</h2><div class="doc-card doc-alert mt-4"><p class="doc-label">Alerta critico</p><p class="mt-2 text-sm text-amber-100">Todo deploy deve garantir que rotas e arquivos internos nao fiquem expostos publicamente.</p></div><ul class="mt-4 list-disc space-y-1 pl-5 text-sm text-slate-300"><li>Rotas locais (`/local/*` e `/dev`) devem ficar restritas ao ambiente local.</li><li>`.env` nao pode ser servido pelo web server.</li><li>Upload deve validar tipo, MIME, extensao e tamanho.</li><li>Sessao administrativa e formularios sensiveis devem usar CSRF.</li><li>`public/index.php` e `public/.htaccess` sao arquivos criticos e exigem validacao separada.</li><li>Uploads nao devem permitir execucao de scripts no servidor web.</li></ul></section>

        <section id="padrao-codigo" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6"><h2 class="font-orbitron text-xl font-bold text-cyan-200">7) Padrao de codigo e nomenclatura</h2><div class="doc-card doc-rule mt-4"><ul class="list-disc space-y-1 pl-5 text-sm text-slate-200"><li>Classes em PascalCase.</li><li>Metodos em camelCase.</li><li>Controllers terminam com `Controller`.</li><li>Services terminam com `Service`.</li><li>Repositories terminam com `Repository`.</li><li>Variaveis devem ser descritivas.</li><li>Nome de arquivo deve refletir responsabilidade.</li></ul></div></section>

        <section id="deploy" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">8) Deploy operacional (codigo e conteudo)</h2>
          <div class="doc-card <?= $productionAllowed ? 'doc-rule' : 'doc-alert' ?> mt-4"><p class="doc-label">Politica atual</p><p class="mt-2 text-sm <?= $productionAllowed ? 'text-slate-100' : 'text-amber-100' ?>">Origem atual: <strong><?= htmlspecialchars($currentSource, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>. Origem aprovada para pacote de producao: <strong><?= htmlspecialchars($approvedSource, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong> (<?= htmlspecialchars($stageLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>).</p><p class="mt-2 text-sm <?= $productionAllowed ? 'text-slate-300' : 'text-amber-100' ?>"><?= $productionAllowed ? 'Este ambiente esta autorizado para gerar/publicar pacote de producao.' : 'Este ambiente nao pode publicar em producao. O local continua valido para desenvolvimento, teste e validacao.' ?></p></div>
          <ol class="mt-4 list-decimal space-y-1 pl-5 text-sm text-slate-300"><li>Alteracao no ambiente local</li><li>Teste local</li><li>Aplicar a mesma mudanca na stage (`<?= htmlspecialchars($stageLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>`)</li><li>Teste em stage</li><li>Registrar alteracao (commit/log)</li><li>Backup obrigatorio</li><li>Gerar pacote apenas da stage</li><li>Publicar em producao</li><li>Executar pos-check e restaurar paridade</li></ol>
          <div class="doc-card doc-example mt-4"><p class="doc-label">Checklist minimo antes de empacotar</p><ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200"><li>Executar `php scripts/preflight-check.php`.</li><li>Confirmar origem atual x origem aprovada.</li><li>Validar `public/index.php` e `public/.htaccess`.</li><li>Conferir rotas criticas, assets, uploads e encoding.</li><li>Garantir pacote sem dados ficticios e sem artefatos locais.</li></ul></div>
          <div class="doc-card doc-example mt-4"><p class="doc-label">Rollback e hotfix</p><ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-200"><li>Interromper novas publicacoes.</li><li>Restaurar ultimo backup valido.</li><li>Reaplicar pacote tecnico estavel.</li><li>Validar paginas criticas e dashboard.</li><li>Replicar hotfix em stage/local antes de encerrar a tarefa.</li></ol></div>
        </section>

        <section id="backup" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6"><h2 class="font-orbitron text-xl font-bold text-cyan-200">9) Backup e restore</h2><div class="doc-card doc-rule mt-4"><p class="doc-label">Visao geral</p><p class="mt-2 text-sm text-slate-200">Backup e etapa obrigatoria antes de qualquer deploy ou restore em ambiente sensivel.</p></div><ul class="mt-4 list-disc space-y-1 pl-5 text-sm text-slate-300"><li>Inclui dump do banco, pacote de uploads e manifesto de integridade.</li><li>Deve ser executado antes de deploy, restore ou mudanca estrutural.</li><li>Producao usa credenciais `BACKUP_PRODUCTION_*` para DB remoto e FTP remoto.</li><li>Restore so deve ocorrer com confirmacao explicita e backup anterior preservado.</li></ul></section>

        <section id="ambiente" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6"><h2 class="font-orbitron text-xl font-bold text-cyan-200">10) Configuracoes de ambiente (.env)</h2><div class="mt-4 overflow-x-auto"><table class="doc-table"><thead><tr><th>Variavel</th><th>Finalidade</th><th>Obs</th></tr></thead><tbody><tr class="doc-row"><td class="text-white">APP_ENV / APP_DEBUG / APP_URL</td><td class="text-slate-300">Controle de ambiente, debug e URL base.</td><td class="text-slate-400">Producao deve estar com debug desligado.</td></tr><tr class="doc-row"><td class="text-white">DB_HOST / DB_PORT / DB_DATABASE / DB_USERNAME / DB_PASSWORD</td><td class="text-slate-300">Conexao principal do sistema.</td><td class="text-slate-400">Nao versionar segredo real.</td></tr><tr class="doc-row"><td class="text-white">BACKUP_PRODUCTION_*</td><td class="text-slate-300">Acesso remoto para backup de producao.</td><td class="text-slate-400">Obrigatorio para backup remoto.</td></tr><tr class="doc-row"><td class="text-white">CONTENT_SYNC_CURRENT_SOURCE</td><td class="text-slate-300">Identifica a origem atual da operacao (`local`, `stage`, etc.).</td><td class="text-slate-400">Usado para travar publish inseguro.</td></tr><tr class="doc-row"><td class="text-white">CONTENT_SYNC_APPROVED_PACKAGE_SOURCE</td><td class="text-slate-300">Origem autorizada para pacote de producao.</td><td class="text-slate-400">Padrao recomendado: `stage`.</td></tr><tr class="doc-row"><td class="text-white">CONTENT_SYNC_STAGE_LABEL</td><td class="text-slate-300">Nome humano da stage oficial.</td><td class="text-slate-400">Ex.: `estrategia-nerd-stage`.</td></tr><tr class="doc-row"><td class="text-white">CONTENT_SYNC_STAGE_DB_*</td><td class="text-slate-300">Banco remoto da stage para homologacao.</td><td class="text-slate-400">Nao reaproveitar credencial de producao.</td></tr><tr class="doc-row"><td class="text-white">CONTENT_SYNC_STAGE_FTP_*</td><td class="text-slate-300">Uploads remotos da stage.</td><td class="text-slate-400">Mantem midias e banco homologados no mesmo ambiente.</td></tr><tr class="doc-row"><td class="text-white">CONTENT_SYNC_STAGE_CODE_*</td><td class="text-slate-300">Deploy tecnico dedicado da stage.</td><td class="text-slate-400">Separar raiz tecnica da stage da raiz de producao.</td></tr><tr class="doc-row"><td class="text-white">CONTENT_SYNC_PRODUCTION_*</td><td class="text-slate-300">Sync de conteudo e deploy de codigo.</td><td class="text-slate-400">Separar dados de conteudo e codigo.</td></tr></tbody></table></div><p class="mt-3 text-sm text-slate-300">Seguranca: `.env` nao pode ser commitado com credencial real. Usar `.env.example` como referencia limpa.</p></section>

        <section id="diagnostico" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6"><h2 class="font-orbitron text-xl font-bold text-cyan-200">11) Erros comuns e diagnostico rapido</h2><div class="mt-4 overflow-x-auto"><table class="doc-table"><thead><tr><th>Problema</th><th>Possivel causa</th><th>Acao recomendada</th></tr></thead><tbody><tr class="doc-row"><td class="text-white">Imagem nao aparece</td><td class="text-slate-300">Path quebrado ou arquivo fora de uploads.</td><td class="text-slate-400">Revisar referencia no banco e em `public/uploads`.</td></tr><tr class="doc-row"><td class="text-white">Rota nao carrega</td><td class="text-slate-300">Regra em `config/routes.php`, `public/index.php` ou `.htaccess` incorreta.</td><td class="text-slate-400">Rodar preflight e validar entrada publica.</td></tr><tr class="doc-row"><td class="text-white">Conteudo nao sincroniza</td><td class="text-slate-300">Pacote invalido, lock ativo ou alvo remoto incompleto.</td><td class="text-slate-400">Verificar manifesto, lock e credenciais `CONTENT_SYNC_PRODUCTION_*`.</td></tr><tr class="doc-row"><td class="text-white">Pacote falha na validacao</td><td class="text-slate-300">JSON ausente, upload faltando ou manifesto inconsistente.</td><td class="text-slate-400">Executar verificacao do pacote e revisar arquivos referenciados.</td></tr><tr class="doc-row"><td class="text-white">Texto com acentuacao quebrada</td><td class="text-slate-300">Arquivo salvo com encoding invalido ou copia com mojibake.</td><td class="text-slate-400">Executar preflight e corrigir na origem antes do deploy.</td></tr></tbody></table></div></section>

        <section id="evolucao" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6"><h2 class="font-orbitron text-xl font-bold text-cyan-200">12) Evolucao tecnica</h2><div class="doc-card doc-example mt-4"><ul class="list-disc space-y-1 pl-5 text-sm text-slate-200"><li>Versao inicial do portal editorial e dashboard admin.</li><li>Criacao da Central Nerd como pagina de conversao.</li><li>Adicao da rotina local de backup com restore controlado.</li><li>Implementacao da sincronizacao de conteudo/codigo com paridade.</li><li>Reforco de preflight, documentacao interna e bloqueio por origem aprovada.</li></ul></div></section>

        <section id="governanca" class="rounded-3xl border border-fuchsia-500/20 bg-slate-900/80 p-6"><h2 class="font-orbitron text-xl font-bold text-fuchsia-200">13) Governanca da documentacao</h2><p class="mt-3 text-sm leading-7 text-slate-300">Esta pagina e a documentacao principal local do portal. Toda mudanca estrutural, operacional ou de deploy deve atualizar esta base no mesmo ciclo de trabalho.</p><div class="mt-4 rounded-2xl border border-fuchsia-400/20 bg-fuchsia-500/10 p-4 text-sm text-fuchsia-50"><ol class="list-decimal space-y-1 pl-5"><li>Atualizar a secao impactada da documentacao principal.</li><li>Revisar compatibilidade com `docs/CODEX-REGRAS-PERMANENTES.txt`.</li><li>Executar preflight antes de qualquer empacotamento.</li><li>Registrar observacao operacional no fechamento da tarefa.</li></ol></div></section>
      </div>
    </article>
  </div>
</section>
