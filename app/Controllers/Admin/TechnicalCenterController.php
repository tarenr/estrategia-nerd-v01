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
                'title' => 'Procedimentos',
                'summary' => 'Runbooks e passos operacionais para tarefas recorrentes do projeto.',
                'cards' => [
                    [
                        'title' => 'Fluxo Local → Stage → Produção',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => ['Corrigir localmente', 'Validar em stage', 'Publicar somente com autorização explícita'],
                    ],
                    [
                        'title' => 'Backups e Restore',
                        'status' => 'Em organização',
                        'tone' => 'warning',
                        'items' => ['Separar sistêmico de editorial', 'Registrar pacote e ambiente', 'Validar antes de qualquer restore'],
                    ],
                    [
                        'title' => 'Deploy Técnico',
                        'status' => 'Em organização',
                        'tone' => 'warning',
                        'items' => ['Gerar pacote técnico', 'Aplicar em stage', 'Promover para produção após validação'],
                    ],
                    [
                        'title' => 'Validação Pós-Mudança',
                        'status' => 'Planejado',
                        'tone' => 'neutral',
                        'items' => ['Smoke tests', 'Conferência visual', 'Registro em mudanças'],
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
<p>[opinião curta do Estrategia Nerd, com cuidado para nao prometer demais].</p>
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
                'summary' => 'Padrões técnicos, visuais e operacionais para manter consistência entre as telas.',
                'cards' => [
                    [
                        'title' => 'Interface Admin V2',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => ['Cabeçalho padronizado', 'Cards objetivos', 'Estados de leitura claros'],
                    ],
                    [
                        'title' => 'Rotinas Existentes',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => ['Não duplicar lógica', 'Usar presenters leves', 'Preservar contratos atuais'],
                    ],
                    [
                        'title' => 'Históricos e Tabelas',
                        'status' => 'Em refinamento',
                        'tone' => 'warning',
                        'items' => ['Filtros consistentes', 'Ações previsíveis', 'Status sem duplicidade'],
                    ],
                    [
                        'title' => 'Documentação',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => ['Registrar após commit validado', 'Manter vínculo com commit', 'Não documentar dado falso'],
                    ],
                ],
            ],
            'estruturas-posts' => [
                'title' => 'Estruturas de Posts',
                'summary' => 'Referencia rapida para criar posts no editor mantendo o padrao visual e editorial do site.',
                'cards' => [
                    [
                        'title' => 'Texto base',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => ['H2 e H3 para hierarquia', 'Paragrafos curtos', 'Listas quando ajudam a leitura'],
                    ],
                    [
                        'title' => 'Blocos editoriais',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => ['Destaque', 'Nota', 'Ponto positivo', 'Atencao', 'CTA'],
                    ],
                    [
                        'title' => 'Comparativos',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => ['Tabela', 'Pros e contras', 'Resumo rapido', 'FAQ'],
                    ],
                    [
                        'title' => 'Reviews',
                        'status' => 'Referencia',
                        'tone' => 'info',
                        'items' => ['Visao geral', 'Pontos principais', 'Pros e contras', 'Veredito'],
                    ],
                    [
                        'title' => 'Guias',
                        'status' => 'Referencia',
                        'tone' => 'info',
                        'items' => ['Nivel', 'O que vai aprender', 'Passo a passo', 'Dicas finais'],
                    ],
                    [
                        'title' => 'Midias suportadas',
                        'status' => 'Ativo',
                        'tone' => 'success',
                        'items' => ['Imagem com legenda', 'Video incorporado', 'Audio editorial', 'Tabela normalizada'],
                    ],
                    [
                        'title' => 'O que evitar',
                        'status' => 'Regra',
                        'tone' => 'warning',
                        'items' => ['CSS inline sem necessidade', 'Classes novas fora do padrao', 'Wrappers manuais sem documentacao'],
                    ],
                    [
                        'title' => 'Referencia completa',
                        'status' => 'Local',
                        'tone' => 'neutral',
                        'items' => ['docs/blog-estruturas-de-conteudo.html', '/local/blog-estruturas'],
                    ],
                ],
            ],
            'faq' => [
                'title' => 'FAQ / Dúvidas Frequentes',
                'summary' => 'Perguntas recorrentes sobre operação, backups, deploy e organização do admin.',
                'cards' => [
                    [
                        'title' => 'Quando documentar uma mudança?',
                        'status' => 'Respondido',
                        'tone' => 'success',
                        'items' => ['Depois da validação e do commit', 'Quando a entrega entra no fluxo oficial'],
                    ],
                    [
                        'title' => 'A V2 substitui a V1?',
                        'status' => 'Respondido',
                        'tone' => 'success',
                        'items' => ['Não no início', 'A V2 é camada paralela até validação completa'],
                    ],
                    [
                        'title' => 'Backup sistêmico e editorial são iguais?',
                        'status' => 'Respondido',
                        'tone' => 'success',
                        'items' => ['Não', 'Sistêmico cobre arquivos técnicos', 'Editorial cobre banco/conteúdo/uploads'],
                    ],
                    [
                        'title' => 'Posso subir direto para produção?',
                        'status' => 'Respondido',
                        'tone' => 'success',
                        'items' => ['Não', 'Fluxo oficial é Local → Stage → Produção'],
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
                    'value' => '7 seções',
                    'hint' => 'Planejamento técnico por fases',
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
