<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Site\BackupToolsController;
use App\Controllers\Site\CentralOperacionalController;
use App\Controllers\Site\ContentSyncToolsController;
use App\Support\Session;
use App\Support\View;

final class OperationsHubController
{
    private const CACHE_TTL_SECONDS = 15;

    public function index(): void
    {
        $tab = strtolower(trim((string) ($_GET['aba'] ?? 'visao-geral')));
        $allowedTabs = ['visao-geral', 'backup-restore', 'conteudo'];
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'visao-geral';
        }

        $tabs = [
            'visao-geral' => [
                'label' => 'Visao Geral',
                'description' => 'Resumo operacional e acoes amplas.',
                'view' => 'site/central-operacional',
            ],
            'backup-restore' => [
                'label' => 'Backup e Restore',
                'description' => 'Backups, verificacoes e restauracao controlada.',
                'view' => 'site/backup-tools',
            ],
            'conteudo' => [
                'label' => 'Conteudo',
                'description' => 'Pacotes, publicacao e paridade editorial.',
                'view' => 'site/content-sync-tools',
            ],
        ];

        $contentHtml = $this->cachedContentHtml($tab, (string) $tabs[$tab]['view']);

        View::render('admin/operations-hub/index', [
            'title' => 'Central Operacional | Estrategia Nerd',
            'active_tab' => $tab,
            'tabs' => $tabs,
            'content_html' => $contentHtml,
        ]);
    }

    private function cachedContentHtml(string $tab, string $view): string
    {
        $cacheKey = 'admin.operations_hub.' . $tab;
        $cache = Session::get($cacheKey);

        if (is_array($cache) && !$this->hasPendingSessionState($tab)) {
            $generatedAt = (int) ($cache['generated_at'] ?? 0);
            $html = (string) ($cache['html'] ?? '');

            if ($generatedAt > 0 && $html !== '' && (time() - $generatedAt) <= self::CACHE_TTL_SECONDS) {
                return $html;
            }
        }

        $contentData = match ($tab) {
            'backup-restore' => (new BackupToolsController())->viewData(true),
            'conteudo' => (new ContentSyncToolsController())->viewData(true),
            default => (new CentralOperacionalController())->viewData(true),
        };

        $html = View::fragment($view, $contentData);

        Session::put($cacheKey, [
            'generated_at' => time(),
            'html' => $html,
        ]);

        return $html;
    }

    private function hasPendingSessionState(string $tab): bool
    {
        return match ($tab) {
            'backup-restore' => Session::has('backup_tools_flash') || Session::has('backup_tools_verification'),
            'conteudo' => Session::has('content_sync_flash') || Session::has('content_sync_verification') || Session::has('content_sync_postcheck'),
            default => Session::has('operations_flash'),
        };
    }
}
