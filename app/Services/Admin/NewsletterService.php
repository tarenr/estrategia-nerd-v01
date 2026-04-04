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
        $today = count(array_filter($filteredItems, static fn (array $item): bool => substr((string) ($item['data_cadastro'] ?? ''), 0, 10) === date('Y-m-d')));

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
            ],
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
