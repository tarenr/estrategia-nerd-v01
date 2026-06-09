<?php

declare(strict_types=1);

namespace App\Services\Admin\Presenters;

use App\Controllers\Site\CentralOperacionalController;
use Scripts\ContentSync\ContentSyncManager;

final class OperationsV2Presenter
{
    /**
     * @return array<string, mixed>
     */
    public function observability(): array
    {
        $controller = new CentralOperacionalController();
        $historyData = $controller->viewData(
            true,
            'historico',
            url('/admin/central-operacional?aba=visao-geral&monitor_secao=historico')
        );
        $testsData = $controller->viewData(
            true,
            'testes',
            url('/admin/central-operacional?aba=visao-geral&monitor_secao=testes')
        );

        $historyStatus = is_array($historyData['operations_status'] ?? null) ? $historyData['operations_status'] : [];
        $testsStatus = is_array($testsData['operations_status'] ?? null) ? $testsData['operations_status'] : [];
        $logsMeta = is_array($historyStatus['logs'] ?? null) ? $historyStatus['logs'] : [];
        $logs = is_array($historyStatus['logs']['categories'] ?? null) ? $historyStatus['logs']['categories'] : [];
        $smoke = is_array($testsStatus['smoke_tests'] ?? null) ? $testsStatus['smoke_tests'] : [];
        $applicationLogs = $this->recentApplicationLogs(12);
        $smokeHistory = is_array($smoke['history'] ?? null) ? array_slice((array) $smoke['history'], 0, 8) : [];
        $environments = $this->observabilityEnvironmentCards($smoke);
        $eventCount = $this->countLogEntries($logs) + count($applicationLogs);
        $smokeSummary = $this->summarizeSmoke($smoke);
        $criticalCount = (int) $smokeSummary['failures'] + $this->countApplicationEvents($applicationLogs, ['error', 'fail', 'exception']);
        $warningCount = $this->countApplicationEvents($applicationLogs, ['warning', 'csrf', 'invalid']);

        return [
            'facts' => [
                ['label' => 'Ambiente', 'value' => environment_label(current_environment())],
                ['label' => 'Modo', 'value' => 'Somente Leitura'],
                ['label' => 'Fonte', 'value' => 'Logs e smoke tests locais'],
                ['label' => 'Última Leitura', 'value' => date('d/m/Y H:i:s')],
            ],
            'cards' => [
                [
                    'label' => 'Saúde Geral',
                    'value' => $criticalCount > 0 ? 'Atenção' : 'Estável',
                    'tone' => $criticalCount > 0 ? 'warning' : 'success',
                    'icon' => 'fa-solid fa-heart-pulse',
                    'hint' => $criticalCount > 0 ? $criticalCount . ' sinal(is) crítico(s)' : 'Nenhum sinal crítico na leitura atual',
                ],
                [
                    'label' => 'Eventos Recentes',
                    'value' => (string) $eventCount,
                    'tone' => $eventCount > 0 ? 'info' : 'neutral',
                    'icon' => 'fa-solid fa-wave-square',
                    'hint' => 'Logs operacionais e registros do sistema',
                ],
                [
                    'label' => 'Smoke Tests',
                    'value' => (string) ($smokeSummary['ok'] . '/' . $smokeSummary['total']),
                    'tone' => ((int) $smokeSummary['failures']) > 0 ? 'danger' : 'success',
                    'icon' => 'fa-solid fa-vial-circle-check',
                    'hint' => ((int) $smokeSummary['failures']) > 0 ? $smokeSummary['failures'] . ' falha(s)' : 'Últimas execuções sem falhas registradas',
                ],
                [
                    'label' => 'Warnings',
                    'value' => (string) $warningCount,
                    'tone' => $warningCount > 0 ? 'warning' : 'success',
                    'icon' => 'fa-solid fa-triangle-exclamation',
                    'hint' => $warningCount > 0 ? 'Eventos leves pedem revisão' : 'Sem warnings recentes nos logs lidos',
                ],
            ],
            'environments' => $environments,
            'smoke_history' => $this->presentSmokeHistory($smokeHistory),
            'operation_logs' => $this->presentOperationLogCategories($logs),
            'application_logs' => $applicationLogs,
            'alerts' => $this->observabilityAlerts($environments, $applicationLogs, $criticalCount, $logsMeta),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $controller = new CentralOperacionalController();
        $summaryData = $controller->viewData(
            true,
            'resumo',
            url('/admin/central-operacional?aba=visao-geral')
        );
        $backupsData = $controller->viewData(
            true,
            'backups',
            url('/admin/central-operacional?aba=visao-geral&monitor_secao=backups')
        );
        $packagesData = $controller->viewData(
            true,
            'pacotes',
            url('/admin/central-operacional?aba=visao-geral&monitor_secao=pacotes')
        );
        $historyData = $controller->viewData(
            true,
            'historico',
            url('/admin/central-operacional?aba=visao-geral&monitor_secao=historico')
        );

        $status = is_array($summaryData['operations_status'] ?? null) ? $summaryData['operations_status'] : [];
        $policy = is_array($status['policy'] ?? null) ? $status['policy'] : [];
        $backupsStatus = is_array($backupsData['operations_status'] ?? null) ? $backupsData['operations_status'] : [];
        $packagesStatus = is_array($packagesData['operations_status'] ?? null) ? $packagesData['operations_status'] : [];
        $historyStatus = is_array($historyData['operations_status'] ?? null) ? $historyData['operations_status'] : [];
        $technicalBackup = is_array($backupsStatus['technical_backup'] ?? null) ? $backupsStatus['technical_backup'] : [];
        $backup = is_array($backupsStatus['backup'] ?? null) ? $backupsStatus['backup'] : [];
        $content = is_array($packagesStatus['content'] ?? null) ? $packagesStatus['content'] : [];
        $rawContentStatus = $this->contentStatus();
        if (is_array($rawContentStatus['items'] ?? null)) {
            $content['items'] = $this->presentRawContentPackages((array) $rawContentStatus['items']);
            $content['latest'] = $content['items'][0] ?? ($content['latest'] ?? null);
            $content['total'] = count($content['items']);
        }
        $logs = is_array($historyStatus['logs']['categories'] ?? null) ? $historyStatus['logs']['categories'] : [];
        $logCount = $this->countLogEntries($logs);
        $environments = $this->environmentSummaries($backup, $technicalBackup, $content);
        $observabilityEnvironments = $this->observabilitySummaries($logs);
        $alerts = $this->environmentAlerts($environments);

        return [
            'source' => 'central-operacional-v1',
            'flash' => is_array($summaryData['flash'] ?? null) ? $summaryData['flash'] : null,
            'policy' => $policy,
            'cards' => $this->environmentCards($environments),
            'facts' => [
                [
                    'label' => 'Ambiente',
                    'value' => environment_label(current_environment()),
                ],
                [
                    'label' => 'Dados',
                    'value' => 'Central Operacional V1',
                ],
                [
                    'label' => 'Fluxo',
                    'value' => 'Local → Stage → Produção',
                ],
                [
                    'label' => 'Última Leitura',
                    'value' => date('d/m/Y H:i:s'),
                ],
            ],
            'environments' => $environments,
            'observability_environments' => $observabilityEnvironments,
            'alerts' => $alerts,
            'observability' => [
                'status' => $logCount > 0 ? 'Disponível' : 'Sem Alertas',
                'tone' => $logCount > 0 ? 'warning' : 'success',
                'summary' => $logCount > 0 ? $logCount . ' eventos recentes' : 'Sem alertas registrados',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $backup
     * @param array<string, mixed> $technicalBackup
     * @param array<string, mixed> $content
     * @return array<string, array<string, mixed>>
     */
    private function environmentSummaries(array $backup, array $technicalBackup, array $content): array
    {
        $dataBackups = is_array($backup['items_by_profile'] ?? null) ? $backup['items_by_profile'] : [];
        $technicalProfiles = is_array($technicalBackup['profiles'] ?? null) ? $technicalBackup['profiles'] : [];
        $contentItems = is_array($content['items'] ?? null) ? $content['items'] : [];

        $summaries = [];
        foreach (['local', 'stage', 'production'] as $environment) {
            $technicalLatest = is_array($technicalBackup[$environment . '_latest'] ?? null)
                ? $technicalBackup[$environment . '_latest']
                : null;
            if ($technicalLatest === null) {
                $technicalItems = is_array($technicalProfiles[$environment] ?? null) ? $technicalProfiles[$environment] : [];
                $technicalLatest = is_array($technicalItems[0] ?? null) ? $technicalItems[0] : null;
            }

            $systemLatest = $technicalLatest;
            $dataItems = is_array($dataBackups[$environment] ?? null) ? $dataBackups[$environment] : [];
            $dataLatest = is_array($dataItems[0] ?? null) ? $dataItems[0] : null;
            if ($systemLatest === null && $dataLatest !== null) {
                $systemLatest = $dataLatest;
            }

            $editorialLatest = null;
            foreach ($contentItems as $contentItem) {
                if (!is_array($contentItem)) {
                    continue;
                }

                $contentSource = strtolower((string) ($contentItem['source_profile'] ?? ''));
                if ($contentSource === $environment) {
                    $editorialLatest = $contentItem;
                    break;
                }
            }

            $systemStatus = $systemLatest !== null ? 'OK' : 'Leitura pendente';
            $editorialStatus = $editorialLatest !== null ? 'OK' : 'Leitura pendente';
            $overallTone = ($systemLatest !== null && $editorialLatest !== null)
                ? 'success'
                : ($systemLatest !== null ? 'warning' : 'neutral');

            $summaries[$environment] = [
                'key' => $environment,
                'label' => environment_label($environment),
                'status' => $systemLatest !== null || $editorialLatest !== null ? ($overallTone === 'success' ? 'OK' : 'Pendente') : 'Sem leitura',
                'tone' => $overallTone,
                'system' => [
                    'status' => $systemStatus,
                    'id' => $this->backupValue($systemLatest, 'backup_id'),
                    'date' => $this->backupDate($systemLatest),
                    'size' => $this->backupSize($systemLatest),
                    'profile' => $this->backupProfile($systemLatest),
                    'cloud' => $this->cloudStatus($systemLatest),
                ],
                'editorial' => [
                    'status' => $editorialStatus,
                    'id' => $this->backupValue($editorialLatest, 'package_id'),
                    'date' => $this->backupDate($editorialLatest),
                    'size' => 'Leitura pendente',
                    'source' => $this->editorialSource($editorialLatest),
                    'applied' => $this->editorialApplied($editorialLatest),
                    'posts' => $this->contentStat($editorialLatest, 'posts'),
                    'links' => $this->contentStat($editorialLatest, 'links'),
                    'uploads' => $this->contentUploads($editorialLatest),
                ],
                'late' => $systemLatest === null || $editorialLatest === null,
            ];
        }

        return $summaries;
    }

    /**
     * @param array<string, array<string, mixed>> $environments
     * @return array<int, array<string, string>>
     */
    private function environmentCards(array $environments): array
    {
        $icons = [
            'local' => 'fa-solid fa-laptop-code',
            'stage' => 'fa-solid fa-code-branch',
            'production' => 'fa-solid fa-globe',
        ];

        $cards = [];
        foreach ($environments as $key => $environment) {
            $system = is_array($environment['system'] ?? null) ? $environment['system'] : [];
            $editorial = is_array($environment['editorial'] ?? null) ? $environment['editorial'] : [];
            $cards[] = [
                'label' => 'Ambiente ' . (string) ($environment['label'] ?? ucfirst((string) $key)),
                'value' => (string) ($environment['status'] ?? 'Sem leitura'),
                'hint' => 'Resumo dos backups sistêmico e editorial deste ambiente.',
                'icon' => $icons[$key] ?? 'fa-solid fa-server',
                'tone' => (string) ($environment['tone'] ?? 'neutral'),
                'href' => url('/admin/central-operacional-v2/backup-sistemico'),
                'support' => [
                    ['label' => 'Sistêmico', 'value' => (string) ($system['id'] ?? 'Leitura pendente')],
                    ['label' => 'Data', 'value' => (string) ($system['date'] ?? 'Leitura pendente')],
                    ['label' => 'Editorial', 'value' => (string) ($editorial['id'] ?? 'Leitura pendente')],
                    ['label' => 'Status', 'value' => (string) ($environment['status'] ?? 'Sem leitura')],
                ],
            ];
        }

        return $cards;
    }

    /**
     * @param array<string, array<string, mixed>> $environments
     * @return array<int, array<string, string>>
     */
    private function environmentAlerts(array $environments): array
    {
        $alerts = [];
        foreach ($environments as $environment) {
            $label = (string) ($environment['label'] ?? 'Ambiente');
            $system = is_array($environment['system'] ?? null) ? $environment['system'] : [];
            $editorial = is_array($environment['editorial'] ?? null) ? $environment['editorial'] : [];

            if (($system['status'] ?? '') !== 'OK') {
                $alerts[] = [
                    'label' => $label . ' sem backup sistêmico recente',
                    'tone' => 'neutral',
                ];
            }
            if (($editorial['status'] ?? '') !== 'OK') {
                $alerts[] = [
                    'label' => $label . ' sem leitura editorial',
                    'tone' => 'neutral',
                ];
            }
            if (($system['status'] ?? '') === 'OK' && ($editorial['status'] ?? '') === 'OK') {
                $alerts[] = [
                    'label' => $label . ' saudável',
                    'tone' => 'success',
                ];
            }
        }

        return $alerts;
    }

    /**
     * @param array<string, mixed> $logs
     * @return array<string, array<string, mixed>>
     */
    private function observabilitySummaries(array $logs): array
    {
        $summaries = [];
        foreach (['local', 'stage', 'production'] as $environment) {
            $count = 0;
            $latest = '';
            $categories = [];

            foreach ($logs as $categoryKey => $category) {
                if (!is_array($category)) {
                    continue;
                }

                $entries = is_array($category['entries'] ?? null) ? $category['entries'] : [];
                foreach ($entries as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }

                    $origin = strtolower((string) ($entry['origin'] ?? ''));
                    $destination = strtolower((string) ($entry['destination'] ?? ''));
                    if ($origin !== $environment && $destination !== $environment) {
                        continue;
                    }

                    $count++;
                    $categories[(string) $categoryKey] = ($categories[(string) $categoryKey] ?? 0) + 1;
                    $timestamp = trim((string) ($entry['timestamp'] ?? ''));
                    if ($latest === '' && $timestamp !== '') {
                        $latest = $timestamp;
                    }
                }
            }

            $summaries[$environment] = [
                'key' => $environment,
                'label' => environment_label($environment),
                'status' => $count > 0 ? 'Com eventos' : 'Sem eventos',
                'tone' => $count > 0 ? 'warning' : 'success',
                'events' => $count,
                'latest' => $latest !== '' ? $this->formatDate($latest) : 'Sem leitura recente',
                'categories' => $categories,
            ];
        }

        return $summaries;
    }

    /**
     * @param array<string, mixed>|null $item
     */
    private function backupValue(?array $item, string $key): string
    {
        $value = trim((string) ($item[$key] ?? ''));

        return $value !== '' ? $value : 'Leitura Pendente';
    }

    /**
     * @param array<string, mixed>|null $item
     */
    private function backupDate(?array $item): string
    {
        if ($item === null) {
            return 'Leitura pendente';
        }

        $date = trim((string) ($item['created_at'] ?? ''));

        return $date !== '' ? $this->formatDate($date) : 'Leitura pendente';
    }

    /**
     * @param array<string, mixed>|null $item
     */
    private function backupSize(?array $item): string
    {
        if ($item === null) {
            return 'Leitura pendente';
        }

        $size = trim((string) ($item['total_size'] ?? ''));
        if ($size !== '') {
            return $size;
        }

        $files = (int) ($item['files_count'] ?? ($item['system_files_count'] ?? 0));
        if ($files > 0) {
            return $files . ' arquivos';
        }

        return 'Leitura pendente';
    }

    /**
     * @param array<string, mixed>|null $item
     */
    private function backupProfile(?array $item): string
    {
        if ($item === null) {
            return 'Leitura pendente';
        }

        $profile = trim((string) ($item['profile_label'] ?? ''));

        return $profile !== '' ? $profile : 'Leitura pendente';
    }

    /**
     * @param array<string, mixed>|null $item
     */
    private function cloudStatus(?array $item): string
    {
        if ($item === null) {
            return 'Leitura pendente';
        }

        if ((bool) ($item['cloud_uploaded'] ?? false)) {
            $uploadedAt = trim((string) ($item['cloud_uploaded_at'] ?? ''));
            return $uploadedAt !== '' ? 'Enviado em ' . $this->formatDate($uploadedAt) : 'Enviado';
        }

        return 'Pendente';
    }

    /**
     * @param array<string, mixed>|null $item
     */
    private function editorialSource(?array $item): string
    {
        if ($item === null) {
            return 'Leitura pendente';
        }

        $label = trim((string) ($item['source_profile_label'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        $source = trim((string) ($item['source_profile'] ?? ''));

        return $source !== '' ? $source : 'Leitura pendente';
    }

    /**
     * @param array<string, mixed>|null $item
     */
    private function editorialApplied(?array $item): string
    {
        if ($item === null) {
            return 'Leitura pendente';
        }

        $targets = array_values((array) ($item['applied_targets'] ?? []));
        if ($targets === []) {
            return 'Sem aplicação registrada';
        }

        $labels = [];
        foreach ($targets as $target) {
            if (is_array($target)) {
                $label = trim((string) ($target['target_profile_label'] ?? $target['target_profile'] ?? $target['environment'] ?? ''));
                if ($label !== '') {
                    $labels[] = $label;
                }
                continue;
            }

            $label = trim((string) $target);
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        return $labels !== [] ? implode(', ', array_unique($labels)) : 'Sem aplicação registrada';
    }

    /**
     * @param array<string, mixed>|null $item
     */
    private function contentStat(?array $item, string $key): string
    {
        if ($item === null) {
            return 'Leitura pendente';
        }

        $stats = is_array($item['stats'] ?? null) ? $item['stats'] : [];
        $value = (int) ($stats[$key] ?? 0);

        return $value > 0 ? (string) $value : 'Leitura pendente';
    }

    /**
     * @param array<string, mixed>|null $item
     */
    private function contentUploads(?array $item): string
    {
        if ($item === null) {
            return 'Leitura pendente';
        }

        $uploads = is_array($item['uploads'] ?? null) ? $item['uploads'] : [];
        $value = (int) ($uploads['included_files'] ?? 0);

        return $value > 0 ? $value . ' arquivos' : 'Leitura pendente';
    }

    /**
     * @return array<string, mixed>
     */
    private function contentStatus(): array
    {
        try {
            require_once base_path('scripts/content-sync/ContentSyncManager.php');

            $config = require base_path('config/content-sync.php');

            return (new ContentSyncManager($config))->status();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<int, mixed> $items
     * @return array<int, array<string, mixed>>
     */
    private function presentRawContentPackages(array $items): array
    {
        $presented = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $sourceProfile = strtolower(trim((string) ($item['source_profile'] ?? '')));
            if (!in_array($sourceProfile, ['local', 'stage', 'production'], true)) {
                continue;
            }

            $presented[] = [
                'package_id' => (string) ($item['package_id'] ?? ''),
                'source_profile' => $sourceProfile,
                'source_profile_label' => (string) ($item['source_profile_label'] ?? ''),
                'created_at' => (string) ($item['created_at'] ?? ''),
                'status' => (string) ($item['status'] ?? ''),
                'is_valid' => (bool) ($item['is_valid'] ?? false),
                'allowed_targets' => array_values((array) ($item['allowed_targets'] ?? [])),
                'applied_targets' => array_values((array) ($item['applied_targets'] ?? [])),
                'stats' => is_array($item['stats'] ?? null) ? $item['stats'] : [],
                'uploads' => is_array($item['uploads'] ?? null) ? $item['uploads'] : [],
            ];
        }

        return $presented;
    }

    /**
     * @param array<string, mixed>|null $item
     */
    private function backupHint(?array $item, string $empty): string
    {
        if ($item === null) {
            return $empty;
        }

        $createdAt = trim((string) ($item['created_at'] ?? ''));
        $files = (int) ($item['files_count'] ?? 0);
        $parts = [];
        if ($createdAt !== '') {
            $parts[] = 'Criado em ' . $this->formatDate($createdAt);
        }
        if ($files > 0) {
            $parts[] = $files . ' arquivos';
        }

        return $parts !== [] ? implode(' · ', $parts) : 'Snapshot técnico encontrado.';
    }

    /**
     * @param array<string, mixed>|null $item
     */
    private function systemBackupHint(?array $item): string
    {
        if ($item === null) {
            return 'Leitura pendente para o backup sistêmico.';
        }

        $parts = [];
        $createdAt = trim((string) ($item['created_at'] ?? ''));
        $totalSize = trim((string) ($item['total_size'] ?? ''));
        $files = (int) ($item['files_count'] ?? ($item['system_files_count'] ?? 0));
        $profile = trim((string) ($item['profile_label'] ?? ''));

        if ($createdAt !== '') {
            $parts[] = 'Criado em ' . $this->formatDate($createdAt);
        }
        if ($totalSize !== '') {
            $parts[] = $totalSize;
        } elseif ($files > 0) {
            $parts[] = $files . ' arquivos';
        }
        if ($profile !== '') {
            $parts[] = $profile;
        }

        return $parts !== [] ? implode(' · ', $parts) : 'Backup sistêmico encontrado.';
    }

    /**
     * @param array<string, mixed> $item
     */
    private function dateHint(array $item, string $key, string $fallback): string
    {
        $date = trim((string) ($item[$key] ?? ''));

        return $date !== '' ? 'Criado em ' . $this->formatDate($date) : $fallback;
    }

    /**
     * @param array<string, mixed> $logs
     */
    private function countLogEntries(array $logs): int
    {
        $total = 0;
        foreach ($logs as $category) {
            if (!is_array($category)) {
                continue;
            }
            $entries = is_array($category['entries'] ?? null) ? $category['entries'] : [];
            $total += count($entries);
        }

        return $total;
    }

    /**
     * @param array<string, mixed> $smoke
     * @return array<string, array<string, mixed>>
     */
    private function observabilityEnvironmentCards(array $smoke): array
    {
        $latestByEnvironment = is_array($smoke['latest_by_environment'] ?? null) ? $smoke['latest_by_environment'] : [];
        $cards = [];

        foreach (['local', 'stage', 'production'] as $environment) {
            $latest = is_array($latestByEnvironment[$environment] ?? null) ? $latestByEnvironment[$environment] : [];
            $summary = is_array($latest['summary'] ?? null) ? $latest['summary'] : [];
            $status = strtolower((string) ($latest['status'] ?? ''));
            $failures = (int) ($summary['fail'] ?? 0);
            $total = (int) ($summary['total'] ?? 0);
            $ok = (int) ($summary['ok'] ?? 0);

            $cards[$environment] = [
                'key' => $environment,
                'label' => environment_label($environment),
                'status' => $latest === [] ? 'Leitura pendente' : ($status === 'ok' && $failures === 0 ? 'OK' : 'Atenção'),
                'tone' => $latest === [] ? 'neutral' : ($status === 'ok' && $failures === 0 ? 'success' : 'warning'),
                'last_run' => $this->formatDate((string) ($latest['finished_at'] ?? $latest['started_at'] ?? '')),
                'run_id' => (string) ($latest['id'] ?? 'Leitura pendente'),
                'tests' => $total > 0 ? $ok . '/' . $total . ' OK' : 'Leitura pendente',
                'failures' => $failures,
                'duration' => $this->formatDuration((int) ($latest['duration_ms'] ?? 0)),
            ];
        }

        return $cards;
    }

    /**
     * @param array<string, mixed> $smoke
     * @return array<string, int>
     */
    private function summarizeSmoke(array $smoke): array
    {
        $latestByEnvironment = is_array($smoke['latest_by_environment'] ?? null) ? $smoke['latest_by_environment'] : [];
        $summary = ['ok' => 0, 'total' => 0, 'failures' => 0];

        foreach ($latestByEnvironment as $latest) {
            if (!is_array($latest)) {
                continue;
            }

            $itemSummary = is_array($latest['summary'] ?? null) ? $latest['summary'] : [];
            $summary['ok'] += (int) ($itemSummary['ok'] ?? 0);
            $summary['total'] += (int) ($itemSummary['total'] ?? 0);
            $summary['failures'] += (int) ($itemSummary['fail'] ?? 0);
        }

        return $summary;
    }

    /**
     * @param array<int, mixed> $items
     * @return array<int, array<string, mixed>>
     */
    private function presentSmokeHistory(array $items): array
    {
        $history = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $summary = is_array($item['summary'] ?? null) ? $item['summary'] : [];
            $status = strtolower((string) ($item['status'] ?? ''));
            $failures = (int) ($summary['fail'] ?? 0);
            $history[] = [
                'id' => (string) ($item['id'] ?? ''),
                'environment' => (string) ($item['environment_label'] ?? environment_label((string) ($item['environment'] ?? 'local'))),
                'status' => $status === 'ok' && $failures === 0 ? 'OK' : 'Atenção',
                'tone' => $status === 'ok' && $failures === 0 ? 'success' : 'warning',
                'finished_at' => $this->formatDate((string) ($item['finished_at'] ?? $item['started_at'] ?? '')),
                'summary' => sprintf('%d OK · %d falha(s) · %d ignorado(s)', (int) ($summary['ok'] ?? 0), $failures, (int) ($summary['skip'] ?? 0)),
                'duration' => $this->formatDuration((int) ($item['duration_ms'] ?? 0)),
            ];
        }

        return $history;
    }

    /**
     * @param array<string, mixed> $logs
     * @return array<int, array<string, mixed>>
     */
    private function presentOperationLogCategories(array $logs): array
    {
        $categories = [];
        foreach ($logs as $key => $category) {
            if (!is_array($category)) {
                continue;
            }

            $entries = is_array($category['entries'] ?? null) ? $category['entries'] : [];
            $latest = is_array($entries[0] ?? null) ? $entries[0] : [];
            $categories[] = [
                'key' => (string) $key,
                'label' => (string) ($category['label'] ?? ucfirst((string) $key)),
                'count' => count($entries),
                'latest_file' => (string) ($category['latest_file'] ?? 'Leitura pendente'),
                'latest_status' => (string) ($latest['status'] ?? 'Leitura pendente'),
                'latest_message' => (string) ($latest['message'] ?? 'Sem evento recente.'),
                'latest_date' => $this->formatDate((string) ($latest['timestamp'] ?? '')),
                'tone' => strtoupper((string) ($latest['status'] ?? '')) === 'OK' ? 'success' : ($latest === [] ? 'neutral' : 'warning'),
                'entries' => array_slice($entries, 0, 3),
            ];
        }

        return $categories;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function recentApplicationLogs(int $limit): array
    {
        $files = glob(base_path('storage/logs/*.log')) ?: [];
        usort($files, static fn (string $a, string $b): int => (int) @filemtime($b) <=> (int) @filemtime($a));

        $items = [];
        foreach ($files as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines)) {
                continue;
            }

            foreach (array_reverse(array_slice($lines, -80)) as $line) {
                $payload = json_decode($line, true);
                if (!is_array($payload)) {
                    continue;
                }

                $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
                $items[] = [
                    'date' => $this->formatDate((string) ($payload['timestamp'] ?? '')),
                    'event' => (string) ($payload['event'] ?? 'evento'),
                    'source' => basename($file),
                    'environment' => environment_label((string) ($context['current_environment'] ?? current_environment())),
                    'detail' => $this->logDetail($context),
                ];

                if (count($items) >= $limit) {
                    return $items;
                }
            }
        }

        return $items;
    }

    /**
     * @param array<int, array<string, string>> $logs
     * @param array<int, string> $needles
     */
    private function countApplicationEvents(array $logs, array $needles): int
    {
        $count = 0;
        foreach ($logs as $log) {
            $event = strtolower((string) ($log['event'] ?? ''));
            foreach ($needles as $needle) {
                if (str_contains($event, strtolower($needle))) {
                    $count++;
                    break;
                }
            }
        }

        return $count;
    }

    /**
     * @param array<string, array<string, mixed>> $environments
     * @param array<int, array<string, string>> $applicationLogs
     * @return array<int, array<string, string>>
     */
    private function observabilityAlerts(array $environments, array $applicationLogs, int $criticalCount, array $logsMeta = []): array
    {
        $alerts = [];
        if (array_key_exists('available', $logsMeta) && !(bool) ($logsMeta['available'] ?? true)) {
            $alerts[] = [
                'label' => 'Logs operacionais indisponiveis',
                'text' => (string) ($logsMeta['unavailable_reason'] ?? 'Pasta operacional nao acessivel.'),
                'tone' => 'warning',
            ];
        }

        foreach ($environments as $environment) {
            if (($environment['status'] ?? '') !== 'OK') {
                $alerts[] = [
                    'label' => (string) ($environment['label'] ?? 'Ambiente') . ' exige leitura dos testes',
                    'text' => (string) ($environment['run_id'] ?? 'Sem execução recente'),
                    'tone' => 'warning',
                ];
            }
        }

        if ($criticalCount > 0) {
            $alerts[] = [
                'label' => 'Eventos críticos encontrados',
                'text' => $criticalCount . ' sinal(is) pedem revisão nos logs recentes.',
                'tone' => 'warning',
            ];
        }

        if ($alerts === []) {
            $alerts[] = [
                'label' => 'Nenhum alerta crítico',
                'text' => $applicationLogs === [] ? 'Sem logs recentes para leitura.' : 'Logs e testes recentes sem falhas críticas.',
                'tone' => 'success',
            ];
        }

        return $alerts;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logDetail(array $context): string
    {
        foreach (['request_path', 'overall_status', 'target_environment', 'usuario', 'backup_id', 'package_id'] as $key) {
            $value = trim((string) ($context[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return 'Sem detalhe adicional';
    }

    private function formatDuration(int $durationMs): string
    {
        if ($durationMs <= 0) {
            return 'Leitura pendente';
        }

        if ($durationMs < 1000) {
            return $durationMs . ' ms';
        }

        return number_format($durationMs / 1000, 1, ',', '.') . ' s';
    }

    private function formatDate(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return 'Leitura pendente';
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }

        return date('d/m/Y H:i:s', $timestamp);
    }
}
