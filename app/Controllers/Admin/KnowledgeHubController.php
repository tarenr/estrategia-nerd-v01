<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Site\LocalDocsController;
use App\Support\Session;
use App\Support\View;

final class KnowledgeHubController
{
    private const CACHE_TTL_SECONDS = 20;

    public function index(): void
    {
        $tab = strtolower(trim((string) ($_GET['aba'] ?? 'backlog')));
        $allowedTabs = ['backlog', 'documentacao', 'regras', 'mudancas'];
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'backlog';
        }

        $tabs = [
            'backlog' => [
                'label' => 'Backlog',
                'description' => 'Plano de evolucao tecnica por fases.',
                'view' => 'site/local-backlog',
            ],
            'documentacao' => [
                'label' => 'Documentacao',
                'description' => 'Base tecnica, arquitetura e fluxo oficial.',
                'view' => 'site/local-docs-v2',
            ],
            'regras' => [
                'label' => 'Regras',
                'description' => 'Regras permanentes e bloqueios operacionais.',
                'view' => 'site/local-rules',
            ],
            'mudancas' => [
                'label' => 'Mudancas',
                'description' => 'Historico de alteracoes, logs e operacoes.',
                'view' => 'site/local-changes',
            ],
        ];

        $contentHtml = $this->cachedContentHtml($tab, (string) $tabs[$tab]['view']);

        View::render('admin/knowledge/index', [
            'title' => 'Base Tecnica | Estrategia Nerd',
            'active_tab' => $tab,
            'tabs' => $tabs,
            'content_html' => $contentHtml,
        ]);
    }

    private function cachedContentHtml(string $tab, string $view): string
    {
        $section = strtolower(trim((string) ($_GET['secao'] ?? '')));
        $cacheKey = 'admin.knowledge_hub.' . $tab . '.' . $section;
        $cache = Session::get($cacheKey);

        if (is_array($cache)) {
            $generatedAt = (int) ($cache['generated_at'] ?? 0);
            $html = (string) ($cache['html'] ?? '');

            if ($generatedAt > 0 && $html !== '' && (time() - $generatedAt) <= self::CACHE_TTL_SECONDS) {
                return $html;
            }
        }

        $docsController = new LocalDocsController();
        $contentData = match ($tab) {
            'documentacao' => $docsController->documentationViewData(true),
            'regras' => $docsController->rulesViewData(true),
            'mudancas' => $docsController->changesViewData(true),
            default => $docsController->backlogViewData(true),
        };

        $html = View::fragment($view, $contentData);

        Session::put($cacheKey, [
            'generated_at' => time(),
            'html' => $html,
        ]);

        return $html;
    }
}
