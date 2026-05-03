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
