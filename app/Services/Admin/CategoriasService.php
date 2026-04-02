<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\CategoriaPostRepository;

final class CategoriasService
{
    public function __construct(private CategoriaPostRepository $categorias)
    {
    }

    public function getIndexViewModel(array $query = []): array
    {
        $filters = $this->normalizeFilters($query);
        $page = $this->clampInt((int) ($query['page'] ?? 1), 1, 9999);
        $perPage = $this->clampInt((int) ($query['per_page'] ?? 10), 5, 50);
        [$sort, $dir] = $this->normalizeSortDir((string) ($query['sort'] ?? 'ordem'), (string) ($query['dir'] ?? 'asc'));
        $filteredItems = $this->categorias->listAdmin($filters);
        $pagination = $this->categorias->paginateAdmin($filters, $page, $perPage, $sort, $dir);
        $total = count($filteredItems);
        $ativos = count(array_filter($filteredItems, static fn (array $item): bool => (int) ($item['ativo'] ?? 0) === 1));
        $inativos = max(0, $total - $ativos);
        $comPosts = count(array_filter($filteredItems, static fn (array $item): bool => (int) ($item['total_posts'] ?? 0) > 0));

        return [
            'title' => 'Categorias',
            'items' => $pagination['items'] ?? [],
            'filters' => $filters,
            'sort' => $sort,
            'dir' => $dir,
            'pagination' => $pagination,
            'summary' => [
                'total' => $total,
                'ativas' => $ativos,
                'inativas' => $inativos,
                'com_posts' => $comPosts,
            ],
        ];
    }

    public function getCreateViewModel(array $old = [], array $errors = []): array
    {
        return $this->buildFormViewModel('create', $this->normalizeForm($old), $errors);
    }

    public function getEditViewModel(int $id, array $old = [], array $errors = []): ?array
    {
        $categoria = $this->categorias->findById($id);
        if ($categoria === null) {
            return null;
        }

        $form = $old !== []
            ? array_replace($this->mapCategoriaToForm($categoria), $this->normalizeForm($old, $id))
            : $this->mapCategoriaToForm($categoria);

        return $this->buildFormViewModel('edit', $form, $errors, $categoria);
    }

    public function getDeleteViewModel(int $id): ?array
    {
        $categoria = $this->categorias->findById($id);
        if ($categoria === null) {
            return null;
        }

        return [
            'title' => 'Excluir Categoria',
            'categoria' => $categoria,
        ];
    }

    public function createCategoria(array $input): array
    {
        $form = $this->normalizeForm($input);
        $errors = $this->validateForm($form);

        if ($errors !== []) {
            return ['ok' => false, 'viewModel' => $this->buildFormViewModel('create', $form, $errors)];
        }

        $slug = $this->categorias->nextAvailableSlug($this->slugify($form['slug'] !== '' ? $form['slug'] : $form['nome']));
        $id = $this->categorias->insertAdmin(['nome' => $form['nome'], 'slug' => $slug, 'cor' => $form['cor'], 'ativo' => $form['ativo'], 'ordem' => $form['ordem']]);

        return ['ok' => true, 'id' => $id, 'slug' => $slug];
    }

    public function updateCategoria(int $id, array $input): array
    {
        $categoria = $this->categorias->findById($id);
        if ($categoria === null) {
            return ['ok' => false, 'not_found' => true];
        }

        $form = $this->normalizeForm($input, $id);
        $errors = $this->validateForm($form, $id);

        if ($errors !== []) {
            return ['ok' => false, 'viewModel' => $this->buildFormViewModel('edit', $form, $errors, $categoria)];
        }

        $slug = $this->categorias->nextAvailableSlug($this->slugify($form['slug'] !== '' ? $form['slug'] : $form['nome']), $id);
        $this->categorias->updateAdmin($id, ['nome' => $form['nome'], 'slug' => $slug, 'cor' => $form['cor'], 'ativo' => $form['ativo'], 'ordem' => $form['ordem']]);

        return ['ok' => true, 'id' => $id, 'slug' => $slug];
    }

    public function deleteCategoria(int $id): array
    {
        $categoria = $this->categorias->findById($id);
        if ($categoria === null) {
            return ['ok' => false, 'not_found' => true];
        }

        $totalPosts = (int) ($categoria['total_posts'] ?? 0);
        if ($totalPosts > 0) {
            $this->categorias->deactivateById($id);
            return ['ok' => true, 'mode' => 'deactivated'];
        }

        $this->categorias->deleteById($id);
        return ['ok' => true, 'mode' => 'deleted'];
    }

    private function buildFormViewModel(string $mode, array $form, array $errors = [], ?array $categoria = null): array
    {
        return ['title' => $mode === 'edit' ? 'Editar Categoria' : 'Criar Categoria', 'mode' => $mode, 'form' => $form, 'errors' => $errors, 'categoria' => $categoria];
    }

    private function mapCategoriaToForm(array $categoria): array
    {
        return ['id' => (int) ($categoria['id'] ?? 0), 'nome' => trim((string) ($categoria['nome'] ?? '')), 'slug' => trim((string) ($categoria['slug'] ?? '')), 'cor' => trim((string) ($categoria['cor'] ?? '#00d4ff')), 'ativo' => (int) ($categoria['ativo'] ?? 1) === 1 ? 1 : 0, 'ordem' => (int) ($categoria['ordem'] ?? 0)];
    }

    private function normalizeForm(array $input, int $id = 0): array
    {
        $cor = trim((string) ($input['cor'] ?? '#00d4ff'));
        if ($cor === '') { $cor = '#00d4ff'; }

        return ['id' => $id > 0 ? $id : (int) ($input['id'] ?? 0), 'nome' => trim((string) ($input['nome'] ?? '')), 'slug' => trim((string) ($input['slug'] ?? '')), 'cor' => $cor, 'ativo' => (int) ($input['ativo'] ?? 0) === 1 ? 1 : 0, 'ordem' => max(0, (int) ($input['ordem'] ?? 0))];
    }

    private function validateForm(array $form, ?int $ignoreId = null): array
    {
        $errors = [];
        if ($form['nome'] === '') {
            $errors['nome'] = 'Informe o nome da categoria.';
        } elseif (mb_strlen($form['nome']) > 120) {
            $errors['nome'] = 'O nome deve ter no maximo 120 caracteres.';
        }

        $slugBase = $this->slugify($form['slug'] !== '' ? $form['slug'] : $form['nome']);
        if ($slugBase === '') {
            $errors['slug'] = 'Nao foi possivel gerar um slug valido para a categoria.';
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $form['cor'])) {
            $errors['cor'] = 'Informe uma cor hexadecimal valida no formato #RRGGBB.';
        }

        return $errors;
    }

    private function normalizeFilters(array $query): array
    {
        return ['busca' => trim((string) ($query['busca'] ?? '')), 'status' => trim((string) ($query['status'] ?? ''))];
    }

    private function normalizeSortDir(string $sort, string $dir): array
    {
        $allowedSort = ['nome', 'slug', 'cor', 'ativo', 'ordem', 'total_posts', 'total_views'];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'ordem';
        }

        return [$sort, strtolower(trim($dir)) === 'desc' ? 'desc' : 'asc'];
    }

    private function clampInt(int $value, int $min, int $max): int
    {
        if ($value < $min) { return $min; }
        if ($value > $max) { return $max; }
        return $value;
    }

    private function slugify(string $value): string
    {
        $value = trim(mb_strtolower($value));
        if ($value === '') { return ''; }
        if (function_exists('iconv')) {
            $normalized = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($normalized) && $normalized !== '') { $value = $normalized; }
        }

        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
        return trim(mb_substr($value, 0, 120), '-');
    }
}
