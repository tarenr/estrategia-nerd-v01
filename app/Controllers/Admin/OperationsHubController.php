<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Site\CentralOperacionalController;
use App\Controllers\Site\SearchConsoleMonitorController;
use App\Support\Session;
use App\Support\View;

final class OperationsHubController
{
    private const CACHE_TTL_SECONDS = 15;

    public function index(): void
    {
        $tab = strtolower(trim((string) ($_GET['aba'] ?? 'visao-geral')));
        $overviewSection = $this->normalizeOverviewSection();
        $monitorSection = $this->normalizeMonitorSection();
        $allowedTabs = ['visao-geral', 'monitoramento'];
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'visao-geral';
        }

        $tabs = [
            'visao-geral' => [
                'label' => 'Visao Geral',
                'description' => 'Resumo operacional e acoes amplas.',
                'view' => 'site/central-operacional',
            ],
            'monitoramento' => [
                'label' => 'Monitoramento',
                'description' => 'Search Console, indexacao e sinais externos.',
                'view' => 'site/search-console-monitor',
            ],
        ];

        if ($tab === 'visao-geral' && $this->isOverviewFragmentRequest()) {
            echo (new CentralOperacionalController())->renderOverviewSection(true);
            return;
        }

        if ($tab === 'monitoramento' && $this->isMonitorFragmentRequest()) {
            echo (new SearchConsoleMonitorController())->renderSection(true);
            return;
        }

        if ($this->isFragmentRequest()) {
            echo $this->renderContentSection($tab, (string) $tabs[$tab]['view'], $overviewSection, $monitorSection);
            return;
        }

        $contentHtml = $this->cachedContentHtml($tab, (string) $tabs[$tab]['view'], $overviewSection, $monitorSection, false);

        $activeTabUrl = match ($tab) {
            'monitoramento' => url('/admin/central-operacional?aba=monitoramento&monitor_secao=' . $monitorSection),
            default => url('/admin/central-operacional?aba=visao-geral&secao=' . $overviewSection),
        };

        View::render('admin/operations-hub/index', [
            'title' => 'Central Operacional | Estrategia Nerd',
            'active_tab' => $tab,
            'tabs' => $tabs,
            'content_html' => $contentHtml,
            'active_tab_url' => $activeTabUrl,
        ]);
    }

    private function cachedContentHtml(
        string $tab,
        string $view,
        string $overviewSection = 'resumo',
        string $monitorSection = 'resumo',
        bool $allowBuild = true
    ): string
    {
        $cacheSuffix = match ($tab) {
            'monitoramento' => '.' . $monitorSection,
            default => '.' . $overviewSection,
        };
        $cacheKey = 'admin.operations_hub.' . $tab . $cacheSuffix;
        $cache = Session::get($cacheKey);

        if (is_array($cache) && !$this->hasPendingSessionState($tab)) {
            $generatedAt = (int) ($cache['generated_at'] ?? 0);
            $html = (string) ($cache['html'] ?? '');

            if ($generatedAt > 0 && $html !== '' && (time() - $generatedAt) <= self::CACHE_TTL_SECONDS) {
                return $html;
            }
        }

        if (!$allowBuild) {
            return '';
        }

        $contentData = match ($tab) {
            'monitoramento' => (new SearchConsoleMonitorController())->viewData(
                true,
                $monitorSection,
                url('/admin/central-operacional?aba=monitoramento')
            ),
            default => (new CentralOperacionalController())->viewData(
                true,
                $overviewSection,
                url('/admin/central-operacional?aba=visao-geral')
            ),
        };

        $html = View::fragment($view, $contentData);

        Session::put($cacheKey, [
            'generated_at' => time(),
            'html' => $html,
        ]);

        return $html;
    }

    private function renderContentSection(
        string $tab,
        string $view,
        string $overviewSection = 'resumo',
        string $monitorSection = 'resumo'
    ): string
    {
        $contentHtml = $this->cachedContentHtml($tab, $view, $overviewSection, $monitorSection, true);
        $activeConfig = [
            'label' => match ($tab) {
                'monitoramento' => 'Monitoramento',
                default => 'Visao Geral',
            },
        ];

        ob_start();
        ?>
<section aria-labelledby="operations-tab-content" data-operations-hub-content data-loaded="true">
  <h2 id="operations-tab-content" class="sr-only"><?= htmlspecialchars((string) ($activeConfig['label'] ?? 'Central Operacional'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
  <?= $contentHtml ?>
</section>
        <?php

        return trim((string) ob_get_clean());
    }

    private function hasPendingSessionState(string $tab): bool
    {
        return match ($tab) {
            default => Session::has('operations_flash'),
        };
    }

    private function isFragmentRequest(): bool
    {
        return (string) ($_GET['fragment'] ?? '0') === '1';
    }

    private function isOverviewFragmentRequest(): bool
    {
        return (string) ($_GET['overview_fragment'] ?? '0') === '1';
    }

    private function isMonitorFragmentRequest(): bool
    {
        return (string) ($_GET['monitor_fragment'] ?? '0') === '1';
    }

    private function normalizeOverviewSection(): string
    {
        $section = strtolower(trim((string) ($_GET['secao'] ?? 'resumo')));
        $allowed = ['resumo', 'backups', 'pacotes', 'historico', 'testes'];

        return in_array($section, $allowed, true) ? $section : 'resumo';
    }

    private function normalizeMonitorSection(): string
    {
        $section = strtolower(trim((string) ($_GET['monitor_secao'] ?? 'resumo')));
        $allowed = ['resumo', 'inspecao'];

        return in_array($section, $allowed, true) ? $section : 'resumo';
    }
}
