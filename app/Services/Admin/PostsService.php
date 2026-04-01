<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Services/Admin/PostsService.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Service da Central de Posts (Admin)
 * @description Normaliza filtros/ordenação/paginação e monta ViewModel via Repositories.
 * @usage       PostsService::getIndexViewModel($_GET)
 * @notes       Não conter SQL. Toda consulta deve ficar nos Repositories.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\CategoriaPostRepository;
use App\Repositories\PostRepository;

final class PostsService
{
    public function __construct(
        private PostRepository $posts,
        private CategoriaPostRepository $categorias,
    ) {
    }

    /**
     * ViewModel da Central de Posts (Admin).
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function getIndexViewModel(array $query): array
    {
        $filters = $this->normalizeFilters($query);

        $page = $this->readInt($query, ['pagina', 'page'], 1);
        $perPage = $this->readInt($query, ['por_pagina', 'per_page'], 10);

        $page = $this->clampInt($page, 1, 9999);
        $perPage = $this->clampInt($perPage, 5, 50);

        [$sort, $dir] = $this->normalizeSortDir(
            (string)($query['sort'] ?? 'data'),
            (string)($query['dir'] ?? 'desc')
        );

        $summary = $this->posts->summaryFiltered($filters);
        $pagination = $this->posts->paginateAdmin($filters, $page, $perPage, $sort, $dir);
        $categorias = $this->categorias->listForSelect();

        return [
            'title' => 'Posts',
            'filters' => $filters,
            'sort' => $sort,
            'dir' => $dir,
            'summary' => $summary,
            'pagination' => $pagination,
            'categorias' => $categorias,
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status:string,categoria:int,destaque:string,busca:string}
     */
    private function normalizeFilters(array $query): array
    {
        return [
            'status' => trim((string)($query['status'] ?? '')),
            'categoria' => (int)($query['categoria'] ?? 0),
            'destaque' => trim((string)($query['destaque'] ?? '')),
            'busca' => trim((string)($query['busca'] ?? '')),
        ];
    }

    /**
     * @return array{0:string,1:string} sort, dir
     */
    private function normalizeSortDir(string $sort, string $dir): array
    {
        $sort = trim($sort);
        $dir = strtolower(trim($dir));

        $allowedSort = [
            'id',
            'titulo',
            'categoria',
            'status',
            'data',
            'views',
            'curtidas',
            'comentarios',
        ];

        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'data';
        }

        $dir = $dir === 'asc' ? 'asc' : 'desc';

        return [$sort, $dir];
    }

    /**
     * @param array<string,mixed> $query
     * @param array<int,string> $keys
     */
    private function readInt(array $query, array $keys, int $default): int
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $query)) {
                return (int)$query[$k];
            }
        }
        return $default;
    }

    private function clampInt(int $value, int $min, int $max): int
    {
        if ($value < $min) return $min;
        if ($value > $max) return $max;
        return $value;
    }
}