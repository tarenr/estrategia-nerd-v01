<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Site\LocalDocsController;
use App\Support\View;

final class TechnicalCenterController
{
    /**
     * @return array<string, array<string, string>>
     */
    private function sections(): array
    {
        return [
            'visao-geral' => [
                'label' => 'Visão Geral',
                'href' => url('/admin/central-tecnica/base-conhecimento'),
                'icon' => 'fa-solid fa-gauge-high',
                'status' => 'Disponível',
                'tone' => 'success',
            ],
            'backlog' => [
                'label' => 'Backlog',
                'href' => url('/admin/central-tecnica/base-conhecimento/backlog'),
                'icon' => 'fa-solid fa-list-check',
                'status' => 'Disponível',
                'tone' => 'success',
            ],
            'documentacao' => [
                'label' => 'Documentação Técnica',
                'href' => url('/admin/central-tecnica/base-conhecimento/documentacao'),
                'icon' => 'fa-solid fa-book-open',
                'status' => 'Disponível',
                'tone' => 'success',
            ],
            'regras' => [
                'label' => 'Regras de Negócio',
                'href' => url('/admin/central-tecnica/base-conhecimento/regras'),
                'icon' => 'fa-solid fa-scale-balanced',
                'status' => 'Disponível',
                'tone' => 'success',
            ],
            'mudancas' => [
                'label' => 'Histórico de Mudanças',
                'href' => url('/admin/central-tecnica/base-conhecimento/mudancas'),
                'icon' => 'fa-solid fa-clock-rotate-left',
                'status' => 'Disponível',
                'tone' => 'success',
            ],
            'procedimentos' => [
                'label' => 'Procedimentos',
                'href' => url('/admin/central-tecnica/base-conhecimento/procedimentos'),
                'icon' => 'fa-solid fa-clipboard-list',
                'status' => 'Disponível',
                'tone' => 'success',
            ],
            'padroes' => [
                'label' => 'Padrões e Boas Práticas',
                'href' => url('/admin/central-tecnica/base-conhecimento/padroes'),
                'icon' => 'fa-solid fa-compass-drafting',
                'status' => 'Disponível',
                'tone' => 'success',
            ],
            'estruturas-posts' => [
                'label' => 'Estruturas de Posts',
                'href' => url('/admin/central-tecnica/base-conhecimento/estruturas-posts'),
                'icon' => 'fa-solid fa-file-code',
                'status' => 'Disponivel',
                'tone' => 'success',
            ],
            'faq' => [
                'label' => 'FAQ / Dúvidas Frequentes',
                'href' => url('/admin/central-tecnica/base-conhecimento/faq'),
                'icon' => 'fa-solid fa-circle-question',
                'status' => 'Disponível',
                'tone' => 'success',
            ],
        ];
    }

    public function index(): void
    {
        $this->baseConhecimento();
    }

    public function baseConhecimento(): void
    {
        $docs = new LocalDocsController();
        $backlog = $docs->backlogViewData(true);
        $documentation = $docs->documentationViewData(true);
        $rules = $docs->rulesViewData(true);
        $changes = $docs->changesViewData(true);

        View::render('admin/technical-center/knowledge-base', [
            'title' => 'Base de Conhecimento | Estrategia Nerd',
            'knowledge' => $this->knowledgeViewModel($backlog, $documentation, $rules, $changes),
            'sections' => $this->sections(),
        ]);
    }

    public function backlog(): void
    {
        $this->renderKnowledgeSection('backlog');
    }

    public function documentacao(): void
    {
        $this->renderKnowledgeSection('documentacao');
    }

    public function regras(): void
    {
        $this->renderKnowledgeSection('regras');
    }

    public function mudancas(): void
    {
        $this->renderKnowledgeSection('mudancas');
    }

    public function procedimentos(): void
    {
        $this->renderKnowledgeSection('procedimentos');
    }

    public function padroes(): void
    {
        $this->renderKnowledgeSection('padroes');
    }

    public function estruturasPosts(): void
    {
        $this->renderKnowledgeSection('estruturas-posts');
    }

    public function faq(): void
    {
        $this->renderKnowledgeSection('faq');
    }

    private function renderKnowledgeSection(string $section): void
    {
        $sections = $this->sections();
        $current = $sections[$section] ?? null;
        if ($current === null) {
            $this->baseConhecimento();
            return;
        }

        $docs = new LocalDocsController();
        $contentHtml = '';
        $source = 'Planejado';

        if ($section === 'backlog') {
            $contentHtml = View::fragment('site/local-backlog', $docs->backlogViewData(true));
            $source = 'Base Técnica V1 / Backlog';
        } elseif ($section === 'documentacao') {
            $contentHtml = View::fragment('site/local-docs-v2', $docs->documentationViewData(true));
            $source = 'Base Técnica V1 / Documentação';
        } elseif ($section === 'regras') {
            $contentHtml = View::fragment('site/local-rules', $docs->rulesViewData(true));
            $source = 'Base Técnica V1 / Regras';
        } elseif ($section === 'mudancas') {
            $contentHtml = View::fragment('site/local-changes', $docs->changesViewData(true));
            $source = 'Base Técnica V1 / Mudanças';
        }

        View::render('admin/technical-center/knowledge-section', [
            'title' => ($current['label'] ?? 'Base de Conhecimento') . ' | Estrategia Nerd',
            'active_section' => $section,
            'current_section' => $current,
            'sections' => $sections,
            'content_html' => $contentHtml,
            'planned_content' => $this->plannedSectionContent($section),
            'source' => $source,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function plannedSectionContent(string $section): array
    {
        return match ($section) {
            'procedimentos' => [
                'title' => 'Procedimentos Operacionais',
                'summary' => 'Runbooks e passos a passo para deploys, backups, promoção de pacotes e operação de mídia.',
                'cards' => [
                    [
                        'title' => 'Fluxo Local → Stage → Produção',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => [
                            '1. Desenvolvimento e testes no ambiente Local',
                            '2. Aplicação e validação na homologação (estrategia-nerd-stage)',
                            '3. Trava ativa: pacotes de Produção DEVEM ter origem comprovada na Stage',
                            '4. Pós-check obrigatório de rotas e restauração de paridade',
                        ],
                    ],
                    [
                        'title' => 'Backups Diários e Retenção',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => [
                            'Rotina agendada diária às 21:30 (EstrategiaNerd-BackupDiario chamando Invoke-DailyBackup.ps1)',
                            'Ambiente local: roda com --no-uploads de seg a sáb (já no restic geral) e completo aos domingos',
                            'Stage e Produção: sempre backups completos diários de banco + uploads + arquivos de sistema',
                            'Retenção oficial: 7 cópias por ambiente em C:\Users\WINDOWS\ProjectBackup\estrategia-nerd',
                            'Sincronização em nuvem consolidada no Google Drive (IMP-023 / IMP-033)',
                        ],
                    ],
                    [
                        'title' => 'Prova de Restauração em Sandbox',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => [
                            'Comando oficial: php scripts/verify-backup-restore.php <local|stage|production> [BACKUP_ID]',
                            'Checa integridade de SHA-256 do manifesto e CRC de todos os arquivos nos zips',
                            'Importa o database.sql em banco temporário isolado (en_restore_check_*), descartado no final',
                            'Zero risco: prova a restaurabilidade sem sobrescrever bases reais em uso',
                        ],
                    ],
                    [
                        'title' => 'Deploy Técnico & Cache de Pacotes',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => [
                            'Gerar pacote técnico exclusivamente a partir da Stage validada',
                            'Otimização IMP-034: cache de leitura em storage/cache/content-sync-packages.json (TTL 5 min)',
                            'Acompanhar aplicação pelo progresso em tempo real do modal da Central Operacional',
                            'Conferir destino e horário no bloco "Últimos deploys técnicos"',
                        ],
                    ],
                    [
                        'title' => 'Revisão & Múltiplos Reels por Post',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => [
                            'Suporte a múltiplos Reels por artigo (IMP-035); todos listados no editor via findLinkedReels',
                            'Padrão de arquivos para múltiplos: public/uploads/posts/<slug>/reels/reel-<id>/ (IMP-037)',
                            'Rascunhos revisados DEVEM retornar para agendado com render_status = ready',
                            'Capas de artigos: conversão obrigatória para WebP (~150-250 KB)',
                        ],
                    ],
                    [
                        'title' => 'Publicação Agendada do Instagram',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => [
                            'Cron a cada 5 min (EstrategiaNerd-InstagramPublicarAgendados) roda scripts/en-instagram-publish-scheduled.php',
                            'Grade oficial de publicação: 12:00 e 19:30',
                            'Lock atômico (agendado -> publicando) e confirmação na Meta antes de marcar como publicado',
                            'Conferir logs em storage/logs/instagram/publish-AAAA-MM.log',
                        ],
                    ],
                    [
                        'title' => 'Troca de Vídeo de Reel Agendado',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => [
                            'Antes de trocar: mover reel.mp4, reel.jpg e capa.jpg para reels/anteriores/ com data e hash',
                            'Copiar o novo vídeo e capa com os nomes canônicos reel.mp4 e capa.jpg (sem alterar caminhos no banco)',
                            'Validar com readyVideoPath, reelCoverParams e suíte verify-changes.php',
                            'Rollback garantido: restaurar os arquivos originais de reels/anteriores/',
                        ],
                    ],
                    [
                        'title' => 'Produção com Motores Modernos de Vídeo',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => [
                            'Motionmaxxing (Oficial): tipografia cinética em GSAP/Chrome Headless para artigos do blog',
                            'OneTake: planos-sequência contínuos via Playwright em Python com motion blur (shutter 180°)',
                            'Framefields: composição e gráficos acelerados nativamente na GPU via WebGPU/Dawn',
                            'Área segura estrita: texto entre Y 170 e Y 1540; lateral X 925 na coluna de botões',
                        ],
                    ],
                    [
                        'title' => 'Produzir um Vídeo da Série do Echo',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => [
                            'Molde completo: docs/governanca/molde-serie-echo.md (abre em Mudanças → governanca); skill video-echo-serie',
                            '1. Roteiro em texto cena a cena (tempo, frase, cenário, ação do Echo, texto na tela) → parar e esperar "Aprovado"',
                            '2. Voz em uma take contínua; tempo de cada palavra pelo Whisper; vale o que a voz diz',
                            '3. Prancha com quadros reais dos cenários antes de animar; cenas novas não copiam o começo do vídeo',
                            '4. Animação em blocos (um por conversa do Antigravity), linha de base congelada e conferência do Claude Code',
                            '5. Prévia com voz → legenda e som (escolhas do vídeo, com o usuário) → prévia final → versão 1080×1920 → capa → agendamento',
                            '6. Fim do vídeo (agendado ou publicado): pacote de origem em conteudo-planejado/videos-echo/<nome>/, testado, e apagar o lixo (_frames, seg_*, prévias superadas, perfis de navegador)',
                            'Só "Aprovado" aprova; "ok", "sim", "pode" não aprovam',
                        ],
                    ],
                    [
                        'title' => 'Agendar um Reel na Fila do Instagram',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => [
                            'Arquivos em public/uploads/reels/<nome>/ como reel.mp4 e capa.jpg (capa JPEG obrigatória); skill agendar-reel-instagram',
                            'Consultar a fila do dia (somente leitura) antes; slots 12:00 e 19:30 ocupados = escolher outro horário com o usuário',
                            'Criar via InstagramPostRepository: create (agendado, reels) → addMedia → markRenderedReady; checar duplicidade e conflito',
                            'Endereço público SEM /public: https://nerd.tfr-info.com.br/uploads/reels/<nome>/ (com /public o Cloudflare pede login)',
                            'PC ligado e túnel no ar no horário; o publicador roda a cada 5 min',
                        ],
                    ],
                    [
                        'title' => 'Atualização da Documentação Central',
                        'status' => 'Regra Ativa',
                        'tone' => 'success',
                        'items' => [
                            'Toda alteração funcional exige atualização da documentação central na mesma tarefa',
                            'Guia obrigatório: docs/governanca/documentacao-central.md',
                            'Validar sempre nas rotas renderizadas e registrar/concluir tarefas exclusivamente no Forge',
                        ],
                    ],
                ],
                'copy_blocks' => [
                    [
                        'title' => 'Prompt mestre para criar post',
                        'description' => 'Use este bloco em outra IA antes de pedir o artigo.',
                        'content' => <<<'TEXT'
Voce e um redator do portal Estrategia Nerd.
Crie um post em portugues do Brasil, com tom editorial claro, nerd, util e sem exagero promocional.

Regras:
- Entregue o corpo em HTML pronto para colar no editor do site.
- Use apenas estruturas simples: h2, h3, p, ul, ol, li, strong, em, table, thead, tbody, tr, th, td, blockquote.
- Nao use CSS inline, scripts, iframes, divs complexas ou classes novas.
- Use paragrafos curtos.
- Inclua um resumo inicial.
- Inclua pelo menos uma secao de pontos principais.
- Inclua FAQ quando fizer sentido.
- Preserve uma opiniao editorial honesta.
- Quando citar produto, jogo, app, servico ou ferramenta, explique para quem serve e para quem nao serve.
- Nao invente dados, precos, datas, especificacoes ou fontes. Se faltar informacao, marque como "verificar antes de publicar".

Tema do post:
[COLE AQUI O TEMA]

Objetivo do post:
[INFORMAR: noticia, review, guia, comparativo, lista, opiniao ou afiliado futuro]

Publico:
[INFORMAR: iniciante, intermediario, gamer, leitor casual, criador, comprador etc.]
TEXT,
                    ],
                    [
                        'title' => 'Modelo de noticia',
                        'description' => 'Para novidades, anuncios e atualizacoes.',
                        'content' => <<<'TEXT'
<p><strong>Resumo rapido:</strong> [explique a novidade em 2 ou 3 linhas, sem enrolar].</p>

<h2>O que aconteceu</h2>
<p>[conte o fato principal com contexto].</p>

<h2>Por que isso importa</h2>
<ul>
  <li><strong>[Ponto 1]:</strong> [impacto para o publico].</li>
  <li><strong>[Ponto 2]:</strong> [impacto tecnico, cultural ou pratico].</li>
  <li><strong>[Ponto 3]:</strong> [o que muda daqui para frente].</li>
</ul>

<h2>O que ainda precisa ser confirmado</h2>
<p>[liste datas, precos, disponibilidade, plataformas ou detalhes que precisam de verificacao].</p>

<h2>Leitura editorial</h2>
<p>[opinião curta do Estrategia Nerd, com cuidado para nao prometer demais].</p>
TEXT,
                    ],
                    [
                        'title' => 'Modelo de review',
                        'description' => 'Para jogos, apps, produtos, cursos ou ferramentas.',
                        'content' => <<<'TEXT'
<p><strong>Resumo rapido:</strong> [diga se vale a pena, para quem e por que].</p>

<h2>Visao geral</h2>
<p>[apresente o item avaliado e o contexto de uso].</p>

<h2>Pontos principais</h2>
<ul>
  <li><strong>Melhor ponto:</strong> [explique].</li>
  <li><strong>Ponto de atencao:</strong> [explique].</li>
  <li><strong>Para quem faz sentido:</strong> [explique].</li>
</ul>

<h2>Pros e contras</h2>
<table>
  <thead>
    <tr>
      <th>Pros</th>
      <th>Contras</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>[beneficio real]</td>
      <td>[limitacao real]</td>
    </tr>
  </tbody>
</table>

<h2>Veredito</h2>
<p>[feche com recomendacao honesta e objetiva].</p>
TEXT,
                    ],
                    [
                        'title' => 'Modelo de guia',
                        'description' => 'Para tutoriais e conteudos educativos.',
                        'content' => <<<'TEXT'
<p><strong>Resumo rapido:</strong> [explique o que o leitor vai aprender].</p>

<h2>Antes de comecar</h2>
<ul>
  <li><strong>Nivel:</strong> [iniciante, intermediario ou avancado].</li>
  <li><strong>Tempo estimado:</strong> [tempo].</li>
  <li><strong>O que voce precisa:</strong> [lista de requisitos].</li>
</ul>

<h2>Passo a passo</h2>
<ol>
  <li><strong>[Passo 1]:</strong> [explique com clareza].</li>
  <li><strong>[Passo 2]:</strong> [explique com clareza].</li>
  <li><strong>[Passo 3]:</strong> [explique com clareza].</li>
</ol>

<h2>Erros comuns</h2>
<ul>
  <li>[erro comum e como evitar].</li>
  <li>[erro comum e como evitar].</li>
</ul>

<h2>Conclusao</h2>
<p>[resuma o ganho pratico do leitor].</p>
TEXT,
                    ],
                    [
                        'title' => 'Modelo de comparativo',
                        'description' => 'Para escolher entre produtos, servicos, jogos ou ferramentas.',
                        'content' => <<<'TEXT'
<p><strong>Resumo rapido:</strong> [diga qual opcao vence em cada caso].</p>

<h2>Comparativo rapido</h2>
<table>
  <thead>
    <tr>
      <th>Opcao</th>
      <th>Melhor para</th>
      <th>Ponto forte</th>
      <th>Ponto fraco</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>[Opcao A]</td>
      <td>[perfil]</td>
      <td>[forca]</td>
      <td>[limitacao]</td>
    </tr>
    <tr>
      <td>[Opcao B]</td>
      <td>[perfil]</td>
      <td>[forca]</td>
      <td>[limitacao]</td>
    </tr>
  </tbody>
</table>

<h2>Qual escolher?</h2>
<ul>
  <li><strong>Escolha [Opcao A] se:</strong> [criterio].</li>
  <li><strong>Escolha [Opcao B] se:</strong> [criterio].</li>
</ul>

<h2>Veredito editorial</h2>
<p>[recomendacao final com ressalvas].</p>
TEXT,
                    ],
                    [
                        'title' => 'Modelo afiliado futuro',
                        'description' => 'Para posts com potencial comercial, sem perder autoridade editorial.',
                        'content' => <<<'TEXT'
<p><strong>Resumo rapido:</strong> [explique o produto/servico e para quem ele faz sentido].</p>

<h2>Para quem vale a pena</h2>
<ul>
  <li>[perfil de leitor que se beneficia].</li>
  <li>[situacao de uso real].</li>
</ul>

<h2>Para quem nao vale</h2>
<ul>
  <li>[perfil que deve evitar].</li>
  <li>[limitacao, custo, complexidade ou alternativa melhor].</li>
</ul>

<h2>Pontos antes de comprar</h2>
<ul>
  <li><strong>Preco:</strong> verificar antes de publicar.</li>
  <li><strong>Disponibilidade:</strong> verificar antes de publicar.</li>
  <li><strong>Compatibilidade:</strong> verificar antes de publicar.</li>
</ul>

<h2>Conclusao editorial</h2>
<p>[recomendacao honesta, sem promessa exagerada].</p>

<p><em>Nota editorial:</em> se houver link de afiliado, informar de forma transparente conforme a politica do site.</p>
TEXT,
                    ],
                ],
            ],
            'padroes' => [
                'title' => 'Padrões e Boas Práticas',
                'summary' => 'Padrões técnicos de engenharia, tipografia, nomenclaturas e diretrizes de mídia.',
                'cards' => [
                    [
                        'title' => 'Interface Admin & Qualidade',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => [
                            'Análise estática obrigatória: PHPStan Nível 5 com zero erros tolerados',
                            'Suíte de testes pré-commit: C:\xampp\php\php.exe scripts/verify-changes.php',
                            'Degradação graciosa: falha em módulo secundário ou API externa nunca derruba o Dashboard',
                            'Declaração estrita: todo arquivo PHP inicia com declare(strict_types=1);',
                        ],
                    ],
                    [
                        'title' => 'Padrões de Engenharia de Vídeo',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => [
                            'Motionmaxxing (Oficial): tipografia cinética em GSAP com deriva lenta e fontes temáticas',
                            'OneTake: planos contínuos sem cortes secos via Playwright/Python com obturador de 180°',
                            'Framefields: shaders, 3D e BeatGrid acelerados nativamente na GPU via WebGPU/Dawn',
                            'Konva/Canvas legado: formalmente descontinuado para novas publicações',
                        ],
                    ],
                    [
                        'title' => 'Padrões de Mídia e Otimização',
                        'status' => 'Regra Ativa',
                        'tone' => 'success',
                        'items' => [
                            'Capas de artigos sempre em WebP otimizado (~150-250 KB), nunca PNGs pesados de 2-3MB',
                            'Vídeos dos Reels em H.264 + AAC, 1080x1920, 30 fps',
                            'Capa JPEG (.jpg) obrigatória ao lado de todo vídeo .mp4 (mesmo nome ou capa.jpg)',
                        ],
                    ],
                    [
                        'title' => 'Biblioteca Local de Trilhas (IMP-036)',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => [
                            'Busca em tempo real por título, artista e gênero no modal de edição',
                            'Seletor de paginação para 5, 10, 20 ou 50 faixas por tela',
                            'Controle de volume e pré-escuta integrados no navegador',
                        ],
                    ],
                    [
                        'title' => 'Área Segura do Texto nos Reels',
                        'status' => 'Regra Ativa',
                        'tone' => 'success',
                        'items' => [
                            'Texto somente entre Y 170 e Y 1540 (altura segura vertical)',
                            'Coluna de botões do Instagram: texto restrito até X 925 na faixa Y 970–1790; margem X 72',
                            'Imagem de fundo pode ficar sob os botões; apenas o texto exige área segura',
                        ],
                    ],
                    [
                        'title' => 'Zip de Sistema Enxuto no Backup',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => [
                            'system-files.zip exclui pastas transitórias (scratch/, tmp/, .codegraph/, notion-import/)',
                            'Mantém o pacote compacto em cerca de 25 MB (em vez de >500 MB)',
                        ],
                    ],
                    [
                        'title' => 'Nomes e Camadas (MVC)',
                        'status' => 'Regra Ativa',
                        'tone' => 'success',
                        'items' => [
                            'Classes em PascalCase, métodos em camelCase',
                            'Sufixos aceitos em app/Services: Service, Renderer, Publisher, Presenter, Reconciler',
                            'SQL concentrado exclusivamente na camada de Repository',
                        ],
                    ],
                    [
                        'title' => 'Série do Echo: o que é igual em todo vídeo',
                        'status' => 'Regra Ativa',
                        'tone' => 'success',
                        'items' => [
                            'Echo pelo motor oficial: sem braços e sem pernas, flutua; a boca acompanha a voz em toda a fala',
                            'Vertical 1080×1920 e área segura de texto dos Reels medida (Y 170–1540, X 72–1008, X ≤ 925 em Y 970–1790)',
                            'Aprovação em cada etapa (roteiro, desenho, prévias, final, capa, agendamento)',
                            'Conferências: hash do que não muda, 0 erros de página, quadros olhados, emendas, -16 LUFS / -1,5 dBTP',
                            'Fonte e posição da legenda, música, cenários e capa NÃO são padrão: decidir em cada vídeo com o usuário',
                        ],
                    ],
                    [
                        'title' => 'Série do Echo: nível de detalhe de cenário e animação',
                        'status' => 'Regra Ativa',
                        'tone' => 'success',
                        'items' => [
                            'Cenário em Canvas 2D com 6+ camadas (céu, distante, médio, estrutura, chão, frente, luz, acabamento) e semente fixa',
                            'Objetos ligados ao assunto, textura em 2+ materiais, uma fonte de luz e sombra; chão e fundo nunca vazios',
                            'Lugar do Echo reservado, com contato (sombra/reflexo); Echo grande o bastante para ler a expressão',
                            'Cada frase da voz tem uma ação; câmera trabalha (aproximação, recuo, zoom); transições com sentido, sem tela colada',
                            'Nada parado mais de ~1,4 s; variar enquadramento ou cena a cada ~6 s; objetos não somem todos de uma vez',
                            'Contraexemplos e prancha dos aprovados: docs/governanca/molde-serie-echo.md, seção 4',
                        ],
                    ],
                ],
            ],
            'estruturas-posts' => [
                'title' => 'Estruturas de Posts',
                'summary' => 'Referência rápida para criar posts no editor mantendo o padrão visual e editorial do site.',
                'cards' => [
                    [
                        'title' => 'Texto base',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => ['H2 e H3 para hierarquia', 'Parágrafos curtos', 'Listas quando ajudam a leitura'],
                    ],
                    [
                        'title' => 'Blocos editoriais',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => ['Destaque', 'Nota', 'Ponto positivo', 'Atenção', 'CTA'],
                    ],
                    [
                        'title' => 'Comparativos',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => ['Tabela', 'Prós e contras', 'Resumo rápido', 'FAQ'],
                    ],
                    [
                        'title' => 'Reviews',
                        'status' => 'Referência',
                        'tone' => 'info',
                        'items' => ['Visão geral', 'Pontos principais', 'Prós e contras', 'Veredito'],
                    ],
                    [
                        'title' => 'Guias',
                        'status' => 'Referência',
                        'tone' => 'info',
                        'items' => ['Nível', 'O que vai aprender', 'Passo a passo', 'Dicas finais'],
                    ],
                    [
                        'title' => 'Mídias suportadas',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => ['Imagem WebP com legenda', 'Vídeo incorporado', 'Áudio editorial', 'Tabela normalizada'],
                    ],
                    [
                        'title' => 'O que evitar',
                        'status' => 'Regra',
                        'tone' => 'warning',
                        'items' => ['CSS inline sem necessidade', 'Classes novas fora do padrão', 'Wrappers manuais sem documentação'],
                    ],
                    [
                        'title' => 'Referência completa',
                        'status' => 'Local',
                        'tone' => 'neutral',
                        'items' => ['docs/blog-estruturas-de-conteudo.html', '/local/blog-estruturas'],
                    ],
                ],
            ],
            'faq' => [
                'title' => 'FAQ / Dúvidas Frequentes',
                'summary' => 'Perguntas e respostas diretas sobre operação, backups, vídeos, deploy e governança.',
                'cards' => [
                    [
                        'title' => 'Por que a Central Operacional estava lenta ao listar pacotes?',
                        'status' => 'Respondido',
                        'tone' => 'success',
                        'items' => [
                            'Causa: leitura e cálculo repetido de dezenas de zips no disco externo de backup.',
                            'Solução (IMP-034): cache automático em storage/cache/content-sync-packages.json (TTL 5 min).',
                            'Para forçar a releitura imediata: basta apagar o arquivo de cache ou gerar um novo pacote.',
                        ],
                    ],
                    [
                        'title' => 'Um post do blog pode ter mais de um Reel vinculado?',
                        'status' => 'Respondido',
                        'tone' => 'success',
                        'items' => [
                            'Sim! Desde o IMP-035 a tabela instagram_posts suporta múltiplos Reels para o mesmo post_blog_id.',
                            'A tela de edição do post (/admin/editar-post) lista todos os Reels vinculados via findLinkedReels.',
                            'Múltiplos Reels usam pastas padronizadas em reels/reel-<id>/ (IMP-037).',
                        ],
                    ],
                    [
                        'title' => 'Qual motor de renderização usar para novos Reels?',
                        'status' => 'Respondido',
                        'tone' => 'success',
                        'items' => [
                            'Motionmaxxing (Oficial): padrão para conteúdo editorial e artigos com tipografia cinética.',
                            'OneTake: indicado para teasers, apresentações contínuas e anúncios em plano-sequência.',
                            'Framefields: para gráficos avançados e sincronia musical diretamente na GPU.',
                            'O motor Konva legado foi formalmente aposentado.',
                        ],
                    ],
                    [
                        'title' => 'O Dropbox ainda é utilizado para backups em nuvem?',
                        'status' => 'Respondido',
                        'tone' => 'success',
                        'items' => [
                            'Não. No IMP-023 o Google Drive foi adotado e no IMP-033 o Dropbox foi totalmente removido do código e telas.',
                            'O Google Drive é o destino exclusivo e oficial de nuvem do portal.',
                        ],
                    ],
                    [
                        'title' => 'Como provar que um backup é restaurável sem risco?',
                        'status' => 'Respondido',
                        'tone' => 'success',
                        'items' => [
                            'Execute: php scripts/verify-backup-restore.php <ambiente> [BACKUP_ID].',
                            'Ele testa SHA-256, CRC de todos os zips e importa o banco em uma base temporária isolada apagada no fim.',
                        ],
                    ],
                    [
                        'title' => 'Posso subir alterações direto do ambiente Local para Produção?',
                        'status' => 'Respondido',
                        'tone' => 'success',
                        'items' => [
                            'Não! Bloqueio absoluto do sistema (CONTENT_SYNC_APPROVED_PACKAGE_SOURCE).',
                            'O fluxo oficial é estritamente: Local → Stage → Produção.',
                        ],
                    ],
                    [
                        'title' => 'Como trocar o vídeo de um Reel já agendado?',
                        'status' => 'Respondido',
                        'tone' => 'success',
                        'items' => [
                            'Mover o vídeo e capa atuais para reels/anteriores/ (com data e hash para rollback).',
                            'Copiar o novo vídeo e capa mantendo os nomes canônicos reel.mp4 e capa.jpg.',
                            'Validar com readyVideoPath, reelCoverParams e suíte verify-changes.php.',
                        ],
                    ],
                    [
                        'title' => 'Onde ficam as tarefas oficiais do projeto?',
                        'status' => 'Respondido',
                        'tone' => 'success',
                        'items' => [
                            'Somente no painel local The Forge (http://127.0.0.1:4477), projeto Estratégia Nerd.',
                            'Itens de baixa prioridade ficam em docs/governanca/backlog-baixa-prioridade.md.',
                        ],
                    ],
                    [
                        'title' => 'Acentos corrompidos em tarefas do Forge?',
                        'status' => 'Respondido',
                        'tone' => 'success',
                        'items' => [
                            'Envie o JSON a partir de arquivo ou via ConvertTo-Json no PowerShell com UTF-8 explícito.',
                        ],
                    ],
                    [
                        'title' => 'Por que a legenda em woff2 falha no ffmpeg?',
                        'status' => 'Respondido',
                        'tone' => 'success',
                        'items' => [
                            'O ffmpeg desta máquina só lê fontes .ttf/.otf; com woff2 (Orbitron, por exemplo) o drawtext/libass falha.',
                            'Solução usada no Manifesto: desenhar a legenda no navegador como PNGs transparentes e sobrepor com ffmpeg.',
                            'A Press Start 2P que existe localmente está incompleta (2 KB, sem letras): fonte pixel exige download aprovado.',
                        ],
                    ],
                    [
                        'title' => 'Por que o vídeo não chega ao celular pelo chat da IA?',
                        'status' => 'Respondido',
                        'tone' => 'success',
                        'items' => [
                            'Arquivos acima de 30 MB não são entregues ao celular; o vídeo final 1080×1920 de 60 s tem ~40 MB.',
                            'Enviar a prévia 540×960 (mesmo conteúdo) para revisão no celular.',
                        ],
                    ],
                    [
                        'title' => 'O que guardar e o que apagar depois que o vídeo é publicado?',
                        'status' => 'Respondido',
                        'tone' => 'success',
                        'items' => [
                            'Guardar na pasta do post: conteudo-planejado/videos-echo/<nome>/ (versionada) com composição, cenários, fontes, voz, mixagem, roteiro, legenda, scripts, pranchas e README.',
                            'O vídeo e a capa publicados ficam em public/uploads/reels/<nome>/ e nunca são apagados (o publicador e o site usam).',
                            'Apagar: quadros PNG (_frames), pedaços de render (seg_*), prévias superadas e perfis de navegador; a pasta de trabalho inteira só depois do post publicado.',
                            'Antes de apagar: testar o pacote (abre e gera quadros idênticos) e conferir os vídeos finais. Regra completa: molde-serie-echo.md, seção 11.',
                        ],
                    ],
                    [
                        'title' => 'Render de vídeo travou ou perdeu os quadros?',
                        'status' => 'Respondido',
                        'tone' => 'success',
                        'items' => [
                            'Sem ?offset= a composição do Manifesto começa em 10 s: passar sempre ?offset=0 ou o início do segmento.',
                            'O render.py do OneTake apaga a pasta _frames ao lado da saída: renderizar numa subpasta própria.',
                            'Comandos de render acima de 150 s: dividir em segmentos de 5 s e juntar (render_v12_janela.py).',
                            'Scripts de teste sempre com prazo e abrindo o HTML de dentro da pasta do projeto (um teste sem prazo travou 50 min).',
                        ],
                    ],
                ],
            ],
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $backlog
     * @param array<string, mixed> $documentation
     * @param array<string, mixed> $rules
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function knowledgeViewModel(array $backlog, array $documentation, array $rules, array $changes): array
    {
        $featureDocs = is_array($changes['feature_docs'] ?? null) ? $changes['feature_docs'] : [];
        $releaseDocs = is_array($changes['release_docs'] ?? null) ? $changes['release_docs'] : [];
        $changeDocs = is_array($changes['change_docs'] ?? null) ? $changes['change_docs'] : [];
        $activityLogs = is_array($changes['activity_logs'] ?? null) ? $changes['activity_logs'] : [];
        $operationLogs = is_array($changes['operation_logs'] ?? null) ? $changes['operation_logs'] : [];
        $rulesBody = trim((string) ($rules['rules_body'] ?? ''));

        return [
            'facts' => [
                ['label' => 'Modo', 'value' => 'Somente Leitura'],
                ['label' => 'Origem', 'value' => 'Base Técnica V1'],
                ['label' => 'Versão', 'value' => (string) ($documentation['project_version'] ?? 'local')],
                ['label' => 'Última Leitura', 'value' => date('d/m/Y H:i:s')],
            ],
            'cards' => [
                [
                    'label' => 'Backlog',
                    'value' => 'No Forge',
                    'hint' => 'Tarefas somente no Forge; baixa prioridade em docs/governanca',
                    'icon' => 'fa-solid fa-list-check',
                    'tone' => 'info',
                    'href' => url('/admin/central-tecnica/base-conhecimento/backlog'),
                ],
                [
                    'label' => 'Documentação Técnica',
                    'value' => 'Disponível',
                    'hint' => 'Arquitetura, fluxos e governança',
                    'icon' => 'fa-solid fa-book-open',
                    'tone' => 'success',
                    'href' => url('/admin/central-tecnica/base-conhecimento/documentacao'),
                ],
                [
                    'label' => 'Regras de Negócio',
                    'value' => $rulesBody !== '' ? 'Disponível' : 'Pendente',
                    'hint' => $rulesBody !== '' ? 'Regras permanentes carregadas' : 'Arquivo de regras não encontrado',
                    'icon' => 'fa-solid fa-scale-balanced',
                    'tone' => $rulesBody !== '' ? 'success' : 'warning',
                    'href' => url('/admin/central-tecnica/base-conhecimento/regras'),
                ],
                [
                    'label' => 'Histórico de Mudanças',
                    'value' => (string) count($changeDocs),
                    'hint' => 'Features, releases e registros técnicos',
                    'icon' => 'fa-solid fa-clock-rotate-left',
                    'tone' => count($changeDocs) > 0 ? 'success' : 'neutral',
                    'href' => url('/admin/central-tecnica/base-conhecimento/mudancas'),
                ],
            ],
            'sections' => [
                [
                    'label' => 'Visão Geral',
                    'status' => 'Disponível',
                    'tone' => 'success',
                    'description' => 'Entrada executiva da Base de Conhecimento.',
                    'href' => $this->sections()['visao-geral']['href'],
                ],
                [
                    'label' => 'Backlog',
                    'status' => 'Disponível',
                    'tone' => 'success',
                    'description' => 'Plano por fases e próximas entregas.',
                    'href' => $this->sections()['backlog']['href'],
                ],
                [
                    'label' => 'Documentação Técnica',
                    'status' => 'Disponível',
                    'tone' => 'success',
                    'description' => 'Base técnica atual reaproveitada.',
                    'href' => $this->sections()['documentacao']['href'],
                ],
                [
                    'label' => 'Regras de Negócio',
                    'status' => 'Disponível',
                    'tone' => 'success',
                    'description' => 'Regras permanentes e bloqueios operacionais.',
                    'href' => $this->sections()['regras']['href'],
                ],
                [
                    'label' => 'Histórico de Mudanças',
                    'status' => 'Disponível',
                    'tone' => 'success',
                    'description' => 'Documentos e logs recentes.',
                    'href' => $this->sections()['mudancas']['href'],
                ],
                [
                    'label' => 'Procedimentos',
                    'status' => 'Disponível',
                    'tone' => 'success',
                    'description' => 'Runbooks e passos operacionais.',
                    'href' => $this->sections()['procedimentos']['href'],
                ],
                [
                    'label' => 'Padrões e Boas Práticas',
                    'status' => 'Disponível',
                    'tone' => 'success',
                    'description' => 'Padrões visuais, técnicos e editoriais.',
                    'href' => $this->sections()['padroes']['href'],
                ],
                [
                    'label' => 'Estruturas de Posts',
                    'status' => 'Disponivel',
                    'tone' => 'success',
                    'description' => 'Modelos oficiais para corpo de artigo, blocos editoriais, midias e comparativos.',
                    'href' => $this->sections()['estruturas-posts']['href'],
                ],
                [
                    'label' => 'FAQ / Dúvidas Frequentes',
                    'status' => 'Disponível',
                    'tone' => 'success',
                    'description' => 'Respostas rápidas para operação recorrente.',
                    'href' => $this->sections()['faq']['href'],
                ],
            ],
            'summary' => [
                ['label' => 'Features recentes', 'value' => (string) count($featureDocs)],
                ['label' => 'Releases recentes', 'value' => (string) count($releaseDocs)],
                ['label' => 'Mudanças registradas', 'value' => (string) count($changeDocs)],
                ['label' => 'Atividades do sistema', 'value' => (string) count($activityLogs)],
                ['label' => 'Logs operacionais', 'value' => (string) count($operationLogs)],
                ['label' => 'Seção ativa V1', 'value' => (string) ($backlog['active_section'] ?? 'visao-geral')],
            ],
        ];
    }
}
