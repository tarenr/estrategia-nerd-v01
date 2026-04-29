<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Services\Site\SearchConsoleService;
use App\Support\LocalOnlyAccess;
use App\Support\View;

final class SearchConsoleMonitorController
{
    public function index(): void
    {
        $this->ensureLocalOnly();

        if ($this->isFragmentRequest()) {
            echo $this->renderSection(false);
            return;
        }

        View::render('site/search-console-monitor', $this->viewData());
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(bool $adminEmbed = false, ?string $section = null, ?string $baseUrl = null): array
    {
        $activeSection = $this->normalizeSection($section);
        $inspectionUrl = trim((string) ($_GET['inspection_url'] ?? ''));
        $viewModel = $this->service()->dashboard($activeSection, $inspectionUrl !== '' ? $inspectionUrl : null);
        $resolvedBaseUrl = $baseUrl ?? ($adminEmbed ? url('/admin/central-operacional?aba=monitoramento') : url('/local/monitoramento'));

        return [
            'title' => 'Search Console | Estrategia Nerd',
            'meta_description' => 'Monitoramento local da propriedade no Google Search Console.',
            'site_chrome' => false,
            'embed_mode' => $adminEmbed ? true : $this->embedMode(),
            'admin_embed' => $adminEmbed,
            'monitor_section' => $activeSection,
            'monitor_sections' => $this->sections(),
            'monitor_base_url' => $resolvedBaseUrl,
            'monitor_fragment_base_url' => $resolvedBaseUrl,
            'search_console' => $viewModel,
            'inspection_url' => $inspectionUrl,
        ];
    }

    public function renderSection(bool $adminEmbed): string
    {
        return View::fragment(
            'site/partials/search-console-monitor-content',
            $this->viewData(
                $adminEmbed,
                $this->normalizeSection(),
                $adminEmbed ? url('/admin/central-operacional?aba=monitoramento') : url('/local/monitoramento')
            )
        );
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function sections(): array
    {
        return [
            'resumo' => [
                'label' => 'Resumo',
                'description' => 'Propriedade, sitemaps e visao rapida.',
            ],
            'inspecao' => [
                'label' => 'Inspecao de URL',
                'description' => 'Consultar indexacao de uma URL especifica.',
            ],
        ];
    }

    private function normalizeSection(?string $section = null): string
    {
        $value = strtolower(trim((string) ($section ?? ($_GET['monitor_secao'] ?? 'resumo'))));
        $allowed = array_keys($this->sections());

        return in_array($value, $allowed, true) ? $value : 'resumo';
    }

    private function isFragmentRequest(): bool
    {
        return (string) ($_GET['monitor_fragment'] ?? '0') === '1';
    }

    private function ensureLocalOnly(): void
    {
        LocalOnlyAccess::enforce();
    }

    private function embedMode(): bool
    {
        return (string) ($_GET['embed'] ?? '0') === '1';
    }

    private function service(): SearchConsoleService
    {
        return new SearchConsoleService();
    }
}
