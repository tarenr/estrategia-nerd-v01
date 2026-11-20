<?php

declare(strict_types=1);

$embedMode = (bool) ($embed_mode ?? false);
$adminEmbed = (bool) ($admin_embed ?? false);
$projectVersion = trim((string) ($project_version ?? 'local'));
$generatedAt = trim((string) ($generated_at ?? date('Y-m-d H:i:s')));
$projectName = (string) config('app.name', 'Estratégia Nerd');
$projectType = 'Portal editorial geek + conversão de afiliados + automação de mídia';
$projectStack = 'PHP 8.2 (MVC leve customizado), MySQL/MariaDB, Tailwind CSS local, FontAwesome 6, Node.js + Chrome (Motionmaxxing), Playwright (OneTake), WebGPU/Dawn (Framefields)';
$projectEnvironment = 'Local / homologação operacional';
$projectOwner = (string) ($_ENV['PROJECT_OWNER'] ?? 'Taren');
$projectScope = 'Documentação interna, backup sistêmico/editorial, validação local, stage e governança de releases';
$currentSource = strtolower(trim((string) ($_ENV['CONTENT_SYNC_CURRENT_SOURCE'] ?? 'local')));
$approvedSource = strtolower(trim((string) ($_ENV['CONTENT_SYNC_APPROVED_PACKAGE_SOURCE'] ?? 'stage')));
$stageLabel = trim((string) ($_ENV['CONTENT_SYNC_STAGE_LABEL'] ?? 'estrategia-nerd-stage'));
$productionAllowed = $currentSource !== '' && $approvedSource !== '' && $currentSource === $approvedSource;

$docGroups = [
    'Fundamentos & Arquitetura' => [
        'identidade' => ['label' => '1. Identidade do projeto', 'icon' => 'fa-solid fa-cube'],
        'arquitetura' => ['label' => '2. Arquitetura técnica (MVC)', 'icon' => 'fa-solid fa-sitemap'],
        'rotas-principais' => ['label' => '3. Rotas do sistema', 'icon' => 'fa-solid fa-route'],
        'pastas' => ['label' => '4. Responsabilidade de pastas', 'icon' => 'fa-solid fa-folder-tree'],
        'padrao-codigo' => ['label' => '5. Padrão de código e nomenclatura', 'icon' => 'fa-solid fa-code'],
    ],
    'Operação & Infraestrutura' => [
        'deploy' => ['label' => '6. Deploy operacional e paridade', 'icon' => 'fa-solid fa-rocket'],
        'backup' => ['label' => '7. Backup e restore', 'icon' => 'fa-solid fa-database'],
        'dropbox' => ['label' => '8. Backup em nuvem (Google Drive)', 'icon' => 'fa-brands fa-google-drive'],
        'ambiente' => ['label' => '9. Configurações de ambiente (.env)', 'icon' => 'fa-solid fa-sliders'],
        'seguranca' => ['label' => '10. Segurança do sistema', 'icon' => 'fa-solid fa-shield-halved'],
    ],
    'Módulos & Negócio' => [
        'instagram' => ['label' => '11. Instagram & Reels (Motores)', 'icon' => 'fa-brands fa-instagram'],
        'central-nerd' => ['label' => '12. Central Nerd & Afiliados', 'icon' => 'fa-solid fa-cart-shopping'],
        'regras-dev' => ['label' => '13. Regras de desenvolvimento', 'icon' => 'fa-solid fa-gavel'],
    ],
    'Governança & Diagnóstico' => [
        'governanca-releases' => ['label' => '14. Governança oficial de releases', 'icon' => 'fa-solid fa-clipboard-check'],
        'diagnostico' => ['label' => '15. Erros comuns e diagnóstico', 'icon' => 'fa-solid fa-triangle-exclamation'],
        'evolucao' => ['label' => '16. Evolução técnica do portal', 'icon' => 'fa-solid fa-timeline'],
        'governanca' => ['label' => '17. Governança da documentação', 'icon' => 'fa-solid fa-book-bookmark'],
    ],
];

// Lista ordenada de chaves para navegação sequencial
$allSectionKeys = [];
$docSections = [];
foreach ($docGroups as $groupItems) {
    foreach ($groupItems as $secKey => $secData) {
        $allSectionKeys[] = $secKey;
        $docSections[$secKey] = $secData['label'];
    }
}

$docSection = strtolower(trim((string) ($_GET['docs_secao'] ?? 'identidade')));
if (!array_key_exists($docSection, $docSections)) {
    $docSection = 'identidade';
}

$currentIndex = array_search($docSection, $allSectionKeys, true);
if ($currentIndex === false) {
    $currentIndex = 0;
}
$prevSection = $currentIndex > 0 ? $allSectionKeys[$currentIndex - 1] : null;
$nextSection = $currentIndex < count($allSectionKeys) - 1 ? $allSectionKeys[$currentIndex + 1] : null;

$docsBaseUrl = $adminEmbed ? url('/admin/central-tecnica/base-conhecimento/documentacao') : url('/local/documentacao');
$docsSectionUrl = static function (string $section) use ($docsBaseUrl): string {
    $separator = str_contains($docsBaseUrl, '?') ? '&' : '?';
    return $docsBaseUrl . $separator . 'docs_secao=' . rawurlencode($section);
};
$technicalPriorityDocUrl = url('/local/mudancas/documento?grupo=governanca&arquivo=priorizacao-tecnica.md&redirect_to=' . rawurlencode($docsSectionUrl('governanca-releases')));
$echoSeriesDocUrl = url('/local/mudancas/documento?grupo=governanca&arquivo=molde-serie-echo.md&redirect_to=' . rawurlencode($docsSectionUrl('instagram')));
$videoDirectionDocUrl = url('/local/mudancas/documento?grupo=governanca&arquivo=guia-direcao-audiovisual-ia.md&redirect_to=' . rawurlencode($docsSectionUrl('instagram')));
?>
<section class="<?= $adminEmbed ? 'text-slate-100' : 'min-h-screen bg-slate-950 px-4 py-8 text-slate-100' ?>">
  <style>
    .doc-callout { border-radius: 1rem; padding: 1.15rem 1.25rem; font-size: 0.875rem; line-height: 1.55rem; }
    .doc-callout-info { border: 1px solid rgba(6, 182, 212, 0.35); background: rgba(8, 47, 73, 0.35); color: #bae6fd; }
    .doc-callout-warn { border: 1px solid rgba(245, 158, 11, 0.35); background: rgba(120, 53, 15, 0.3); color: #fde68a; }
    .doc-callout-danger { border: 1px solid rgba(244, 63, 94, 0.35); background: rgba(136, 19, 55, 0.3); color: #fecdd3; }
    .doc-callout-success { border: 1px solid rgba(16, 185, 129, 0.35); background: rgba(6, 78, 59, 0.3); color: #a7f3d0; }
    .doc-step-badge { display: inline-flex; align-items: center; justify-content: center; width: 1.75rem; height: 1.75rem; border-radius: 0.55rem; font-family: monospace; font-weight: 700; font-size: 0.8rem; flex-shrink: 0; }
    .doc-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.85rem; }
    .doc-table th { text-align: left; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.16em; color: #94a3b8; padding: 0.75rem 1rem; border-bottom: 1px solid rgba(51, 65, 85, 0.7); background: rgba(2, 6, 23, 0.6); }
    .doc-table td { padding: 0.85rem 1rem; border-bottom: 1px solid rgba(30, 41, 59, 0.6); vertical-align: top; }
    .doc-table tr:hover td { background: rgba(30, 41, 59, 0.35); }
    .doc-panel[hidden] { display: none; }
  </style>

  <div class="<?= $adminEmbed ? 'space-y-4' : 'mx-auto max-w-7xl space-y-6' ?>">
    <?php if (!$embedMode && !$adminEmbed): ?>
      <?php \App\Support\View::component('site/local-tools-nav', ['active' => 'docs']); ?>
      <header class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_35px_rgba(6,182,212,0.06)]">
        <p class="font-orbitron text-xs uppercase tracking-[0.3em] text-cyan-300/70">Documentação Central Interna</p>
        <h1 class="mt-2 font-orbitron text-2xl font-black tracking-tight text-white md:text-3xl text-balance">Base Técnica Operacional — <?= htmlspecialchars($projectName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
        <p class="mt-2.5 max-w-4xl text-sm leading-relaxed text-slate-300">Referência técnica oficial para desenvolvimento, deploy, paridade de ambientes, backups em nuvem e motores visuais.</p>
      </header>
    <?php endif; ?>

    <!-- Seletor Compacto no Mobile / Telas Pequenas (ocupa apenas 46px no topo, sem empilhar botões!) -->
    <div class="lg:hidden rounded-2xl border border-slate-800 bg-slate-900/90 p-3 shadow-sm">
      <div class="flex items-center gap-2.5">
        <label for="docSectionSelect" class="font-orbitron text-[10px] font-black uppercase tracking-wider text-slate-400 shrink-0">Seção Ativa:</label>
        <select id="docSectionSelect" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-xs font-semibold text-white outline-none focus:border-cyan-400">
          <?php foreach ($docGroups as $groupTitle => $groupItems): ?>
            <optgroup label="<?= htmlspecialchars($groupTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
              <?php foreach ($groupItems as $secKey => $secData): ?>
                <option value="<?= htmlspecialchars($secKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" <?= $secKey === $docSection ? 'selected' : '' ?>><?= htmlspecialchars($secData['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Layout Dividido Lado a Lado no Desktop (Sidebar Sticky de 280px à Esquerda + Conteúdo Imediato à Direita) -->
    <div class="flex flex-col lg:flex-row gap-6 items-start">

      <!-- Barra Lateral Desktop (Sticky e Compacta) -->
      <aside class="hidden lg:block w-72 shrink-0 sticky top-4 rounded-3xl border border-slate-800/90 bg-slate-900/80 p-5 shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <h2 class="font-orbitron text-xs font-black uppercase tracking-[0.2em] text-slate-400">Sumário da Base</h2>
          <span class="rounded-full border border-cyan-500/30 bg-cyan-950/60 px-2 py-0.5 font-mono text-[10px] font-bold text-cyan-300">17 seções</span>
        </div>

        <nav class="mt-4 space-y-4 text-sm" data-doc-nav>
          <?php foreach ($docGroups as $groupTitle => $groupItems): ?>
            <div>
              <p class="font-orbitron text-[9px] font-black uppercase tracking-[0.18em] text-slate-500"><?= htmlspecialchars($groupTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
              <div class="mt-1.5 grid gap-1">
                <?php foreach ($groupItems as $sectionKey => $sectionData): ?>
                  <?php $isActive = $sectionKey === $docSection; ?>
                  <a
                    href="<?= htmlspecialchars($docsSectionUrl($sectionKey), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                    data-doc-trigger="<?= htmlspecialchars($sectionKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                    class="flex items-center gap-2.5 rounded-xl border px-3 py-1.5 text-xs font-medium transition <?= $isActive ? 'border-cyan-400/50 bg-cyan-500/15 text-cyan-100 shadow-[0_0_18px_rgba(34,211,238,0.12)]' : 'border-slate-800/70 bg-slate-950/50 text-slate-300 hover:border-slate-700 hover:bg-slate-800/50 hover:text-white' ?>"
                    aria-current="<?= $isActive ? 'page' : 'false' ?>"
                  >
                    <i class="<?= htmlspecialchars($sectionData['icon'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> text-[10px] <?= $isActive ? 'text-cyan-300' : 'text-slate-500' ?>" aria-hidden="true"></i>
                    <span class="truncate"><?= htmlspecialchars($sectionData['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </nav>
      </aside>

      <!-- Área de Conteúdo das Seções (Começa Imediatamente no Topo da Tela!) -->
      <div class="flex-1 min-w-0 space-y-6">

        <!-- 1. IDENTIDADE DO PROJETO -->
        <section id="identidade" data-doc-panel="identidade" class="doc-panel rounded-3xl border border-slate-800/90 bg-slate-900/85 p-6 shadow-sm" <?= $docSection === 'identidade' ? '' : 'hidden' ?>>
          <div class="flex items-center gap-2.5 border-b border-slate-800/80 pb-4">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl border border-cyan-500/30 bg-cyan-500/10 text-cyan-300"><i class="fa-solid fa-cube text-xs"></i></span>
            <div>
              <h2 class="font-orbitron text-xl font-bold text-white text-balance">1) Identidade do Projeto</h2>
              <p class="text-xs text-slate-400">Diretrizes gerais, stack tecnológica e governança de escopo.</p>
            </div>
          </div>

          <div class="doc-callout doc-callout-info mt-4">
            <p class="font-semibold text-cyan-100">Visão Geral & Filosofia Técnica</p>
            <p class="mt-1">Esta documentação interna centraliza as diretrizes operacionais de código, deploys controlados, testes de integridade, paridade de ambientes e rotinas do portal Estratégia Nerd.</p>
          </div>

          <div class="mt-5 overflow-hidden rounded-2xl border border-slate-800 bg-slate-950/70">
            <table class="doc-table">
              <tbody>
                <tr><td class="w-1/3 font-semibold text-slate-400">Nome Oficial</td><td class="font-bold text-white"><?= htmlspecialchars($projectName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
                <tr><td class="font-semibold text-slate-400">Natureza</td><td class="text-slate-200"><?= htmlspecialchars($projectType, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
                <tr><td class="font-semibold text-slate-400">Stack Tecnológica</td><td class="text-slate-200"><?= htmlspecialchars($projectStack, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
                <tr><td class="font-semibold text-slate-400">Ambientes</td><td class="text-slate-200"><span class="rounded bg-slate-800 px-2 py-0.5 text-xs font-mono text-cyan-300">Local</span> &rarr; <span class="rounded bg-slate-800 px-2 py-0.5 text-xs font-mono text-amber-300"><?= htmlspecialchars($stageLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span> &rarr; <span class="rounded bg-slate-800 px-2 py-0.5 text-xs font-mono text-emerald-300">Produção</span></td></tr>
                <tr><td class="font-semibold text-slate-400">Responsável</td><td class="text-white"><?= htmlspecialchars($projectOwner, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
                <tr><td class="font-semibold text-slate-400">Versão / Tag</td><td class="font-mono text-xs text-slate-300"><?= htmlspecialchars($projectVersion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
                <tr><td class="font-semibold text-slate-400">Última Leitura</td><td class="text-slate-300"><?= htmlspecialchars($generatedAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
                <tr><td class="font-semibold text-slate-400">Escopo Governança</td><td class="text-slate-300"><?= htmlspecialchars($projectScope, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- 2. ARQUITETURA TÉCNICA -->
        <section id="arquitetura" data-doc-panel="arquitetura" class="doc-panel rounded-3xl border border-slate-800/90 bg-slate-900/85 p-6 shadow-sm" <?= $docSection === 'arquitetura' ? '' : 'hidden' ?>>
          <div class="flex items-center gap-2.5 border-b border-slate-800/80 pb-4">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl border border-cyan-500/30 bg-cyan-500/10 text-cyan-300"><i class="fa-solid fa-sitemap text-xs"></i></span>
            <div>
              <h2 class="font-orbitron text-xl font-bold text-white text-balance">2) Arquitetura Técnica (MVC)</h2>
              <p class="text-xs text-slate-400">Separação rigorosa de responsabilidades e orquestração de camadas.</p>
            </div>
          </div>

          <div class="mt-4 rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <p class="font-orbitron text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Fluxo Canônico da Requisição</p>
            <pre class="mt-2 overflow-x-auto text-xs leading-relaxed text-cyan-300 font-mono"><code>Request &rarr; Router &rarr; Controller &rarr; Service &rarr; Repository &rarr; Database (PDO)
                                       &rarr; ViewModel &rarr; View (HTML) &rarr; Response</code></pre>
          </div>

          <div class="mt-5 grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-800/80 bg-slate-950/60 p-4">
              <h3 class="font-semibold text-white flex items-center gap-2"><i class="fa-solid fa-layer-group text-xs text-cyan-400"></i> Orquestração (Controllers & Services)</h3>
              <ul class="mt-2 space-y-1.5 text-xs text-slate-300">
                <li>&bull; <strong class="text-white">Controller:</strong> Apenas valida requisições HTTP, despacha para Services e devolve Views/JSON. <em>Proibido executar SQL direto.</em></li>
                <li>&bull; <strong class="text-white">Service:</strong> Concentra regras de negócio, integrações, validação cruzada e geração de ViewModels.</li>
              </ul>
            </div>
            <div class="rounded-2xl border border-slate-800/80 bg-slate-950/60 p-4">
              <h3 class="font-semibold text-white flex items-center gap-2"><i class="fa-solid fa-database text-xs text-cyan-400"></i> Persistência & Exibição</h3>
              <ul class="mt-2 space-y-1.5 text-xs text-slate-300">
                <li>&bull; <strong class="text-white">Repository:</strong> Centraliza queries SQL seguras (`prepare`/`execute`), paginação e mapeamento.</li>
                <li>&bull; <strong class="text-white">ViewModel & View:</strong> ViewModels preparam os dados no Service. Views contêm apenas renderização visual, sem queries complexas.</li>
              </ul>
            </div>
            <div class="rounded-2xl border border-slate-800/80 bg-slate-950/60 p-4 md:col-span-2">
              <h3 class="font-semibold text-white flex items-center gap-2"><i class="fa-solid fa-wave-square text-xs text-cyan-400"></i> Homepage Circuito Contínuo (FEAT-015)</h3>
              <ul class="mt-2 space-y-1.5 text-xs text-slate-300">
                <li>&bull; A home <code>/</code> usa <code>site/home-circuito-preview</code> com o SEO oficial; a home anterior continua em <code>site/home</code> para rollback. A rota <code>/preview/circuito-continuo</code> continua com noindex.</li>
                <li>&bull; Dois canvases WebGL2 fixos: fundo ambiente e o feixe principal, um "rio" de luz que nasce no Hero, serpenteia por trás do conteúdo (desviando de botões e campos) e sai pela base da página.</li>
                <li>&bull; A fantasia do Echo é escolhida em <code>/admin/echo</code> por ambiente (chave <code>echo_fantasia</code> em <code>configuracoes</code>; lista em <code>config/echo-fantasias.php</code>; FEAT-016).</li>
                <li>&bull; As camadas compartilham o ciclo de animação, pausam com a aba oculta e degradam para o fundo estático quando WebGL2 não está disponível.</li>
                <li>&bull; A trajetória completa é preparada antes do primeiro quadro e reutilizada por uma assinatura da geometria da página. Portal, Echo, Hero e feixe começam no mesmo ciclo, sem substituir um caminho provisório após a abertura.</li>
                <li>&bull; Embora a geometria já esteja pronta, o traçado visual nasce na saída do Hero e avança pela ponta até a rolagem atual. Assim, inclusive após uma recarga no meio ou no rodapé, o feixe percorre a tela em vez de surgir inteiro.</li>
                <li>&bull; Depois da chegada, o Echo acompanha a seção ativa pelo corredor lateral com movimento amortecido, sem atravessar o conteúdo. Ele reduz de tamanho no celular e se oculta suavemente quando o menu móvel está aberto ou um campo está em edição.</li>
                <li>&bull; As demais páginas públicas (blog e categorias, posts, post indisponível, Central Nerd, política, termos e 404) recebem o fundo ambiente compartilhado: o componente <code>site/shared/ambient-background</code> coloca um canvas WebGL2 (<code>.en-flow-bg</code>) antes do <code>&lt;main&gt;</code> e o <code>site-home-flow.js</code> desenha só a camada de fundo, sem rio, portal ou Echo. Como na Home, a grade <code>.circuit-bg</code> e a <code>.scanline</code> continuam; seções, rodapé e newsletter ficam sem fundo e sem borda de largura total (regras <code>.en-flow-bg ~ main</code> em <code>site.css</code>), e cards, painéis e formulários mantêm os fundos. Na Home o canvas fica dentro do <code>&lt;main&gt;</code> e as regras são as do <code>site-home-flow.css</code>.</li>
                <li>&bull; Echo da tarefa #1666 (local e stage em 26/10/2026; a produção segue com a versão anterior; correção Forge #1693 a #1695, detalhes no FEAT-015): no Hero, se o tamanho-base não cabe ao lado do título (768 a ~1150 px), o portal usa um tamanho menor (13% da largura, 120 ou 104 px); no celular ele continua aparecendo depois do Hero. Acompanhando a página, toda troca de lugar segue a sequência <code>moving</code> &rarr; <code>fading-out</code> (parado) &rarr; <code>repositioning</code> (invisível) &rarr; <code>fading-in</code>, exposta em <code>data-echo-state</code> no canvas; o fade é a transição do CSS (300 ms, ou 120 ms em <code>.is-fading-fast</code> quando o conteúdo chega antes), a antecipação é a distância rolada nos últimos 340 ms e, com movimento reduzido, a troca é um corte sem deslizar. Pendente (27/10/2026, Forge #1697): o dono viu o Echo "teletransportar" na stage e no local, porque o lugar novo costuma ser o outro lado da tela (troca por seção e a cada 7 s parado); a produção, que voa de um lado para o outro sem sumir, é a referência. A correção proposta (voltar ao movimento da produção mantendo o portal em tablet, o celular depois do Hero e o conteúdo por cima do Echo) está no FEAT-015, seção "Pendente: Echo teletransporta".</li>
                <li>&bull; Reconciliação de 26/10/2026 (FEAT-015): local e Git iguais ao publicado, motor Canvas 2D removido, emendas das páginas públicas retiradas na stage e rollback das 3 alterações de produção de 25/10 23:43 preparado e validado em sandbox, sem execução, em <code>scripts/home-reconciliacao-deploy.php</code> (<code>producao --check</code> só lê; o <code>--rollback</code> exige <code>--confirmar</code>). Em 27/10/2026 as emendas também saíram da produção, por <code>scripts/home-bordas-production-deploy.php</code>: as 6 views vieram da stage e o <code>site.css</code> da produção recebeu só os 3 trechos das bordas (sem FIX-009 nem o cabeçalho 768–1023 px). Cópias para o rollback (<code>--rollback --confirmar</code>) em <code>storage/home-bordas-deploy/production</code>; backup completo BS-PROD-20261027-103630.</li>
                <li>&bull; O botão do menu móvel é controlado somente pelo CSS externo: oculto por padrão e visível apenas até 767 px, sem estilos inline ou JavaScript de visibilidade.</li>
                <li>&bull; Se o navegador perder um contexto WebGL, a página agenda uma única recarga para reconstruir programas e buffers das duas camadas; uma janela de 30 segundos impede recargas em ciclo quando a GPU permanece indisponível.</li>
              </ul>
            </div>
          </div>
        </section>

        <!-- 3. ROTAS DO SISTEMA -->
        <section id="rotas-principais" data-doc-panel="rotas-principais" class="doc-panel rounded-3xl border border-slate-800/90 bg-slate-900/85 p-6 shadow-sm" <?= $docSection === 'rotas-principais' ? '' : 'hidden' ?>>
          <div class="flex items-center gap-2.5 border-b border-slate-800/80 pb-4">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl border border-cyan-500/30 bg-cyan-500/10 text-cyan-300"><i class="fa-solid fa-route text-xs"></i></span>
            <div>
              <h2 class="font-orbitron text-xl font-bold text-white text-balance">3) Rotas do Sistema</h2>
              <p class="text-xs text-slate-400">Tabela de rotas gerada dinamicamente a partir de <code>config/routes.php</code>.</p>
            </div>
          </div>

          <?php
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
                  $groupName = in_array($second, $known, true) ? 'Admin: /admin/' . $second : 'Admin: posts, categorias, comentários e demais';
              } elseif ($first === 'local') {
                  $groupName = 'Local: /local (somente neste PC via EnvironmentGuard)';
              } elseif ($first === 'api') {
                  $groupName = 'API: /api';
              } else {
                  $groupName = 'Público (Portal do Leitor)';
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

          <p class="mt-4 text-xs text-slate-300">Total de <strong class="text-white"><?= (int) $routeTotal ?></strong> rotas registradas. O acesso <code class="font-mono text-cyan-300">auth</code> exige login autenticado; <code class="font-mono text-amber-300">capability:*</code> exige perfil específico; <code class="font-mono text-slate-400">livre</code> é público (sendo que rotas <code>/local/*</code> são bloqueadas fora de localhost/LAN).</p>

          <div class="mt-4 space-y-3">
            <?php foreach ($routeGroups as $groupName => $groupRoutes): ?>
              <details class="group rounded-2xl border border-slate-800 bg-slate-950/60 p-4 transition open:border-cyan-500/30">
                <summary class="flex cursor-pointer items-center justify-between text-xs font-semibold text-white">
                  <span><?= htmlspecialchars($groupName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                  <span class="rounded-full bg-slate-800 px-2 py-0.5 text-[10px] font-mono text-slate-400"><?= count($groupRoutes) ?> rotas</span>
                </summary>
                <div class="mt-3 overflow-x-auto">
                  <table class="doc-table">
                    <thead>
                      <tr><th>Método</th><th>Rota</th><th>Handler (Controller::Ação)</th><th>Acesso</th></tr>
                    </thead>
                    <tbody>
                      <?php foreach ($groupRoutes as [$routeMethod, $routePathLabel, $routeHandler, $routeAccess]): ?>
                        <tr>
                          <td><span class="rounded px-2 py-0.5 font-mono text-[10px] font-bold <?= $routeMethod === 'POST' ? 'bg-cyan-950 text-cyan-300 border border-cyan-800' : 'bg-slate-800 text-slate-300' ?>"><?= htmlspecialchars($routeMethod, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></td>
                          <td class="font-mono text-xs text-white"><?= htmlspecialchars($routePathLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                          <td class="text-xs text-slate-300"><?= htmlspecialchars($routeHandler, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                          <td class="text-[11px] text-slate-400"><?= htmlspecialchars($routeAccess, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </details>
            <?php endforeach; ?>
          </div>
        </section>

        <!-- 4. RESPONSABILIDADE DE PASTAS -->
        <section id="pastas" data-doc-panel="pastas" class="doc-panel rounded-3xl border border-slate-800/90 bg-slate-900/85 p-6 shadow-sm" <?= $docSection === 'pastas' ? '' : 'hidden' ?>>
          <div class="flex items-center gap-2.5 border-b border-slate-800/80 pb-4">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl border border-cyan-500/30 bg-cyan-500/10 text-cyan-300"><i class="fa-solid fa-folder-tree text-xs"></i></span>
            <div>
              <h2 class="font-orbitron text-xl font-bold text-white text-balance">4) Responsabilidade de Pastas</h2>
              <p class="text-xs text-slate-400">Organização e regras de governança dos diretórios do repositório.</p>
            </div>
          </div>

          <div class="mt-4 overflow-hidden rounded-2xl border border-slate-800 bg-slate-950/70">
            <table class="doc-table">
              <thead>
                <tr><th>Diretório</th><th>Responsabilidade Primária</th><th>Regra Estrita de Uso</th></tr>
              </thead>
              <tbody>
                <tr><td class="font-mono text-xs text-white">app/Controllers</td><td class="text-slate-300">Entrada HTTP, validação básica de payload, dispatch e resposta.</td><td class="text-xs text-rose-300">Proibido conter SQL direto ou chamadas a banco.</td></tr>
                <tr><td class="font-mono text-xs text-white">app/Services</td><td class="text-slate-300">Regras de negócio, cálculos, composição de repositórios e ViewModels.</td><td class="text-xs text-slate-400">Evitar acoplamento com classes de request/response.</td></tr>
                <tr><td class="font-mono text-xs text-white">app/Repositories</td><td class="text-slate-300">Centralização de consultas PDO ao MySQL.</td><td class="text-xs text-rose-300">Nunca renderizar HTML nem enviar headers HTTP.</td></tr>
                <tr><td class="font-mono text-xs text-white">app/Views</td><td class="text-slate-300">Templates Blade-like, páginas e componentes visuais.</td><td class="text-xs text-slate-400">Proibido lógica de negócio pesada ou queries.</td></tr>
                <tr><td class="font-mono text-xs text-white">config</td><td class="text-slate-300">Rotas e configurações de app, banco, backup e content-sync.</td><td class="text-xs text-rose-300">Nunca versionar senhas ou segredos reais.</td></tr>
                <tr><td class="font-mono text-xs text-white">public/assets</td><td class="text-slate-300">CSS estático compilado, JS e ícones.</td><td class="text-xs text-slate-400">Alterações exigem validação de cache de navegadores.</td></tr>
                <tr><td class="font-mono text-xs text-white">public/uploads</td><td class="text-slate-300">Mídias editoriais, capas WebP e arquivos de Reels.</td><td class="text-xs text-slate-400">PHP bloqueado via <code>.htaccess</code>. Limpeza segura.</td></tr>
                <tr><td class="font-mono text-xs text-white">scripts</td><td class="text-slate-300">Rotinas operacionais CLI, crons e testes automatizados.</td><td class="text-xs text-slate-400">Executar sempre via PHP oficial (`C:\xampp\php\php.exe`).</td></tr>
                <tr><td class="font-mono text-xs text-white">storage</td><td class="text-slate-300">Artefatos locais, caches, logs e manifestos temporários.</td><td class="text-xs text-rose-300">Nunca expor diretamente ao servidor web.</td></tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- 5. PADRÃO DE CÓDIGO E NOMENCLATURA -->
        <section id="padrao-codigo" data-doc-panel="padrao-codigo" class="doc-panel rounded-3xl border border-slate-800/90 bg-slate-900/85 p-6 shadow-sm" <?= $docSection === 'padrao-codigo' ? '' : 'hidden' ?>>
          <div class="flex items-center gap-2.5 border-b border-slate-800/80 pb-4">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl border border-cyan-500/30 bg-cyan-500/10 text-cyan-300"><i class="fa-solid fa-code text-xs"></i></span>
            <div>
              <h2 class="font-orbitron text-xl font-bold text-white text-balance">5) Padrão de Código e Nomenclatura</h2>
              <p class="text-xs text-slate-400">Convenções inegociáveis para legibilidade e análise estática PHPStan nível 5.</p>
            </div>
          </div>

          <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <h3 class="font-semibold text-white text-xs uppercase tracking-wider">Convenções de Arquivo & Classe</h3>
              <ul class="mt-3 space-y-2 text-xs text-slate-300">
                <li class="flex items-start gap-2"><i class="fa-solid fa-check text-cyan-400 mt-0.5"></i> <span><strong>Classes:</strong> Formato <code class="font-mono text-cyan-300">PascalCase</code>.</span></li>
                <li class="flex items-start gap-2"><i class="fa-solid fa-check text-cyan-400 mt-0.5"></i> <span><strong>Métodos:</strong> Formato <code class="font-mono text-cyan-300">camelCase</code>.</span></li>
                <li class="flex items-start gap-2"><i class="fa-solid fa-check text-cyan-400 mt-0.5"></i> <span><strong>Sufixos Obrigatórios:</strong> <code class="font-mono text-slate-200">Controller</code>, <code class="font-mono text-slate-200">Service</code>, <code class="font-mono text-slate-200">Repository</code>.</span></li>
                <li class="flex items-start gap-2"><i class="fa-solid fa-check text-cyan-400 mt-0.5"></i> <span><strong>Subclasses especializadas:</strong> Usam sufixos descritivos (<code class="font-mono text-slate-200">Renderer</code>, <code class="font-mono text-slate-200">Publisher</code>, <code class="font-mono text-slate-200">Reconciler</code>).</span></li>
              </ul>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <h3 class="font-semibold text-white text-xs uppercase tracking-wider">Regras de Implementação</h3>
              <ul class="mt-3 space-y-2 text-xs text-slate-300">
                <li class="flex items-start gap-2"><i class="fa-solid fa-check text-cyan-400 mt-0.5"></i> <span><strong>Declaração Estrita:</strong> Todo arquivo PHP inicia com <code class="font-mono text-cyan-300">&lt;?php declare(strict_types=1);</code>.</span></li>
                <li class="flex items-start gap-2"><i class="fa-solid fa-check text-cyan-400 mt-0.5"></i> <span><strong>Isolamento de SQL:</strong> Código novo sempre concentra SQL no Repository. Ao alterar service legado que faz query direta, migrar o SQL para o repository.</span></li>
                <li class="flex items-start gap-2"><i class="fa-solid fa-check text-cyan-400 mt-0.5"></i> <span><strong>PHPStan Level 5:</strong> Tipagem completa de parâmetros, retornos e PHPDoc em arrays multidimensionais.</span></li>
              </ul>
            </div>
          </div>
        </section>

        <!-- 6. DEPLOY OPERACIONAL E PARIDADE -->
        <section id="deploy" data-doc-panel="deploy" class="doc-panel rounded-3xl border border-slate-800/90 bg-slate-900/85 p-6 shadow-sm" <?= $docSection === 'deploy' ? '' : 'hidden' ?>>
          <div class="flex items-center gap-2.5 border-b border-slate-800/80 pb-4">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl border border-cyan-500/30 bg-cyan-500/10 text-cyan-300"><i class="fa-solid fa-rocket text-xs"></i></span>
            <div>
              <h2 class="font-orbitron text-xl font-bold text-white text-balance">6) Deploy Operacional e Paridade</h2>
              <p class="text-xs text-slate-400">Fluxo controlado de promoção, travas atômicas e otimização de pacotes.</p>
            </div>
          </div>

          <div class="doc-callout <?= $productionAllowed ? 'doc-callout-info' : 'doc-callout-warn' ?> mt-4">
            <p class="font-semibold">Política Atual de Deploy</p>
            <p class="mt-1">Origem atual: <strong class="font-mono text-white"><?= htmlspecialchars($currentSource, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong> &bull; Origem aprovada para pacotes de produção: <strong class="font-mono text-white"><?= htmlspecialchars($approvedSource, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong> (<?= htmlspecialchars($stageLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>).</p>
            <p class="mt-1 text-xs"><?= $productionAllowed ? 'Este ambiente possui autorização para gerar/publicar pacote de produção.' : 'Trava ativa: este ambiente NÃO pode publicar diretamente em produção. O fluxo exige promoção prévia para Stage.' ?></p>
          </div>

          <div class="mt-5 rounded-2xl border border-slate-800 bg-slate-950/70 p-5">
            <h3 class="font-orbitron text-xs font-black uppercase tracking-wider text-slate-300">Fluxo Canônico de Publicação (9 Etapas)</h3>
            <div class="mt-4 grid gap-3 md:grid-cols-3">
              <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-3">
                <span class="doc-step-badge bg-cyan-500/20 text-cyan-300 border border-cyan-500/30">1</span>
                <p class="mt-2 text-xs font-bold text-white">Desenvolvimento Local</p>
                <p class="text-[11px] text-slate-400">Alteração e validação inicial nos testes locais.</p>
              </div>
              <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-3">
                <span class="doc-step-badge bg-amber-500/20 text-amber-300 border border-amber-500/30">2</span>
                <p class="mt-2 text-xs font-bold text-white">Aplicação em Stage</p>
                <p class="text-[11px] text-slate-400">Replicação na homologação (<?= htmlspecialchars($stageLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>).</p>
              </div>
              <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-3">
                <span class="doc-step-badge bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">3</span>
                <p class="mt-2 text-xs font-bold text-white">Promoção para Produção</p>
                <p class="text-[11px] text-slate-400">Backup obrigatório, pacote gerado da stage e pós-check.</p>
              </div>
            </div>
          </div>

          <div class="doc-callout doc-callout-info mt-4">
            <p class="font-semibold text-cyan-100 flex items-center gap-2"><i class="fa-solid fa-bolt text-xs"></i> Performance da Central Operacional (IMP-034)</p>
            <p class="mt-1">A leitura de pacotes de conteúdo em disco de backup agora utiliza cache em <code class="font-mono text-cyan-200">storage/cache/content-sync-packages.json</code> com validade de 5 minutos. Isso reduziu o tempo de abertura do painel de ~12s para ~4s, com invalidação automática a cada novo pacote gerado.</p>
          </div>
        </section>

        <!-- 7. BACKUP E RESTORE -->
        <section id="backup" data-doc-panel="backup" class="doc-panel rounded-3xl border border-slate-800/90 bg-slate-900/85 p-6 shadow-sm" <?= $docSection === 'backup' ? '' : 'hidden' ?>>
          <div class="flex items-center gap-2.5 border-b border-slate-800/80 pb-4">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl border border-cyan-500/30 bg-cyan-500/10 text-cyan-300"><i class="fa-solid fa-database text-xs"></i></span>
            <div>
              <h2 class="font-orbitron text-xl font-bold text-white text-balance">7) Backup e Restore Sistêmico</h2>
              <p class="text-xs text-slate-400">Rotina diária, retenção, política de uploads e validação sem risco.</p>
            </div>
          </div>

          <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <h3 class="font-semibold text-white text-xs uppercase tracking-wider">Rotina Diária (21:30)</h3>
              <ul class="mt-3 space-y-2 text-xs text-slate-300">
                <li class="flex items-start gap-2"><i class="fa-solid fa-clock text-cyan-400 mt-0.5"></i> <span><strong>Agendamento:</strong> Tarefa do Windows <code class="font-mono text-cyan-300">EstrategiaNerd-BackupDiario</code> roda <code class="font-mono text-slate-200">Invoke-DailyBackup.ps1</code>.</span></li>
                <li class="flex items-start gap-2"><i class="fa-solid fa-folder text-cyan-400 mt-0.5"></i> <span><strong>Diretório Base:</strong> <code class="font-mono text-slate-200">C:\Users\WINDOWS\ProjectBackup\estrategia-nerd</code> (pastas <code class="font-mono text-slate-400">01-local</code>, <code class="font-mono text-slate-400">02-stage</code>, <code class="font-mono text-slate-400">03-prod</code>, <code class="font-mono text-slate-400">04-logs</code>).</span></li>
                <li class="flex items-start gap-2"><i class="fa-solid fa-box-archive text-cyan-400 mt-0.5"></i> <span><strong>Retenção:</strong> 7 backups por ambiente (<code class="font-mono text-slate-200">BACKUP_RETENTION=7</code>).</span></li>
                <li class="flex items-start gap-2"><i class="fa-solid fa-image text-cyan-400 mt-0.5"></i> <span><strong>Política de Uploads:</strong> Local roda <code class="font-mono text-amber-300">--no-uploads</code> de seg a sáb (já cobertos pelo restic geral) e completo aos domingos. Stage e Prod sempre completos.</span></li>
              </ul>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <h3 class="font-semibold text-white text-xs uppercase tracking-wider">Prova de Restauração em Sandbox</h3>
              <p class="mt-2 text-xs text-slate-300">Para garantir que os backups são restauráveis sem afetar as bases de dados em uso:</p>
              <div class="mt-3 rounded-xl border border-slate-800 bg-slate-900/90 p-2.5 font-mono text-[11px] text-cyan-300">
                php scripts/verify-backup-restore.php &lt;local|stage|production&gt; [ID]
              </div>
              <p class="mt-2 text-[11px] text-slate-400">Valida SHA-256 do manifesto, CRC de todos os zips e importa o banco em uma base temporária isolada (<code class="font-mono text-slate-300">en_restore_check_*</code>), que é descartada automaticamente.</p>
            </div>
          </div>

          <div class="doc-callout doc-callout-warn mt-4">
            <p class="font-semibold">Regra de Ouro do Restore</p>
            <p class="mt-1">O restore oficial sobrescreve completamente o banco de dados, uploads e arquivos de sistema do destino. <em>Nunca execute restore sem antes gerar e validar um backup do estado imediatamente anterior.</em></p>
          </div>
        </section>

        <!-- 8. BACKUP EM NUVEM (GOOGLE DRIVE) -->
        <section id="dropbox" data-doc-panel="dropbox" class="doc-panel rounded-3xl border border-slate-800/90 bg-slate-900/85 p-6 shadow-sm" <?= $docSection === 'dropbox' ? '' : 'hidden' ?>>
          <div class="flex items-center gap-2.5 border-b border-slate-800/80 pb-4">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl border border-cyan-500/30 bg-cyan-500/10 text-cyan-300"><i class="fa-brands fa-google-drive text-xs"></i></span>
            <div>
              <h2 class="font-orbitron text-xl font-bold text-white text-balance">8) Backup em Nuvem (Google Drive)</h2>
              <p class="text-xs text-slate-400">Consolidação oficial do Google Drive como destino externo (IMP-023 / IMP-033).</p>
            </div>
          </div>

          <div class="doc-callout doc-callout-info mt-4">
            <p class="font-semibold text-cyan-100">Destino Oficial e Expurgamento do Dropbox (IMP-033)</p>
            <p class="mt-1">O Google Drive é o único provedor de nuvem do portal. No IMP-033 o Dropbox foi 100% removido das telas e do código. O manifesto registra <code class="font-mono text-cyan-200">cloud_provider = google_drive</code>, e a pasta remota oficial é <code class="font-mono text-cyan-200">Estrategia Nerd - Backups de Conteudo/Sistema/&lt;Ambiente&gt;/&lt;ID&gt;</code>.</p>
          </div>

          <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <h3 class="font-semibold text-white text-xs uppercase tracking-wider">Operação Via Terminal (CLI)</h3>
              <ul class="mt-3 space-y-2 text-xs text-slate-300">
                <li><strong class="text-white">Envio Sistêmico:</strong> <code class="font-mono text-cyan-300">php scripts/backup.php upload-cloud latest</code></li>
                <li><strong class="text-white">Envio Editorial:</strong> <code class="font-mono text-cyan-300">php scripts/content-sync.php upload-cloud latest</code></li>
              </ul>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <h3 class="font-semibold text-white text-xs uppercase tracking-wider">Operação Pelo Painel Local</h3>
              <ul class="mt-3 space-y-2 text-xs text-slate-300">
                <li><strong class="text-white">Central Operacional V2:</strong> <code class="font-mono text-slate-200">/admin/central-operacional-v2/backup-em-nuvem</code></li>
                <li><strong class="text-white">Central de Backup:</strong> <code class="font-mono text-slate-200">/local/backup?backup_secao=nuvem</code></li>
                <li class="text-[11px] text-slate-400">Exclusões remotas exigem confirmação explícita digitando o ID do backup.</li>
              </ul>
            </div>
          </div>
        </section>

        <!-- 9. CONFIGURAÇÕES DE AMBIENTE (.ENV) -->
        <section id="ambiente" data-doc-panel="ambiente" class="doc-panel rounded-3xl border border-slate-800/90 bg-slate-900/85 p-6 shadow-sm" <?= $docSection === 'ambiente' ? '' : 'hidden' ?>>
          <div class="flex items-center gap-2.5 border-b border-slate-800/80 pb-4">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl border border-cyan-500/30 bg-cyan-500/10 text-cyan-300"><i class="fa-solid fa-sliders text-xs"></i></span>
            <div>
              <h2 class="font-orbitron text-xl font-bold text-white text-balance">9) Configurações de Ambiente (.env)</h2>
              <p class="text-xs text-slate-400">Dicionário de variáveis oficiais (apenas nomes e finalidades; segredos preservados).</p>
            </div>
          </div>

          <div class="mt-4 overflow-hidden rounded-2xl border border-slate-800 bg-slate-950/70">
            <table class="doc-table">
              <thead>
                <tr><th>Chave</th><th>Finalidade</th><th>Observação Operacional</th></tr>
              </thead>
              <tbody>
                <tr><td class="font-mono text-xs text-cyan-300">APP_ENV / APP_DEBUG / APP_URL</td><td class="text-slate-300">Definição do ambiente ativo, modo debug e URL base.</td><td class="text-xs text-rose-300">Em produção e stage `APP_DEBUG` deve ser estritamente false.</td></tr>
                <tr><td class="font-mono text-xs text-cyan-300">DB_HOST / DB_PORT / DB_DATABASE / DB_USER...</td><td class="text-slate-300">Conexão principal PDO do ambiente atual.</td><td class="text-xs text-slate-400">Utiliza UTF8MB4. Credenciais nunca versionadas.</td></tr>
                <tr><td class="font-mono text-xs text-cyan-300">BACKUP_ROOT / BACKUP_RETENTION</td><td class="text-slate-300">Diretório local oficial dos backups e limite de retenção.</td><td class="text-xs text-slate-400">Padrão: `C:\Users\WINDOWS\ProjectBackup\estrategia-nerd` e 7 cópias.</td></tr>
                <tr><td class="font-mono text-xs text-cyan-300">BACKUP_GOOGLE_DRIVE_CLIENT_*</td><td class="text-slate-300">Credenciais OAuth para sincronização de backups no Drive.</td><td class="text-xs text-slate-400">Configurado em `config/backup-cloud.php`. Somente no local.</td></tr>
                <tr><td class="font-mono text-xs text-cyan-300">CONTENT_SYNC_CURRENT_SOURCE</td><td class="text-slate-300">Origem atual do ambiente (`local`, `stage`, etc.).</td><td class="text-xs text-amber-300">Trava operacional de integridade.</td></tr>
                <tr><td class="font-mono text-xs text-cyan-300">CONTENT_SYNC_APPROVED_PACKAGE_SOURCE</td><td class="text-slate-300">Origem obrigatória aceita para pacotes de produção.</td><td class="text-xs text-emerald-300">Valor oficial: `stage`.</td></tr>
                <tr><td class="font-mono text-xs text-cyan-300">INSTAGRAM_PUBLIC_MEDIA_URL</td><td class="text-slate-300">URL pública de onde a Meta baixa vídeos e capas dos Reels.</td><td class="text-xs text-slate-400">Deve responder HTTP 200 externamente (Cloudflare Tunnel).</td></tr>
                <tr><td class="font-mono text-xs text-cyan-300">YOUTUBE_CLIENT_ID / YOUTUBE_CLIENT_SECRET / YOUTUBE_REDIRECT_URI</td><td class="text-slate-300">OAuth dedicado do módulo de vídeos em <code>/admin/youtube</code>.</td><td class="text-xs text-slate-400">Somente no `.env` local; tokens ficam em `storage/app/youtube`.</td></tr>
                <tr><td class="font-mono text-xs text-cyan-300">GEMINI_API_KEY / GEMINI_TEXT_MODEL...</td><td class="text-slate-300">Geração de legendas em cross-posts Blog &rarr; Instagram.</td><td class="text-xs text-slate-400">Somente no `.env` local.</td></tr>
                <tr><td class="font-mono text-xs text-cyan-300">MATOMO_URL / MATOMO_SITE_ID</td><td class="text-slate-300">Analytics de audiência e tráfego auto-hospedado.</td><td class="text-xs text-slate-400">Configurado no IMP-022.</td></tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- 10. SEGURANÇA DO SISTEMA -->
        <section id="seguranca" data-doc-panel="seguranca" class="doc-panel rounded-3xl border border-slate-800/90 bg-slate-900/85 p-6 shadow-sm" <?= $docSection === 'seguranca' ? '' : 'hidden' ?>>
          <div class="flex items-center gap-2.5 border-b border-slate-800/80 pb-4">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl border border-cyan-500/30 bg-cyan-500/10 text-cyan-300"><i class="fa-solid fa-shield-halved text-xs"></i></span>
            <div>
              <h2 class="font-orbitron text-xl font-bold text-white text-balance">10) Segurança do Sistema</h2>
              <p class="text-xs text-slate-400">Proteção de borda, isolamento de rotas e mitigação de vulnerabilidades.</p>
            </div>
          </div>

          <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <h3 class="font-semibold text-white text-xs uppercase tracking-wider flex items-center gap-2"><i class="fa-solid fa-lock text-cyan-400"></i> Proteção de Borda & Acesso</h3>
              <ul class="mt-3 space-y-2 text-xs text-slate-300">
                <li>&bull; <strong class="text-white">Cloudflare Access:</strong> O admin e as rotas de sistema exigem login institucional na camada de borda do Cloudflare Tunnel.</li>
                <li>&bull; <strong class="text-white">Bloqueio Local:</strong> As rotas <code>/local/*</code> executam <code>EnvironmentGuard::requireLocal()</code>, aceitando apenas localhost, CLI ou LAN privada autorizada.</li>
                <li>&bull; <strong class="text-white">CSRF Obrigatório:</strong> Toda requisição POST no admin valida token anti-CSRF unificado.</li>
              </ul>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <h3 class="font-semibold text-white text-xs uppercase tracking-wider flex items-center gap-2"><i class="fa-solid fa-eye-slash text-cyan-400"></i> Bloqueio SEO na Stage</h3>
              <ul class="mt-3 space-y-2 text-xs text-slate-300">
                <li>&bull; <strong class="text-white">Noindex Garantido:</strong> Com <code class="font-mono text-cyan-300">APP_ENV=stage</code>, o <code class="font-mono text-slate-200">bootstrap.php</code> envia automaticamente o header <code class="font-mono text-amber-300">X-Robots-Tag: noindex, nofollow</code> em todas as respostas HTTP.</li>
                <li>&bull; <strong class="text-white">Uploads Protegidos:</strong> Em <code class="font-mono text-slate-200">public/uploads/.htaccess</code> a execução direta de scripts PHP é expressamente revogada.</li>
              </ul>
            </div>
          </div>
        </section>

        <!-- 11. INSTAGRAM & REELS -->
        <section id="instagram" data-doc-panel="instagram" class="doc-panel rounded-3xl border border-slate-800/90 bg-slate-900/85 p-6 shadow-sm" <?= $docSection === 'instagram' ? '' : 'hidden' ?>>
          <div class="flex items-center gap-2.5 border-b border-slate-800/80 pb-4">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl border border-cyan-500/30 bg-cyan-500/10 text-cyan-300"><i class="fa-brands fa-instagram text-xs"></i></span>
            <div>
              <h2 class="font-orbitron text-xl font-bold text-white text-balance">11) Instagram & Reels (Motores de Vídeo)</h2>
              <p class="text-xs text-slate-400">Arquitetura local-only, publicação agendada, múltiplos Reels e motores visuais.</p>
            </div>
          </div>

          <div class="doc-callout doc-callout-info mt-4">
            <p class="font-semibold text-cyan-100">Arquitetura Local-Only (FEAT-010)</p>
            <p class="mt-1">Tokens da Meta Graph API v21.0, fila de agendamento e cache de insights residem exclusivamente no MySQL local (<code class="font-mono text-cyan-200">$GLOBALS['pdo']</code>). A conta @estrategia_nerd é unificada e não trafega para o banco remoto de produção.</p>
          </div>

          <div class="mt-5 rounded-2xl border border-slate-800 bg-slate-950/70 p-5">
            <h3 class="font-orbitron text-xs font-black uppercase tracking-wider text-slate-300">Motores de Renderização de Vídeo</h3>
            <div class="mt-3 grid gap-3 md:grid-cols-3">
              <div class="rounded-xl border border-cyan-500/30 bg-cyan-950/20 p-3.5">
                <span class="rounded bg-cyan-900/80 px-2 py-0.5 text-[10px] font-bold text-cyan-300 uppercase">Oficial</span>
                <p class="mt-2 text-xs font-bold text-white">Motionmaxxing</p>
                <p class="mt-1 text-[11px] text-slate-300 leading-relaxed">Tipografia cinética animada com GSAP/HTML capturada via Chrome Headless. Padrão para artigos e vídeos informativos.</p>
              </div>
              <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-3.5">
                <span class="rounded bg-slate-800 px-2 py-0.5 text-[10px] font-bold text-slate-300 uppercase">Sequencial</span>
                <p class="mt-2 text-xs font-bold text-white">OneTake</p>
                <p class="mt-1 text-[11px] text-slate-300 leading-relaxed">Planos-sequência contínuos via Playwright em Python com motion blur real (180&deg;) e áudio procedural. Ideal para teasers e lançamentos.</p>
              </div>
              <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-3.5">
                <span class="rounded bg-slate-800 px-2 py-0.5 text-[10px] font-bold text-slate-300 uppercase">GPU Nativo</span>
                <p class="mt-2 text-xs font-bold text-white">Framefields</p>
                <p class="mt-1 text-[11px] text-slate-300 leading-relaxed">Motor acelerado por hardware via WebGPU/Dawn (Node 22+) sem overhead de navegador, com suporte a 3D e BeatGrid musical.</p>
              </div>
            </div>
            <p class="mt-3 text-[11px] text-slate-400 italic">* O motor legado Konva/Canvas de slides estáticos foi formalmente descontinuado.</p>
          </div>

          <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <h3 class="font-semibold text-white text-xs uppercase tracking-wider">Múltiplos Reels por Post (IMP-035 & IMP-037)</h3>
              <p class="mt-2 text-xs text-slate-300 leading-relaxed">Um mesmo artigo pode ter vários Reels vinculados. No editor do post, o método <code class="font-mono text-cyan-300">findLinkedReels</code> lista todos os vídeos relacionados. Múltiplos vídeos usam pastas padronizadas <code class="font-mono text-slate-200">public/uploads/posts/&lt;slug&gt;/reels/reel-&lt;id&gt;/</code>.</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <h3 class="font-semibold text-white text-xs uppercase tracking-wider">Biblioteca de Trilhas (IMP-036)</h3>
              <p class="mt-2 text-xs text-slate-300 leading-relaxed">A seleção de áudio conta com busca em tempo real por título/artista e seletor de paginação (5, 10, 20 ou 50 por página) diretamente no modal de criação e edição de postagens.</p>
            </div>
          </div>

          <div class="mt-4 rounded-2xl border border-slate-800 bg-slate-950/70 p-5">
            <h3 class="font-orbitron text-xs font-black uppercase tracking-wider text-slate-300">Painel de Métricas (/admin/instagram)</h3>
            <ul class="mt-2 space-y-1.5 text-xs text-slate-300 leading-relaxed">
              <li><strong class="text-white">Cards KPI:</strong> Seguidores, Seguindo, Mídias e Alcance mostram a variação real em relação ao registro anterior (alcance em %) e uma curva com o histórico de <code class="font-mono text-cyan-300">instagram_insights_cache</code>. Sem registro anterior aparece "—" e "sem histórico ainda"; nenhum valor é inventado.</li>
              <li><strong class="text-white">Histórico de Seguindo e Mídias:</strong> cada sincronização grava <code class="font-mono text-cyan-300">follows_count</code> e <code class="font-mono text-cyan-300">media_count</code> em <code class="font-mono text-slate-200">payload_raw._account_snapshot</code>. A curva de Seguindo aparece a partir do segundo dia com registro.</li>
              <li><strong class="text-white">Filtro de período e alcance por ponto:</strong> calendário travado entre o primeiro post publicado (20/11/2024) e hoje, sem cortes artificiais de 30 ou 90 dias. Cada ponto traz alcance consultado na Meta e persistido em <code class="font-mono text-cyan-300">instagram_reach_blocks</code> (blocos encerrados há mais de 2 dias não são reconsultados, tornando consultas subsequentes instantâneas). Faixas adaptativas do gráfico: até 14 dias (diário), 15 a 45 dias (a cada 3 dias), 46 a 180 dias (semanal) e acima de 180 dias (blocos de 28 dias). O card de Alcance exibe o total exato da Meta em janelas de até 30 dias e a soma dos blocos acompanhada de aviso explicativo em intervalos maiores.</li>
              <li><strong class="text-white">Troca de período:</strong> sem recarregar a página, com animação suave das linhas de curtidas e alcance, dos números e do donut (desligada quando o sistema pede menos movimento).</li>
            </ul>
          </div>

          <div class="mt-4 rounded-2xl border border-cyan-500/30 bg-slate-950/70 p-5">
            <h3 class="font-orbitron text-xs font-black uppercase tracking-wider text-slate-300">Série de Vídeos do Echo (Molde e Quarto Vivo em Camadas)</h3>
            <p class="mt-2 text-xs text-slate-300 leading-relaxed">Os vídeos em que o Echo apresenta seguem um molde com três partes separadas: o que é <strong class="text-white">igual em todo vídeo</strong> (Echo sem braços nem pernas e com a boca acompanhando a voz, 1080×1920 a 60 fps, área segura das duas redes, quarto vivo em camadas como cópia do setup do operador, ordem com aprovação em cada etapa e conferências técnicas), o que <strong class="text-white">se decide em cada vídeo</strong> com o operador (cenários extras, figurino, fonte e posição da legenda, música, efeitos, capa, horário) e os <strong class="text-white">erros já cometidos</strong>, com causa e prevenção.</p>
            <ul class="mt-2.5 space-y-1.5 text-xs text-slate-300 leading-relaxed">
              <li><strong class="text-white">Quarto padrão oficial (<code class="font-mono text-cyan-300">_kit-quarto/quarto_setup_raster.js</code> + <code class="font-mono text-cyan-300">producao-camadas-v1/</code>):</strong> composição 1080×1920 em camadas independentes (<code class="font-mono text-slate-200">00-ambiente</code>, <code class="font-mono text-slate-200">10-quadros</code> com os 5 quadros de Yu-Gi-Oh, <code class="font-mono text-slate-200">20-mesa-equipamentos</code>, <code class="font-mono text-slate-200">30-cadeira</code> e <code class="font-mono text-slate-200">31-cadeira-frente</code> ampliadas 1,28×, <code class="font-mono text-slate-200">40-pufe</code> e <code class="font-mono text-slate-200">50-acabamento</code>), com 4 modos de oclusão (<code class="font-mono text-cyan-300">sentado</code>, <code class="font-mono text-cyan-300">frente</code>, <code class="font-mono text-cyan-300">atras-da-cadeira</code>, <code class="font-mono text-cyan-300">atras-da-mesa</code>) e área dedicada no monitor central (<code class="font-mono text-cyan-300">mainScreen</code>) para gameplay.</li>
              <li><strong class="text-white">Iluminação viva determinística por <code class="font-mono text-cyan-300">tempo</code>:</strong> 6 painéis hexagonais LED 3D e 3 barras neon na parede (com apagamento e halo real), painel pixelado 6×6 em perspectiva sobre a mesa e gabinete RGB (3 ventoinhas frontais, cooler traseiro e memórias RAM percorrendo o espectro completo).</li>
              <li><strong class="text-white">Atuação e encerramento padrão (60 fps):</strong> o Echo inicia sentado sobre a almofada da cadeira (<code class="font-mono text-slate-200">X=289, Y=1574, k=1.50</code>) segurando o controle 3D no colo com as mãos azuis oficiais; no fechamento, pousa o controle 3D sobre o assento (<code class="font-mono text-slate-200">X=289, Y=1558</code>), sai da cadeira em trajetória fluida e passa a flutuar no meio do quarto (<code class="font-mono text-slate-200">X=575, Y=1460, k=1.85</code>) com a Propulsão Matrix (<code class="font-mono text-cyan-300">echo-propulsao-matrix.js</code>) acesa sob a base.</li>
            </ul>
            <div class="mt-3 flex flex-wrap gap-2">
              <a href="<?= htmlspecialchars($echoSeriesDocUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="rounded-lg border border-cyan-500/40 bg-cyan-950/30 px-3 py-1.5 text-[11px] font-semibold text-cyan-200 hover:bg-cyan-900/40">Abrir o molde da série</a>
              <a href="<?= htmlspecialchars($videoDirectionDocUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="rounded-lg border border-slate-700 bg-slate-900/60 px-3 py-1.5 text-[11px] font-semibold text-slate-200 hover:bg-slate-800">Guia de direção audiovisual</a>
            </div>
            <p class="mt-3 text-[11px] text-slate-400">Skills: <code class="font-mono text-cyan-300">video-echo-serie</code> (produção) e <code class="font-mono text-cyan-300">agendar-reel-instagram</code> (fila). Pacotes de referência: <code class="font-mono text-slate-200">conteudo-planejado/videos-echo/_kit-quarto/</code> e <code class="font-mono text-slate-200">conteudo-planejado/videos-echo/manifesto-estrategia-nerd/</code>.</p>
          </div>

          <div class="mt-4 rounded-2xl border border-purple-500/30 bg-slate-950/70 p-5">
            <div class="flex items-center justify-between">
              <h3 class="font-orbitron text-xs font-black uppercase tracking-wider text-purple-300">Segunda Integração: Stories e Destaques (@estrategia_nerd)</h3>
              <span class="rounded bg-purple-900/60 px-2 py-0.5 text-[10px] font-bold text-purple-200 uppercase">instagrapi</span>
            </div>
            <p class="mt-2 text-xs text-slate-300 leading-relaxed">
              Página própria em <code class="font-mono text-cyan-300">/admin/instagram-destaques</code>, no menu abaixo de Instagram. O operador adiciona imagens e links; o sistema publica os stories e monta o destaque. O assistente mostra uma etapa por vez: <strong>Configurar conta → Montar destaque → Conferir e publicar</strong>.
            </p>
            <ol class="mt-3 list-decimal pl-5 space-y-2 text-xs text-slate-300 leading-relaxed">
              <li><strong class="text-white">Configurar conta:</strong> “Como conectar minha conta” exibe instruções e um comando copiável para autenticar pelo terminal. O botão não conecta sozinho. É possível preparar a montagem antes; ter uma sessão armazenada não comprova conexão válida com o Instagram.</li>
              <li><strong class="text-white">Montar destaque:</strong> escolher novo ou existente, informar nome (até 15 caracteres, como “PC R$400”) e capa opcional. Em destaque existente, nome e capa em branco preservam os atuais. Cada story reúne imagem JPEG 1080×1920, link HTTPS e ações para ordenar ou remover; até 20 imagens, de até 8 MB cada. A figurinha de link é posicionada acima do CTA da arte em <code class="font-mono text-cyan-300">x=0.5, y=0.70</code>, com tamanho normalizado <code class="font-mono text-cyan-300">0.38×0.07</code> (cerca de 410×134 px). A capa deve ser JPEG quadrado de 320 a 2160 px.</li>
              <li><strong class="text-white">Conferir e publicar:</strong> revisar conta, destaque, capa, imagens e links. Voltar mantém a montagem na página. A confirmação “Publicar stories e montar destaque” inicia o envio e seu progresso; um resultado incerto pede conferência no aplicativo antes de continuar.</li>
            </ol>
            <p class="mt-3 text-[11px] text-slate-400">A prévia aparece após adicionar conteúdo; no celular, abra “Ver como ficará”. “Publicações anteriores” fica recolhida, com consulta e retomada. A montagem ainda não revisada não é um rascunho salvo para recuperar após fechar ou recarregar a página. Links para artigos do portal usam obrigatoriamente <code class="font-mono text-slate-200">https://estrategianerd.com.br/post/{slug}</code>. Na leitura de confirmação, o motor aceita o redirecionamento seguro de <code class="font-mono text-slate-200">l.instagram.com</code> e ignora apenas o parâmetro volátil <code class="font-mono text-slate-200">fbclid</code>.</p>
            <div class="mt-3 grid gap-3 md:grid-cols-3 text-[11px] text-slate-300">
              <div class="rounded-lg bg-slate-900/80 p-2.5 border border-slate-800">
                <strong class="text-white block mb-1">Ponte Isolada via CLI</strong>
                Subprocesso Python 3.13 (<code class="font-mono text-cyan-300">cli.py</code>) via <code class="font-mono text-slate-200">proc_open</code> com argumentos estáticos, JSON via stdin e fechamento prévio de sessão PHP.
              </div>
              <div class="rounded-lg bg-slate-900/80 p-2.5 border border-slate-800">
                <strong class="text-white block mb-1">Prevenção de Duplicatas</strong>
                Adaptador single-attempt sem retentativas em timeouts. Estado incerto entra em status <code class="font-mono text-amber-300">unknown</code> e exige reconciliação manual assistida com confirmação visual no aplicativo móvel.
              </div>
              <div class="rounded-lg bg-slate-900/80 p-2.5 border border-slate-800">
                <strong class="text-white block mb-1">Autenticação Segura</strong>
                Inicialização de sessão estritamente pelo terminal (<code class="font-mono text-cyan-300">auth_cli.py</code>) com senha oculta e 2FA, gravando <code class="font-mono text-slate-200">session.json</code> com permissões restritas.
              </div>
            </div>
          </div>

          <div class="doc-callout doc-callout-warn mt-4">
            <p class="font-semibold">Área Segura do Reels e do Shorts (1080x1920)</p>
            <p class="mt-1 text-xs">Todo texto e elemento de leitura (incluindo o rosto do Echo) deve permanecer entre <strong class="text-white">Y: 250 e Y: 1450</strong> e entre <strong class="text-white">X: 90 e X: 990</strong>, com margem direita em <strong class="text-white">X: 925 entre Y: 970 e Y: 1450</strong> para não ser encoberto pela coluna lateral de botões dos dois apps (curtir, comentar e compartilhar). A capa segue a mesma área e cabe na grade 3:4 do Instagram (Y: 240 a 1680).</p>
          </div>

          <div class="mt-5 rounded-2xl border border-red-500/30 bg-slate-950/70 p-5">
            <h3 class="font-orbitron text-xs font-black uppercase tracking-wider text-red-400 flex items-center gap-2">
              <i class="fa-brands fa-youtube"></i>
              <span>Módulo YouTube Studio (/admin/youtube — FEAT-youtube-integration)</span>
            </h3>
            <p class="mt-2 text-xs text-slate-300 leading-relaxed">
              Painel com paridade operacional ao Instagram: sincronização de acervo de vídeos e Shorts, métricas de visualizações e curtidas, distribuição de formatos e fila de envio resumível em blocos com agendamento privado nativo via YouTube Data API v3.
            </p>
            <div class="mt-3 grid gap-3 md:grid-cols-3">
              <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-3">
                <span class="text-[10px] font-bold text-red-400 uppercase">Acervo & Métricas</span>
                <p class="mt-1 text-xs text-slate-200">Sincronização em lotes de 50 itens via <code>playlistItems</code> e <code>videos.list</code> com histórico diário. Vídeos <strong>não listados</strong> (os antigos do canal, desde 31/10/2026) ficam fora da lista padrão, dos KPIs, da distribuição e do resumo do painel; o filtro <em>Privacidade</em> mostra só eles ou todos, e a lista marca cada um com o selo "Não listado" ou "Privado" (a privacidade só é relida ao apertar Sincronizar). "Agendado" (filtro e contador do painel) inclui a fila local e o vídeo já enviado ao YouTube com hora marcada (<code>agendado_remoto</code>). O card "Visualizações (fase nova)" soma as visualizações gravadas na última sincronização (sem os não listados); o total do canal informado pela API aparece embaixo, porque atrasa e não conta os não listados. Cada número aparece num lugar só (cards de cima e gráfico). O card "Vídeos e Shorts" não conta cancelados nem não listados; a lista mostra publicados, depois agendados, depois o resto, esconde os cancelados (filtro Status → "Cancelado") e, nos agendados, mostra "Agendado para" com a data marcada. O gráfico mostra as visualizações acumuladas de cada vídeo no dia da publicação (não é visualização por dia). Período, filtros, ordenação, paginação e Sincronizar atualizam sem recarregar (<code>?ajax=metrics</code> e <code>?ajax=videos</code>, parcial <code>_lista-videos.php</code>); sem JavaScript a página recarrega como antes (FEAT-youtube-revisao-pagina).</p>
              </div>
              <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-3">
                <span class="text-[10px] font-bold text-cyan-400 uppercase">Upload Resumível</span>
                <p class="mt-1 text-xs text-slate-200">Envio de MP4 em blocos de 8MB com offset persistido no banco e lock contra concorrência. Todo agendamento é despachado imediatamente para a API do YouTube com data/hora de publicação programada (<code>publishAt</code> e privacidade privada), convertendo o item para <code>agendado_remoto</code> ("Agendado no YouTube") com permalink e ID gerados de imediato. Inclui envio de capas personalizadas (<code>thumbnails.set</code>) e persistência automática de <code>thumbnail_url</code> da CDN do YouTube durante a reconciliação periódica.</p>
              </div>
              <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-3">
                <span class="text-[10px] font-bold text-purple-400 uppercase">Radar & Transcrições</span>
                <p class="mt-1 text-xs text-slate-200">Espionagem de tendências e transcrições automáticas sem consumo de cota (FEAT-youtube-research-radar).</p>
              </div>
            </div>
          </div>

          <div class="mt-5 rounded-2xl border border-indigo-500/30 bg-slate-950/70 p-5">
            <h3 class="font-orbitron text-xs font-black uppercase tracking-wider text-indigo-400 flex items-center gap-2">
              <i class="fa-solid fa-share-nodes"></i>
              <span>Conectores Sociais & Blindagem de API (Frente 3 — TryPost &rarr; Estratégia Nerd)</span>
            </h3>
            <p class="mt-2 text-xs text-slate-300 leading-relaxed">
              Módulos nativos em PHP para distribuição multicanal dos conteúdos do Echo e postagens do portal, com proteção defensiva contra concorrência e expiração de credenciais.
            </p>
            <div class="mt-3 grid gap-3 md:grid-cols-3">
              <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-3">
                <span class="text-[10px] font-bold text-pink-400 uppercase">Blindagem Instagram</span>
                <p class="mt-1 text-xs text-slate-200">Renovação proativa de token de longa duração via <code>oauth/access_token</code> e <code>refresh_access_token</code> quando faltam menos de 48h para expirar. Prevenção de duplicatas com reutilização atômica de <code>creation_id</code> validado na Meta.</p>
              </div>
              <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-3">
                <span class="text-[10px] font-bold text-cyan-400 uppercase">Conector TikTok</span>
                <p class="mt-1 text-xs text-slate-200">Publicação de vídeos verticais (1080x1920) via <code>TikTokPublishService</code> utilizando Content Posting API v2 (<code>PULL_FROM_URL</code>), polling seguro de status e renovação OAuth via refresh_token.</p>
              </div>
              <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-3">
                <span class="text-[10px] font-bold text-indigo-300 uppercase">Conector Threads</span>
                <p class="mt-1 text-xs text-slate-200">Publicador <code>ThreadsPublishService</code> com suporte a texto, fotos, vídeos e carrosséis via Threads API oficial, consulta de contêineres e renovação de token permanente.</p>
              </div>
            </div>
          </div>
        </section>

        <!-- 12. CENTRAL NERD E AFILIADOS -->
        <section id="central-nerd" data-doc-panel="central-nerd" class="doc-panel rounded-3xl border border-slate-800/90 bg-slate-900/85 p-6 shadow-sm" <?= $docSection === 'central-nerd' ? '' : 'hidden' ?>>
          <div class="flex items-center gap-2.5 border-b border-slate-800/80 pb-4">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl border border-cyan-500/30 bg-cyan-500/10 text-cyan-300"><i class="fa-solid fa-cart-shopping text-xs"></i></span>
            <div>
              <h2 class="font-orbitron text-xl font-bold text-white text-balance">12) Central Nerd & Afiliados</h2>
              <p class="text-xs text-slate-400">Rastreamento de conversão, ofertas contextuais em posts e filtro anti-scanner.</p>
            </div>
          </div>

          <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <h3 class="font-semibold text-white text-xs uppercase tracking-wider">Ofertas nos Artigos (FEAT-013)</h3>
              <p class="mt-2 text-xs text-slate-300 leading-relaxed">Ao final de cada artigo do blog, o <code class="font-mono text-cyan-300">RelatedOffersService</code> mapeia ofertas contextuais da categoria (hardware, games, gadgets) e sorteia 3 produtos a cada visita com cabeçalho <code class="font-mono text-slate-200">no-store</code>, rastreando a conversão com a tag <code class="font-mono text-slate-200">post-{id}</code>.</p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <h3 class="font-semibold text-white text-xs uppercase tracking-wider">Filtro de Cliques Falsos</h3>
              <p class="mt-2 text-xs text-slate-300 leading-relaxed">Scanners automáticos que injetam SQL em parâmetros de origem são neutralizados: origens que contenham caracteres fora de <code class="font-mono text-cyan-300">[a-z0-9:_-]</code> são redirecionadas normalmente mas <strong>não computam clique</strong> no banco de dados, protegendo a integridade das métricas.</p>
            </div>
          </div>
        </section>

        <!-- 13. REGRAS DE DESENVOLVIMENTO -->
        <section id="regras-dev" data-doc-panel="regras-dev" class="doc-panel rounded-3xl border border-slate-800/90 bg-slate-900/85 p-6 shadow-sm" <?= $docSection === 'regras-dev' ? '' : 'hidden' ?>>
          <div class="flex items-center gap-2.5 border-b border-slate-800/80 pb-4">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl border border-cyan-500/30 bg-cyan-500/10 text-cyan-300"><i class="fa-solid fa-gavel text-xs"></i></span>
            <div>
              <h2 class="font-orbitron text-xl font-bold text-white text-balance">13) Regras de Desenvolvimento</h2>
              <p class="text-xs text-slate-400">Diretrizes de sustentabilidade de código e disciplina de manutenção.</p>
            </div>
          </div>

          <div class="mt-4 space-y-2.5">
            <div class="flex items-start gap-3 rounded-2xl border border-slate-800 bg-slate-950/70 p-3.5 text-xs text-slate-300">
              <span class="doc-step-badge bg-cyan-500/20 text-cyan-300 border border-cyan-500/30">1</span>
              <div><strong class="text-white">Controller Não Acessa Banco:</strong> Toda persistência ou consulta deve passar obrigatoriamente pela camada de Repository.</div>
            </div>
            <div class="flex items-start gap-3 rounded-2xl border border-slate-800 bg-slate-950/70 p-3.5 text-xs text-slate-300">
              <span class="doc-step-badge bg-cyan-500/20 text-cyan-300 border border-cyan-500/30">2</span>
              <div><strong class="text-white">Services Concentram Regra de Negócio:</strong> Mantenha a coerência entre módulos e isolamento de efeitos colaterais.</div>
            </div>
            <div class="flex items-start gap-3 rounded-2xl border border-slate-800 bg-slate-950/70 p-3.5 text-xs text-slate-300">
              <span class="doc-step-badge bg-cyan-500/20 text-cyan-300 border border-cyan-500/30">3</span>
              <div><strong class="text-white">Hotfixes em Produção:</strong> Qualquer hotfix aplicado em ambiente de produção deve ser retroalimentado para Local e Stage no mesmo ciclo.</div>
            </div>
            <div class="flex items-start gap-3 rounded-2xl border border-slate-800 bg-slate-950/70 p-3.5 text-xs text-slate-300">
              <span class="doc-step-badge bg-cyan-500/20 text-cyan-300 border border-cyan-500/30">4</span>
              <div><strong class="text-white">Atualização Simultânea da Documentação:</strong> Nenhuma alteração funcional é considerada concluída sem atualizar a documentação central na mesma tarefa.</div>
            </div>
          </div>
        </section>

        <!-- 14. GOVERNANÇA DE RELEASES -->
        <section id="governanca-releases" data-doc-panel="governanca-releases" class="doc-panel rounded-3xl border border-slate-800/90 bg-slate-900/85 p-6 shadow-sm" <?= $docSection === 'governanca-releases' ? '' : 'hidden' ?>>
          <div class="flex items-center gap-2.5 border-b border-slate-800/80 pb-4">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl border border-cyan-500/30 bg-cyan-500/10 text-cyan-300"><i class="fa-solid fa-clipboard-check text-xs"></i></span>
            <div>
              <h2 class="font-orbitron text-xl font-bold text-white text-balance">14) Governança Oficial de Releases</h2>
              <p class="text-xs text-slate-400">Matriz de risco, rastreabilidade e critérios obrigatórios de aceitação.</p>
            </div>
          </div>

          <div class="doc-callout doc-callout-info mt-4">
            <p class="font-semibold text-cyan-100">Matriz Oficial de Risco</p>
            <p class="mt-1">As diretrizes oficiais de risco e execução técnica do projeto estão registradas em <code class="font-mono text-cyan-200">docs/governanca/priorizacao-tecnica.md</code>. Toda entrega deve registrar impacto, paridade confirmada e plano de rollback.</p>
          </div>

          <div class="mt-5 rounded-2xl border border-slate-800 bg-slate-950/70 p-5">
            <h3 class="font-orbitron text-xs font-black uppercase tracking-wider text-slate-300">Suíte Obrigatória de Pré-Commit / Conclusão</h3>
            <p class="mt-1 text-xs text-slate-400">Nenhum commit é permitido sem que a suíte termine em aprovação total:</p>
            <div class="mt-3 rounded-xl border border-slate-800 bg-slate-900/90 p-3 font-mono text-xs text-emerald-300">
              C:\xampp\php\php.exe scripts/verify-changes.php
            </div>
            <p class="mt-2 text-xs text-slate-400">Valida lint PHP, PHPStan nível 5 (0 erros tolerados), renderização do dashboard nos 3 ambientes e integridade do módulo Instagram.</p>
          </div>
        </section>

        <!-- 15. ERROS COMUNS E DIAGNÓSTICO -->
        <section id="diagnostico" data-doc-panel="diagnostico" class="doc-panel rounded-3xl border border-slate-800/90 bg-slate-900/85 p-6 shadow-sm" <?= $docSection === 'diagnostico' ? '' : 'hidden' ?>>
          <div class="flex items-center gap-2.5 border-b border-slate-800/80 pb-4">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl border border-cyan-500/30 bg-cyan-500/10 text-cyan-300"><i class="fa-solid fa-triangle-exclamation text-xs"></i></span>
            <div>
              <h2 class="font-orbitron text-xl font-bold text-white text-balance">15) Erros Comuns e Diagnóstico Rápido</h2>
              <p class="text-xs text-slate-400">Guia de troubleshooting direto para os problemas operacionais mais frequentes.</p>
            </div>
          </div>

          <div class="mt-4 overflow-hidden rounded-2xl border border-slate-800 bg-slate-950/70">
            <table class="doc-table">
              <thead>
                <tr><th>Sintoma / Problema</th><th>Causa Raiz Mais Provável</th><th>Ação Recomendada</th></tr>
              </thead>
              <tbody>
                <tr><td class="font-semibold text-rose-300">Central Operacional lenta (~12s)</td><td class="text-slate-300">Varredura repetida de pacotes em disco externo sem cache.</td><td class="text-xs text-cyan-300">Limpar ou aguardar o cache em <code class="font-mono text-slate-200">storage/cache/content-sync-packages.json</code> (IMP-034).</td></tr>
                <tr><td class="font-semibold text-rose-300">Capa do artigo pesada degradando o site</td><td class="text-slate-300">Upload de PNG não otimizado (~2 a 3 MB).</td><td class="text-xs text-cyan-300">Converter obrigatoriamente para WebP (~150-250 KB) e atualizar <code class="font-mono text-slate-200">posts.imagem_capa</code>.</td></tr>
                <tr><td class="font-semibold text-rose-300">Reel bloqueado: "Capa ausente ou inválida"</td><td class="text-slate-300">Ausência do arquivo `.jpg` com o mesmo nome do `.mp4`.</td><td class="text-xs text-cyan-300">Gerar a capa JPEG ao lado do vídeo; nunca agendar sem capa (<code class="font-mono text-slate-200">scripts/verify-reel-cover.php</code>).</td></tr>
                <tr><td class="font-semibold text-rose-300">Erro de sincronização no Google Drive</td><td class="text-slate-300">Token OAuth expirado ou callback divergente.</td><td class="text-xs text-cyan-300">Acessar Backup em Nuvem no painel e reautenticar a conta Google Drive.</td></tr>
                <tr><td class="font-semibold text-rose-300">Acentos corrompidos em tarefas do Forge</td><td class="text-slate-300">Envio de JSON pelo terminal sem codificação UTF-8 explícita.</td><td class="text-xs text-cyan-300">Enviar payload JSON a partir de arquivo ou via <code class="font-mono text-slate-200">ConvertTo-Json</code> no PowerShell.</td></tr>
                <tr><td class="font-semibold text-rose-300">Capa do feed do Instagram trocada ou ausente após mexer nos IDs de instagram_posts</td><td class="text-slate-300">A sincronização salva as capas como <code class="font-mono text-slate-200">post_&lt;ID&gt;.jpg</code> e reaproveita arquivos existentes. Renumerar IDs faz as capas antigas ficarem atribuídas a outros posts.</td><td class="text-xs text-cyan-300">Nunca renumerar sem reconciliar as capas por <code class="font-mono text-slate-200">ig_media_id</code>. Conferir <code class="font-mono text-slate-200">storage/instagram-feed-publish/auto.disabled</code> antes de reativar o envio automático.</td></tr>
                <tr><td class="font-semibold text-rose-300">Reel publicado com vídeo antigo</td><td class="text-slate-300">Caminho em <code class="font-mono text-slate-200">video_rendered_path</code> divergente.</td><td class="text-xs text-cyan-300">Mover versão anterior para <code class="font-mono text-slate-200">reels/anteriores/</code> antes de sobrescrever <code class="font-mono text-slate-200">reel.mp4</code>.</td></tr>
                <tr><td class="font-semibold text-rose-300">Post excluído no Instagram ainda aparece no site</td><td class="text-slate-300">A Graph API pode manter temporariamente a mídia removida; publicações feitas pelo Instagrapi também usam um PK diferente do ID da Graph e podem gerar registro duplicado se forem conciliadas apenas pelo ID.</td><td class="text-xs text-cyan-300">Confirmar o shortcode pela API privada, cancelar logicamente apenas o registro ausente e executar <code class="font-mono text-slate-200">scripts/sync-instagram-feed-assets.php</code>. O upsert concilia registros locais pelo permalink antes de vincular o ID da Graph.</td></tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- 16. EVOLUÇÃO TÉCNICA -->
        <section id="evolucao" data-doc-panel="evolucao" class="doc-panel rounded-3xl border border-slate-800/90 bg-slate-900/85 p-6 shadow-sm" <?= $docSection === 'evolucao' ? '' : 'hidden' ?>>
          <div class="flex items-center gap-2.5 border-b border-slate-800/80 pb-4">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl border border-cyan-500/30 bg-cyan-500/10 text-cyan-300"><i class="fa-solid fa-timeline text-xs"></i></span>
            <div>
              <h2 class="font-orbitron text-xl font-bold text-white text-balance">16) Evolução Técnica do Portal</h2>
              <p class="text-xs text-slate-400">Linha do tempo consolidada de marcos e entregas estruturais.</p>
            </div>
          </div>

          <div class="mt-5 space-y-3">
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <span class="rounded bg-cyan-950 text-cyan-300 border border-cyan-800 px-2 py-0.5 font-mono text-[10px] font-bold">Outubro / 2026</span>
              <p class="mt-2 text-xs font-bold text-white">Motores de Vídeo, Otimizações & Governança de Mídia</p>
              <ul class="mt-2 space-y-1 text-xs text-slate-300">
                <li>&bull; <strong>IMP-037 & Motores:</strong> Consolidação oficial de Motionmaxxing (tipografia cinética), OneTake (Playwright) e Framefields (WebGPU); descontinuação do Konva.</li>
                <li>&bull; <strong>IMP-035 & IMP-036:</strong> Suporte a múltiplos Reels por post de blog e busca/paginação na biblioteca local de áudios.</li>
                <li>&bull; <strong>IMP-034:</strong> Cache de pacotes de conteúdo na Central Operacional (`content-sync-packages.json`).</li>
                <li>&bull; <strong>FEAT-013:</strong> Bloco de ofertas contextuais nos posts do blog e filtro anti-scanner de SQL.</li>
                <li>&bull; <strong>IMP-033:</strong> Remoção definitiva do Dropbox em favor do Google Drive API.</li>
              </ul>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <span class="rounded bg-slate-800 text-slate-300 px-2 py-0.5 font-mono text-[10px] font-bold">Setembro / 2026</span>
              <p class="mt-2 text-xs font-bold text-white">Automação Meta, Backup Diário & Qualidade</p>
              <ul class="mt-2 space-y-1 text-xs text-slate-300">
                <li>&bull; <strong>FEAT-010:</strong> Módulo Instagram Graph API v21.0 local-only, fila agendada e publicação atômica.</li>
                <li>&bull; <strong>Rotina Diária de Backup:</strong> Windows Task Scheduler às 21:30 e prova de restauração em sandbox temporário (`verify-backup-restore.php`).</li>
                <li>&bull; <strong>Qualidade:</strong> PHPStan nível 5 obrigatório e suíte `verify-changes.php`.</li>
              </ul>
            </div>
          </div>
        </section>

        <!-- 17. GOVERNANÇA DA DOCUMENTAÇÃO -->
        <section id="governanca" data-doc-panel="governanca" class="doc-panel rounded-3xl border border-fuchsia-500/25 bg-slate-900/85 p-6 shadow-sm" <?= $docSection === 'governanca' ? '' : 'hidden' ?>>
          <div class="flex items-center gap-2.5 border-b border-fuchsia-500/20 pb-4">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl border border-fuchsia-500/30 bg-fuchsia-500/10 text-fuchsia-300"><i class="fa-solid fa-book-bookmark text-xs"></i></span>
            <div>
              <h2 class="font-orbitron text-xl font-bold text-fuchsia-100 text-balance">17) Governança da Documentação Central</h2>
              <p class="text-xs text-slate-400">Regra máxima: alteração sem documentação atualizada é uma alteração incompleta.</p>
            </div>
          </div>

          <div class="mt-4 rounded-2xl border border-fuchsia-500/30 bg-fuchsia-950/20 p-4 text-xs text-fuchsia-100">
            <p class="font-bold text-fuchsia-200">Guia Obrigatório para Qualquer IA ou Desenvolvedor</p>
            <p class="mt-1">Consulte sempre <code class="font-mono text-white">docs/governanca/documentacao-central.md</code> (definido na tarefa Forge #1253). Ele especifica qual arquivo alimenta cada área da Central Técnica.</p>
            <ol class="mt-3 list-decimal space-y-1 pl-4 text-slate-300">
              <li>Atualizar a seção correspondente desta documentação na mesma tarefa da alteração.</li>
              <li>Revisar compatibilidade com <code class="font-mono text-slate-200">docs/CODEX-REGRAS-PERMANENTES.txt</code>.</li>
              <li>Rodar a suíte <code class="font-mono text-slate-200">verify-changes.php</code> e conferir a página renderizada antes de concluir.</li>
              <li>Registrar e concluir tarefas exclusivamente no painel local <strong>The Forge</strong>.</li>
            </ol>
          </div>
        </section>

        <!-- Rodapé com Navegação Sequencial (← Anterior / Próxima →) -->
        <div class="flex items-center justify-between border-t border-slate-800 pt-4" data-doc-pager>
          <div>
            <?php if ($prevSection !== null): ?>
              <a href="<?= htmlspecialchars($docsSectionUrl($prevSection), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-pager-prev class="inline-flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-900/80 px-4 py-2 text-xs font-semibold text-slate-300 transition hover:border-cyan-400/40 hover:text-white">
                <i class="fa-solid fa-arrow-left text-[10px]"></i>
                <span data-pager-prev-label><?= htmlspecialchars($docSections[$prevSection] ?? 'Anterior', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
              </a>
            <?php else: ?>
              <span class="inline-block"></span>
            <?php endif; ?>
          </div>
          <div>
            <?php if ($nextSection !== null): ?>
              <a href="<?= htmlspecialchars($docsSectionUrl($nextSection), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-pager-next class="inline-flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-900/80 px-4 py-2 text-xs font-semibold text-slate-300 transition hover:border-cyan-400/40 hover:text-white">
                <span data-pager-next-label><?= htmlspecialchars($docSections[$nextSection] ?? 'Próxima', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
              </a>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>
  </div>

  <script>
    (() => {
      const sectionKeys = <?= json_encode($allSectionKeys, JSON_UNESCAPED_SLASHES) ?>;
      const sectionLabels = <?= json_encode($docSections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      const nav = document.querySelector('[data-doc-nav]');
      const mobileSelect = document.getElementById('docSectionSelect');
      const triggers = nav ? Array.from(nav.querySelectorAll('[data-doc-trigger]')) : [];
      const panels = Array.from(document.querySelectorAll('[data-doc-panel]'));
      const prevBtn = document.querySelector('[data-pager-prev]');
      const nextBtn = document.querySelector('[data-pager-next]');
      const prevLabel = document.querySelector('[data-pager-prev-label]');
      const nextLabel = document.querySelector('[data-pager-next-label]');

      const updatePager = (target) => {
        const idx = sectionKeys.indexOf(target);
        if (idx === -1) return;

        if (prevBtn) {
          if (idx > 0) {
            const pKey = sectionKeys[idx - 1];
            prevBtn.style.display = 'inline-flex';
            prevBtn.dataset.docTrigger = pKey;
            prevBtn.href = '?docs_secao=' + encodeURIComponent(pKey);
            if (prevLabel) prevLabel.textContent = sectionLabels[pKey] || 'Anterior';
          } else {
            prevBtn.style.display = 'none';
          }
        }

        if (nextBtn) {
          if (idx < sectionKeys.length - 1) {
            const nKey = sectionKeys[idx + 1];
            nextBtn.style.display = 'inline-flex';
            nextBtn.dataset.docTrigger = nKey;
            nextBtn.href = '?docs_secao=' + encodeURIComponent(nKey);
            if (nextLabel) nextLabel.textContent = sectionLabels[nKey] || 'Próxima';
          } else {
            nextBtn.style.display = 'none';
          }
        }
      };

      const activate = (target, push = true) => {
        triggers.forEach((trigger) => {
          const active = trigger.dataset.docTrigger === target;
          trigger.classList.toggle('border-cyan-400/50', active);
          trigger.classList.toggle('bg-cyan-500/15', active);
          trigger.classList.toggle('text-cyan-100', active);
          trigger.classList.toggle('shadow-[0_0_18px_rgba(34,211,238,0.12)]', active);
          trigger.classList.toggle('border-slate-800/70', !active);
          trigger.classList.toggle('bg-slate-950/50', !active);
          trigger.classList.toggle('text-slate-300', !active);
          trigger.setAttribute('aria-current', active ? 'page' : 'false');
          const icon = trigger.querySelector('i');
          if (icon) {
            icon.classList.toggle('text-cyan-300', active);
            icon.classList.toggle('text-slate-500', !active);
          }
        });

        panels.forEach((panel) => {
          panel.hidden = panel.dataset.docPanel !== target;
        });

        if (mobileSelect && mobileSelect.value !== target) {
          mobileSelect.value = target;
        }

        updatePager(target);

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

      if (mobileSelect) {
        mobileSelect.addEventListener('change', () => {
          activate(mobileSelect.value || 'identidade', true);
        });
      }

      [prevBtn, nextBtn].forEach((btn) => {
        if (!btn) return;
        btn.addEventListener('click', (event) => {
          event.preventDefault();
          const target = btn.dataset.docTrigger;
          if (target) {
            activate(target, true);
            window.scrollTo({ top: 0, behavior: 'smooth' });
          }
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
