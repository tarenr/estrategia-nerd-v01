<?php

declare(strict_types=1);

$projectVersion = trim((string) ($project_version ?? 'local'));
$generatedAt = trim((string) ($generated_at ?? date('Y-m-d H:i:s')));
$projectName = (string) config('app.name', 'Estrategia Nerd');
$projectType = 'Portal editorial + conversao';
$projectStack = 'PHP (MVC leve), MySQL, Tailwind (CDN)';
$projectEnvironment = 'Local / homologacao';
$projectOwner = (string) ($_ENV['PROJECT_OWNER'] ?? 'Taren');
$projectScope = 'Operacao local, backup, deploy e conteudo';
?>
<section class="min-h-screen bg-slate-950 px-4 py-8 text-slate-100">
  <style>
    .doc-card {
      border: 1px solid rgba(51, 65, 85, 0.9);
      background: rgba(2, 6, 23, 0.65);
      border-radius: 1rem;
      padding: 1rem;
    }

    .doc-label {
      font-family: Orbitron, ui-sans-serif, system-ui;
      font-size: 0.66rem;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: rgba(148, 163, 184, 0.95);
    }

    .doc-rule {
      border: 1px solid rgba(34, 211, 238, 0.25);
      background: rgba(8, 47, 73, 0.45);
    }

    .doc-alert {
      border: 1px solid rgba(251, 191, 36, 0.28);
      background: rgba(120, 53, 15, 0.28);
    }

    .doc-example {
      border: 1px solid rgba(192, 132, 252, 0.26);
      background: rgba(59, 7, 100, 0.23);
    }

    .doc-table {
      min-width: 100%;
      border-collapse: separate;
      border-spacing: 0 0.55rem;
      font-size: 0.875rem;
    }

    .doc-table th {
      text-align: left;
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 0.2em;
      color: rgba(100, 116, 139, 1);
      padding: 0.5rem 0.75rem;
    }

    .doc-table td {
      padding: 0.7rem 0.75rem;
      vertical-align: top;
    }

    .doc-row {
      border: 1px solid rgba(51, 65, 85, 0.95);
      background: rgba(2, 6, 23, 0.7);
    }
  </style>
  <div class="mx-auto max-w-7xl space-y-6">
    <?php \App\Support\View::component('site/local-tools-nav', ['active' => 'docs']); ?>

    <header class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_40px_rgba(6,182,212,0.08)]">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Documentacao Interna Local</p>
      <h1 class="mt-2 font-orbitron text-3xl font-black tracking-tight text-white">Base Tecnica Operacional - <?= htmlspecialchars($projectName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
      <p class="mt-3 max-w-4xl text-sm leading-7 text-slate-300">
        Painel de referencia tecnica para desenvolvimento, manutencao, backup, deploy e operacao editorial.
        Esta documentacao e interna/local e deve evoluir junto com o sistema.
      </p>
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
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#padrao-secoes">13. Padrao de secao</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#governanca">14. Governanca da documentacao</a>
        </nav>
      </aside>

      <div class="space-y-6">
        <section id="identidade" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">1) Identidade do projeto</h2>
          <p class="mt-2 text-sm leading-7 text-slate-300">Visao geral de referencia rapida para manutencao e operacao.</p>
          <div class="doc-card doc-rule mt-4">
            <p class="doc-label">Visao geral</p>
            <p class="mt-2 text-sm text-slate-200">Esta base e interna/local, com foco em confiabilidade operacional e evolucao controlada do sistema.</p>
          </div>
          <div class="mt-4 overflow-x-auto">
            <table class="doc-table">
              <tbody>
                <tr class="doc-row"><td class="text-slate-500">Projeto</td><td class="text-white"><?= htmlspecialchars($projectName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
                <tr class="doc-row"><td class="text-slate-500">Tipo</td><td class="text-white"><?= htmlspecialchars($projectType, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
                <tr class="doc-row"><td class="text-slate-500">Stack</td><td class="text-white"><?= htmlspecialchars($projectStack, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
                <tr class="doc-row"><td class="text-slate-500">Ambiente</td><td class="text-white"><?= htmlspecialchars($projectEnvironment, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
                <tr class="doc-row"><td class="text-slate-500">Responsavel</td><td class="text-white"><?= htmlspecialchars($projectOwner, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
                <tr class="doc-row"><td class="text-slate-500">Versao</td><td class="text-white"><?= htmlspecialchars($projectVersion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
                <tr class="doc-row"><td class="text-slate-500">Ultima atualizacao</td><td class="text-white"><?= htmlspecialchars($generatedAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
                <tr class="doc-row"><td class="text-slate-500">Escopo</td><td class="text-white"><?= htmlspecialchars($projectScope, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
              </tbody>
            </table>
          </div>
        </section>

        <section id="arquitetura" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">2) Arquitetura tecnica</h2>

          <h3 class="mt-4 font-orbitron text-sm font-semibold uppercase tracking-[0.2em] text-slate-300">Visao geral</h3>
          <p class="mt-2 text-sm leading-7 text-slate-300">Arquitetura MVC leve com separacao entre orquestracao (Controller), regra de negocio (Service), acesso a dados (Repository), persistencia (Database) e renderizacao (View).</p>

          <h3 class="mt-4 font-orbitron text-sm font-semibold uppercase tracking-[0.2em] text-slate-300">Fluxo base</h3>
          <div class="doc-card doc-rule mt-2">
            <pre class="overflow-x-auto text-xs leading-6 text-slate-200"><code>Request -> Router -> Controller -> Service -> Repository -> Database
                                                -> ViewModel -> View -> Response</code></pre>
          </div>

          <h3 class="mt-4 font-orbitron text-sm font-semibold uppercase tracking-[0.2em] text-slate-300">Conceito de ViewModel</h3>
          <div class="doc-card doc-example mt-2">
            <p class="doc-label">Como funciona</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
              <li>ViewModel e montado no Service para preparar dados de tela.</li>
              <li>Concentra dados de exibicao (titulos, listas, metadados e flags).</li>
              <li>Evita que a View dependa de Repository/SQL.</li>
              <li>Nao deve concentrar regra de negocio pesada.</li>
            </ul>
          </div>

          <h3 class="mt-4 font-orbitron text-sm font-semibold uppercase tracking-[0.2em] text-slate-300">Exemplo pratico 1 (post publico)</h3>
          <pre class="mt-2 rounded-2xl border border-slate-800 bg-slate-950/80 p-4 text-xs leading-6 text-slate-300"><code>GET /post/{slug}
-> App\Controllers\Site\PostController::show
-> App\Services\Site\PostService::getViewModel
-> App\Repositories\PostRepository / ComentarioRepository / EstatisticaRepository
-> MySQL (posts, comentarios, estatisticas)
-> View::render('site/post', $viewModel)</code></pre>

          <h3 class="mt-4 font-orbitron text-sm font-semibold uppercase tracking-[0.2em] text-slate-300">Exemplo pratico 2 (admin links)</h3>
          <pre class="mt-2 rounded-2xl border border-slate-800 bg-slate-950/80 p-4 text-xs leading-6 text-slate-300"><code>POST /admin/criar-link
-> App\Controllers\Admin\LinksController::store
-> App\Services\Admin\LinksService::createLink
-> App\Repositories\LinkRepository (+ LinkClickRepository quando aplicavel)
-> View::render('admin/links/create' | redirect para /admin/links)</code></pre>
        </section>

        <section id="rotas-principais" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">3) Rotas principais do sistema</h2>
          <div class="doc-card doc-rule mt-4">
            <p class="doc-label">Visao geral</p>
            <p class="mt-2 text-sm text-slate-200">Mapa rapido para manutencao, observabilidade e debugging.</p>
          </div>
          <div class="mt-4 overflow-x-auto">
            <table class="doc-table">
              <thead>
                <tr>
                  <th>Rota</th>
                  <th>Controller</th>
                  <th>Metodo</th>
                </tr>
              </thead>
              <tbody>
                <tr class="doc-row"><td class="text-white">/</td><td class="text-slate-300">HomeController</td><td class="text-slate-400">index</td></tr>
                <tr class="doc-row"><td class="text-white">/blog</td><td class="text-slate-300">BlogController</td><td class="text-slate-400">index</td></tr>
                <tr class="doc-row"><td class="text-white">/post/{slug}</td><td class="text-slate-300">PostController</td><td class="text-slate-400">show</td></tr>
                <tr class="doc-row"><td class="text-white">/central-nerd</td><td class="text-slate-300">CentralController</td><td class="text-slate-400">index</td></tr>
                <tr class="doc-row"><td class="text-white">/admin</td><td class="text-slate-300">DashboardController</td><td class="text-slate-400">index</td></tr>
                <tr class="doc-row"><td class="text-white">/local/backup</td><td class="text-slate-300">BackupToolsController</td><td class="text-slate-400">index / handle</td></tr>
                <tr class="doc-row"><td class="text-white">/local/conteudo</td><td class="text-slate-300">ContentSyncToolsController</td><td class="text-slate-400">index / handle</td></tr>
                <tr class="doc-row"><td class="text-white">/local/documentacao</td><td class="text-slate-300">LocalDocsController</td><td class="text-slate-400">index</td></tr>
              </tbody>
            </table>
          </div>
        </section>

        <section id="pastas" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">4) Responsabilidade de cada pasta</h2>
          <div class="doc-card doc-rule mt-4">
            <p class="doc-label">Regras</p>
            <p class="mt-2 text-sm text-slate-200">Cada diretorio deve refletir responsabilidade unica. Evitar mistura de camadas.</p>
          </div>
          <div class="mt-4 overflow-x-auto">
            <table class="doc-table">
              <thead>
                <tr>
                  <th>Diretorio</th>
                  <th>Responsabilidade</th>
                  <th>Regra de uso</th>
                </tr>
              </thead>
              <tbody>
                <tr class="doc-row"><td class="text-white">app/Controllers</td><td class="text-slate-300">Entrada HTTP, validacao basica, dispatch e resposta.</td><td class="text-slate-400">Nao conter SQL direto.</td></tr>
                <tr class="doc-row"><td class="text-white">app/Services</td><td class="text-slate-300">Regra de negocio, composicao de repositorios e view model.</td><td class="text-slate-400">Evitar dependencia de camada de view.</td></tr>
                <tr class="doc-row"><td class="text-white">app/Repositories</td><td class="text-slate-300">Acesso ao MySQL (queries, upsert, filtros, agregacoes).</td><td class="text-slate-400">Nao renderizar HTML e nao responder HTTP.</td></tr>
                <tr class="doc-row"><td class="text-white">app/Views</td><td class="text-slate-300">Layouts, paginas e componentes.</td><td class="text-slate-400">Sem regra complexa de negocio.</td></tr>
                <tr class="doc-row"><td class="text-white">app/Support</td><td class="text-slate-300">Infra local (Auth, Csrf, Session, View, Helpers).</td><td class="text-slate-400">Reutilizavel entre modulos.</td></tr>
                <tr class="doc-row"><td class="text-white">config</td><td class="text-slate-300">Rotas e configuracoes de app, banco, backup, content-sync.</td><td class="text-slate-400">Nao incluir segredo fixo versionado.</td></tr>
                <tr class="doc-row"><td class="text-white">public/assets</td><td class="text-slate-300">CSS, JS e recursos estaticos da interface.</td><td class="text-slate-400">Arquivos de marca com controle.</td></tr>
                <tr class="doc-row"><td class="text-white">public/uploads</td><td class="text-slate-300">Midias editoriais e de links/campanhas.</td><td class="text-slate-400">Limpeza apenas por rotina segura.</td></tr>
                <tr class="doc-row"><td class="text-white">scripts</td><td class="text-slate-300">Rotinas de operacao local (backup, content-sync, preflight).</td><td class="text-slate-400">Executar com contexto e confirmacao.</td></tr>
                <tr class="doc-row"><td class="text-white">storage</td><td class="text-slate-300">Artefatos locais: backups, pacotes de conteudo e codigo.</td><td class="text-slate-400">Nao publicar diretamente para web.</td></tr>
              </tbody>
            </table>
          </div>
        </section>

        <section id="regras-dev" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">5) Regras de desenvolvimento</h2>
          <h3 class="mt-4 font-orbitron text-sm font-semibold uppercase tracking-[0.2em] text-slate-300">Regras obrigatorias</h3>
          <div class="doc-card doc-rule mt-3">
            <p class="doc-label">Regras</p>
            <p class="mt-2 text-sm text-slate-200">Estas regras sao obrigatorias para evitar degradacao arquitetural e regressao funcional.</p>
          </div>
          <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-300">
            <li>Controller nao acessa banco diretamente.</li>
            <li>Service concentra regra de negocio e coerencia entre modulos.</li>
            <li>Repository concentra acesso a dados.</li>
            <li>View nao carrega regra de negocio complexa.</li>
            <li>Scripts locais sao para operacao controlada, com confirmacao.</li>
            <li>Pacote de codigo para producao nao pode incluir dados ficticios.</li>
            <li>Toda alteracao funcional deve atualizar esta documentacao.</li>
          </ul>
        </section>

        <section id="seguranca" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">6) Seguranca do sistema</h2>
          <div class="doc-card doc-alert mt-4">
            <p class="doc-label">Alerta critico</p>
            <p class="mt-2 text-sm text-amber-100">Todo deploy deve garantir que rotas e arquivos internos nao fiquem expostos publicamente.</p>
          </div>

          <h3 class="mt-4 font-orbitron text-sm font-semibold uppercase tracking-[0.2em] text-slate-300">Visao geral</h3>
          <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-300">
            <li>Rotas locais (`/local/*` e `/dev`) devem ficar restritas ao ambiente local.</li>
            <li>`.env` nao pode ser servido pelo web server.</li>
            <li>Upload deve validar tipo, mime, extensao e tamanho.</li>
            <li>Sessao de admin e formularios sensiveis devem usar CSRF.</li>
          </ul>

          <h3 class="mt-4 font-orbitron text-sm font-semibold uppercase tracking-[0.2em] text-slate-300">Como funciona</h3>
          <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-300">
            <li>Midia valida extensoes e MIME permitidos (`MidiaService::ALLOWED_MIME_TYPES` e `IMAGE_EXTENSIONS`).</li>
            <li>Autenticacao administrativa usa sessao com regeneracao em login/logout (`Auth`).</li>
            <li>Operacoes POST validam token (`Csrf::validate`).</li>
            <li>Rotas internas sao protegidas por guard local no front controller.</li>
          </ul>

          <h3 class="mt-4 font-orbitron text-sm font-semibold uppercase tracking-[0.2em] text-slate-300">Boas praticas para producao</h3>
          <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-300">
            <li>Configurar docroot para apontar somente para a pasta `public`.</li>
            <li>Negar acesso direto a `.env` e arquivos de configuracao fora de `public`.</li>
            <li>Bloquear execucao de scripts em `public/uploads` no servidor web.</li>
            <li>Manter `APP_DEBUG=false` em producao.</li>
          </ul>
        </section>

        <section id="padrao-codigo" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">7) Padrao de codigo e nomenclatura</h2>
          <div class="doc-card doc-rule mt-4">
            <p class="doc-label">Regras</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
              <li>Classes em PascalCase.</li>
              <li>Metodos em camelCase.</li>
              <li>Controllers finalizam com `Controller`.</li>
              <li>Services finalizam com `Service`.</li>
              <li>Repositories finalizam com `Repository`.</li>
              <li>Variaveis devem ser descritivas (evitar nomes genericos).</li>
              <li>Nome de arquivo deve refletir responsabilidade da classe.</li>
            </ul>
          </div>
        </section>

        <section id="deploy" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">8) Deploy operacional (codigo e conteudo)</h2>

          <h3 class="mt-4 font-orbitron text-sm font-semibold uppercase tracking-[0.2em] text-slate-300">Fluxo oficial</h3>
          <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-300">
            <li>Alteracao local</li>
            <li>Teste local</li>
            <li>Commit</li>
            <li>Gerar pacote</li>
            <li>Publicar em producao</li>
          </ol>

          <div class="doc-card doc-example mt-4">
            <p class="doc-label">Checklist</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-200">
              <li>Executar `php scripts/preflight-check.php`</li>
              <li>Validar `.env` e perfil alvo</li>
              <li>Testar paginas criticas antes de empacotar</li>
              <li>Confirmar pacote sem dados ficticios</li>
            </ul>
          </div>

          <h3 class="mt-4 font-orbitron text-sm font-semibold uppercase tracking-[0.2em] text-slate-300">Checklist pre-deploy</h3>
          <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-300">
            <li>Executar `php scripts/preflight-check.php`</li>
            <li>Validar `.env` (sem trocar credencial acidental)</li>
            <li>Conferir rotas criticas (`/`, `/blog`, `/post/{slug}`, `/admin`, `/central-nerd`)</li>
            <li>Conferir assets e uploads relevantes</li>
            <li>Checar encoding/acentuacao em textos alterados</li>
            <li>Garantir que pacote contem somente arquivos validados</li>
          </ul>

          <h3 class="mt-4 font-orbitron text-sm font-semibold uppercase tracking-[0.2em] text-slate-300">Rollback (falha em producao)</h3>
          <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-300">
            <li>Interromper novas publicacoes</li>
            <li>Restaurar ultimo backup valido (banco/uploads conforme escopo)</li>
            <li>Reaplicar pacote de codigo anterior estavel</li>
            <li>Validar paginas criticas e dashboard</li>
            <li>Registrar causa e correcao na secao de evolucao</li>
          </ol>
        </section>

        <section id="backup" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">9) Backup e restore</h2>
          <div class="doc-card doc-rule mt-4">
            <p class="doc-label">Visao geral</p>
            <p class="mt-2 text-sm text-slate-200">Backup e etapa obrigatoria antes de qualquer deploy ou restore em ambiente sensivel.</p>
          </div>

          <h3 class="mt-4 font-orbitron text-sm font-semibold uppercase tracking-[0.2em] text-slate-300">O que entra no backup</h3>
          <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-300">
            <li>Dump do banco (`database.sql`)</li>
            <li>Pacote de uploads (`uploads.zip`)</li>
            <li>Metadados de integridade (`manifest.json`)</li>
          </ul>

          <h3 class="mt-4 font-orbitron text-sm font-semibold uppercase tracking-[0.2em] text-slate-300">Onde fica</h3>
          <p class="mt-2 text-sm text-slate-300">Diretorio configurado em `BACKUP_ROOT` (ex.: `D:\Taren\Documents\Backup\Estrategia Nerd\AAAA-MM-DD_HH-mm-ss`).</p>

          <h3 class="mt-4 font-orbitron text-sm font-semibold uppercase tracking-[0.2em] text-slate-300">Quando executar</h3>
          <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-300">
            <li>Antes de qualquer deploy para producao</li>
            <li>Antes de restore local/producao</li>
            <li>Antes de mudancas estruturais em links/posts/midia</li>
          </ul>

          <h3 class="mt-4 font-orbitron text-sm font-semibold uppercase tracking-[0.2em] text-slate-300">Local x Producao</h3>
          <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-300">
            <li><strong>Local:</strong> usa banco e uploads da maquina local.</li>
            <li><strong>Producao:</strong> usa credenciais `BACKUP_PRODUCTION_*` para DB remoto e FTP remoto.</li>
          </ul>

          <h3 class="mt-4 font-orbitron text-sm font-semibold uppercase tracking-[0.2em] text-slate-300">Boas praticas antes de restore</h3>
          <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-300">
            <li>Verificar pacote antes (`verify`)</li>
            <li>Confirmar escopo (all, database, uploads)</li>
            <li>Confirmar ambiente alvo com cuidado</li>
            <li>Manter backup imediatamente anterior ao restore</li>
          </ul>
        </section>

        <section id="ambiente" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">10) Configuracoes de ambiente (.env)</h2>
          <div class="doc-card doc-rule mt-4">
            <p class="doc-label">Visao geral</p>
            <p class="mt-2 text-sm text-slate-200">O arquivo `.env` concentra configuracoes criticas de ambiente, conexao e operacao local.</p>
          </div>
          <h3 class="mt-4 font-orbitron text-sm font-semibold uppercase tracking-[0.2em] text-slate-300">Variaveis essenciais</h3>
          <div class="mt-2 overflow-x-auto">
            <table class="doc-table">
              <thead>
                <tr>
                  <th>Variavel</th>
                  <th>Finalidade</th>
                  <th>Obs</th>
                </tr>
              </thead>
              <tbody>
                <tr class="doc-row"><td class="text-white">APP_ENV / APP_DEBUG / APP_URL</td><td class="text-slate-300">Controle de ambiente, debug e URL base.</td><td class="text-slate-400">Producao deve estar com debug desligado.</td></tr>
                <tr class="doc-row"><td class="text-white">DB_HOST / DB_PORT / DB_DATABASE / DB_USERNAME / DB_PASSWORD</td><td class="text-slate-300">Conexao principal do sistema.</td><td class="text-slate-400">Nao versionar segredo real.</td></tr>
                <tr class="doc-row"><td class="text-white">BACKUP_ROOT / BACKUP_RETENTION</td><td class="text-slate-300">Destino e retencao de backups locais.</td><td class="text-slate-400">Revisar espaco em disco.</td></tr>
                <tr class="doc-row"><td class="text-white">BACKUP_PRODUCTION_*</td><td class="text-slate-300">Acesso remoto para backup de producao.</td><td class="text-slate-400">Obrigatorio para backup remoto.</td></tr>
                <tr class="doc-row"><td class="text-white">CONTENT_SYNC_* / CONTENT_SYNC_PRODUCTION_*</td><td class="text-slate-300">Exportacao/aplicacao de conteudo e deploy de codigo.</td><td class="text-slate-400">Separar dados de conteudo e codigo.</td></tr>
              </tbody>
            </table>
          </div>
          <p class="mt-3 text-sm text-slate-300">Seguranca: `.env` nao pode ser commitado com credencial real. Usar `.env.example` como referencia limpa.</p>
        </section>

        <section id="diagnostico" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">11) Erros comuns e diagnostico rapido</h2>
          <div class="doc-card doc-alert mt-4">
            <p class="doc-label">Checklist rapido</p>
            <p class="mt-2 text-sm text-amber-100">Sempre confirmar `.env`, rotas e ultima alteracao publicada antes de abrir intervencao maior.</p>
          </div>
          <div class="mt-4 overflow-x-auto">
            <table class="doc-table">
              <thead>
                <tr>
                  <th>Problema</th>
                  <th>Possivel causa</th>
                  <th>Acao recomendada</th>
                </tr>
              </thead>
              <tbody>
                <tr class="doc-row"><td class="text-white">Imagem nao aparece</td><td class="text-slate-300">Path quebrado ou arquivo fora de uploads.</td><td class="text-slate-400">Revisar referencia no banco e em `public/uploads`.</td></tr>
                <tr class="doc-row"><td class="text-white">Rota nao carrega</td><td class="text-slate-300">Regra em `config/routes.php` ausente/incorreta.</td><td class="text-slate-400">Validar rota e cache de opcache local.</td></tr>
                <tr class="doc-row"><td class="text-white">Conteudo nao sincroniza</td><td class="text-slate-300">Package invalido ou credencial remota.</td><td class="text-slate-400">Executar `verify`, revisar `.env` e lock de rotina.</td></tr>
                <tr class="doc-row"><td class="text-white">Pacote falha na validacao</td><td class="text-slate-300">JSON inconsistente ou upload ausente.</td><td class="text-slate-400">Regenerar pacote e revisar referencias de midia.</td></tr>
                <tr class="doc-row"><td class="text-white">Erro de acentuacao</td><td class="text-slate-300">Arquivo salvo com encoding errado.</td><td class="text-slate-400">Rodar preflight e revisar arquivo com UTF-8 consistente.</td></tr>
              </tbody>
            </table>
          </div>
        </section>

        <section id="evolucao" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">12) Evolucao/historico tecnico</h2>
          <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-slate-300">
            <li>Base inicial do portal editorial e blog.</li>
            <li>Criacao da Central Nerd como pagina de conversao.</li>
            <li>Consolidacao do admin para conteudo, links e midia.</li>
            <li>Adicao da rotina local de backup com verify/restore.</li>
            <li>Implementacao da rotina de sync de conteudo e deploy de codigo.</li>
            <li>Melhorias de validacao de midia orfa e seguranca de caminho.</li>
            <li>Padronizacao do fluxo: alteracao -> teste -> commit -> pacote -> producao.</li>
            <li>Criacao da central de documentacao local com menu unificado.</li>
          </ol>
        </section>

        <section id="padrao-secoes" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">13) Padrao de formato por secao</h2>
          <p class="mt-2 text-sm text-slate-300">Para manter leitura previsivel e reutilizavel, usar este molde sempre que fizer sentido:</p>
          <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm text-slate-300">
            <li>Visao geral</li>
            <li>Como funciona</li>
            <li>Regras</li>
            <li>Fluxo</li>
            <li>Exemplos</li>
            <li>Checklist</li>
          </ol>
        </section>

        <section id="governanca" class="rounded-3xl border border-fuchsia-500/20 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-fuchsia-200">14) Governanca da documentacao</h2>
          <p class="mt-2 text-sm leading-7 text-slate-300">
            Esta documentacao deve ser mantida no mesmo ciclo das alteracoes do sistema.
            O objetivo e evitar perda de contexto e reduzir retrabalho em manutencao futura.
          </p>
          <h3 class="mt-4 font-orbitron text-sm font-semibold uppercase tracking-[0.2em] text-fuchsia-100">Checklist de manutencao da doc</h3>
          <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-fuchsia-50">
            <li>Atualizar secao impactada quando houver mudanca funcional.</li>
            <li>Registrar mudanca relevante na evolucao tecnica.</li>
            <li>Conferir links internos de navegacao da documentacao.</li>
            <li>Gerar pacote somente depois da revisao da documentacao.</li>
            <li>Documentacao e exclusiva do ambiente local (nao publicar em producao).</li>
            <li>Backlog e trabalhado no local; so vira alteracao em producao apos validacao completa.</li>
          </ul>
        </section>
      </div>
    </article>
  </div>
</section>
