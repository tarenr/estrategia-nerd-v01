<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\NewsletterRepository;

final class NewsletterService
{
    public function __construct(private NewsletterRepository $newsletter)
    {
    }

    public function getIndexViewModel(array $query = []): array
    {
        $filters = $this->normalizeFilters($query);
        $page = $this->clampInt((int) ($query['page'] ?? 1), 1, 9999);
        $perPage = $this->clampInt((int) ($query['per_page'] ?? 10), 5, 50);
        [$sort, $dir] = $this->normalizeSortDir((string) ($query['sort'] ?? 'data_cadastro'), (string) ($query['dir'] ?? 'desc'));

        $filteredItems = $this->newsletter->listAdmin($filters, $sort, $dir);
        $pagination = $this->newsletter->paginateAdmin($filters, $page, $perPage, $sort, $dir);

        $total = count($filteredItems);
        $ativos = count(array_filter($filteredItems, static fn (array $item): bool => (string) ($item['status'] ?? '') === 'ativo'));
        $inativos = count(array_filter($filteredItems, static fn (array $item): bool => (string) ($item['status'] ?? '') === 'inativo'));
        $desinscritos = count(array_filter($filteredItems, static fn (array $item): bool => (string) ($item['status'] ?? '') === 'desinscreve'));
        $today = count(array_filter($filteredItems, fn (array $item): bool => $this->isWithinLastDays((string) ($item['data_cadastro'] ?? ''), 1)));
        $last7 = count(array_filter($filteredItems, fn (array $item): bool => $this->isWithinLastDays((string) ($item['data_cadastro'] ?? ''), 7)));
        $activeLast7 = count(array_filter($filteredItems, fn (array $item): bool => (string) ($item['status'] ?? '') === 'ativo' && $this->isWithinLastDays((string) ($item['data_cadastro'] ?? ''), 7)));

        return [
            'title' => 'Newsletter',
            'items' => $pagination['items'] ?? [],
            'filters' => $filters,
            'sort' => $sort,
            'dir' => $dir,
            'pagination' => $pagination,
            'summary' => [
                'total' => $total,
                'ativos' => $ativos,
                'inativos' => $inativos,
                'desinscritos' => $desinscritos,
                'hoje' => $today,
                'last7' => $last7,
                'active_last7' => $activeLast7,
                'ativos_rate' => $total > 0 ? round(($ativos / $total) * 100, 1) : 0.0,
                'inativos_rate' => $total > 0 ? round(($inativos / $total) * 100, 1) : 0.0,
                'desinscritos_rate' => $total > 0 ? round(($desinscritos / $total) * 100, 1) : 0.0,
                'daily_avg_7' => $last7 > 0 ? round($last7 / 7, 1) : 0.0,
            ],
            'charts' => $this->buildIndexCharts($filteredItems, [
                'ativos' => $ativos,
                'inativos' => $inativos,
                'desinscritos' => $desinscritos,
                'hoje' => $today,
                'last7' => $last7,
                'active_last7' => $activeLast7,
            ]),
        ];
    }

    public function getDeleteViewModel(int $id, string $returnTo): ?array
    {
        $subscriber = $this->newsletter->findById($id);
        if ($subscriber === null) {
            return null;
        }

        return [
            'title' => 'Excluir Inscrito',
            'subscriber' => $subscriber,
            'returnTo' => $returnTo,
        ];
    }

    public function updateStatus(int $id, string $status): array
    {
        $subscriber = $this->newsletter->findById($id);
        if ($subscriber === null) {
            return ['ok' => false, 'not_found' => true];
        }

        if (!in_array($status, ['ativo', 'inativo', 'desinscreve'], true)) {
            return ['ok' => false, 'invalid_status' => true];
        }

        $this->newsletter->updateStatus($id, $status);

        return ['ok' => true, 'mode' => $status];
    }

    public function deleteSubscriber(int $id): array
    {
        $subscriber = $this->newsletter->findById($id);
        if ($subscriber === null) {
            return ['ok' => false, 'not_found' => true];
        }

        $this->newsletter->deleteById($id);
        return ['ok' => true];
    }

    private function normalizeFilters(array $query): array
    {
        return [
            'busca' => trim((string) ($query['busca'] ?? '')),
            'status' => trim((string) ($query['status'] ?? '')),
        ];
    }

    private function normalizeSortDir(string $sort, string $dir): array
    {
        $allowedSort = ['nome', 'email', 'status', 'data_cadastro', 'ip'];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'data_cadastro';
        }

        return [$sort, strtolower(trim($dir)) === 'asc' ? 'asc' : 'desc'];
    }

    private function isWithinLastDays(string $value, int $days): bool
    {
        $days = max(1, $days);
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return false;
        }

        $start = strtotime('-' . ($days - 1) . ' days 00:00:00');
        $end = strtotime('today 23:59:59');
        if ($start === false || $end === false) {
            return false;
        }

        return $timestamp >= $start && $timestamp <= $end;
    }

    /**
     * @param array<int, array<string,mixed>> $items
     * @param array<string,int> $summary
     * @return array<string, array<string,mixed>>
     */
    private function buildIndexCharts(array $items, array $summary): array
    {
        $today = strtotime('today 00:00:00') ?: time();
        $daily = [];
        for ($index = 6; $index >= 0; $index--) {
            $timestamp = strtotime('-' . $index . ' days', $today) ?: $today;
            $key = date('Y-m-d', $timestamp);
            $daily[$key] = ['label' => date('d/m', $timestamp), 'total' => 0, 'ativos' => 0];
        }

        foreach ($items as $item) {
            $timestamp = strtotime((string) ($item['data_cadastro'] ?? ''));
            if ($timestamp === false) {
                continue;
            }

            $key = date('Y-m-d', $timestamp);
            if (!isset($daily[$key])) {
                continue;
            }

            $daily[$key]['total']++;
            if ((string) ($item['status'] ?? '') === 'ativo') {
                $daily[$key]['ativos']++;
            }
        }

        return [
            'status' => [
                'labels' => ['Ativos', 'Inativos', 'Desinscritos'],
                'values' => [
                    max(0, (int) ($summary['ativos'] ?? 0)),
                    max(0, (int) ($summary['inativos'] ?? 0)),
                    max(0, (int) ($summary['desinscritos'] ?? 0)),
                ],
                'colors' => ['#34d399', '#60a5fa', '#fb7185'],
            ],
            'daily' => [
                'labels' => array_map(static fn (array $item): string => (string) $item['label'], $daily),
                'datasets' => [
                    [
                        'label' => 'Cadastros',
                        'values' => array_map(static fn (array $item): int => (int) $item['total'], $daily),
                        'backgroundColor' => 'rgba(34, 211, 238, 0.42)',
                        'borderColor' => 'rgba(34, 211, 238, 0.9)',
                    ],
                    [
                        'label' => 'Ativos',
                        'values' => array_map(static fn (array $item): int => (int) $item['ativos'], $daily),
                        'backgroundColor' => 'rgba(52, 211, 153, 0.32)',
                        'borderColor' => 'rgba(52, 211, 153, 0.86)',
                    ],
                ],
            ],
        ];
    }

    private function clampInt(int $value, int $min, int $max): int
    {
        if ($value < $min) {
            return $min;
        }
        if ($value > $max) {
            return $max;
        }

        return $value;
    }
}
