<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\CategoriaPostRepository;
use App\Services\Site\SitemapCacheService;

final class CategoriasService
{
    private const RESERVED_SLUGS = [
        'page',
        'tag',
        'autor',
        'author',
        'search',
        'busca',
        'feed',
        'post',
        'categoria',
        'admin',
        'rss',
        'sitemap',
    ];

    public function __construct(
        private CategoriaPostRepository $categorias,
        private SitemapCacheService $sitemapCache,
    )
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

        return [
            'title' => 'Categorias',
            'items' => $pagination['items'] ?? [],
            'filters' => $filters,
            'sort' => $sort,
            'dir' => $dir,
            'pagination' => $pagination,
            'summary' => $this->buildIndexSummary($filteredItems),
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

        $slug = $this->slugify($form['slug'] !== '' ? $form['slug'] : $form['nome']);
        $id = $this->categorias->insertAdmin([
            'nome' => $form['nome'],
            'slug' => $slug,
            'descricao_publica' => $form['descricao_publica'],
            'seo_title' => $form['seo_title'],
            'seo_description' => $form['seo_description'],
            'cor' => $form['cor'],
            'ativo' => $form['ativo'],
            'indexar' => $form['indexar'],
            'exibir_no_menu' => $form['exibir_no_menu'],
            'ordem' => $form['ordem'],
        ]);

        $this->sitemapCache->refreshQuietly();

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

        $slug = $this->slugify($form['slug'] !== '' ? $form['slug'] : $form['nome']);
        $this->categorias->updateAdmin($id, [
            'nome' => $form['nome'],
            'slug' => $slug,
            'descricao_publica' => $form['descricao_publica'],
            'seo_title' => $form['seo_title'],
            'seo_description' => $form['seo_description'],
            'cor' => $form['cor'],
            'ativo' => $form['ativo'],
            'indexar' => $form['indexar'],
            'exibir_no_menu' => $form['exibir_no_menu'],
            'ordem' => $form['ordem'],
        ]);

        $this->sitemapCache->refreshQuietly();

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
            $this->sitemapCache->refreshQuietly();
            return ['ok' => true, 'mode' => 'deactivated'];
        }

        $this->categorias->deleteById($id);
        $this->sitemapCache->refreshQuietly();
        return ['ok' => true, 'mode' => 'deleted'];
    }

    private function buildFormViewModel(string $mode, array $form, array $errors = [], ?array $categoria = null): array
    {
        return ['title' => $mode === 'edit' ? 'Editar Categoria' : 'Criar Categoria', 'mode' => $mode, 'form' => $form, 'errors' => $errors, 'categoria' => $categoria];
    }

    private function buildIndexSummary(array $items): array
    {
        $total = count($items);
        $ativos = count(array_filter($items, static fn (array $item): bool => (int) ($item['ativo'] ?? 0) === 1));
        $inativos = max(0, $total - $ativos);
        $indexaveis = count(array_filter($items, static fn (array $item): bool => (int) ($item['indexar'] ?? 1) === 1));
        $noindex = max(0, $total - $indexaveis);
        $menu = count(array_filter($items, static fn (array $item): bool => (int) ($item['exibir_no_menu'] ?? 1) === 1));
        $foraMenu = max(0, $total - $menu);
        $comPosts = count(array_filter($items, static fn (array $item): bool => (int) ($item['total_posts'] ?? 0) > 0));
        $semPosts = max(0, $total - $comPosts);
        $totalPosts = 0;
        $totalViews = 0;

        foreach ($items as $item) {
            $totalPosts += (int) ($item['total_posts'] ?? 0);
            $totalViews += (int) ($item['total_views'] ?? 0);
        }

        return [
            'total' => $total,
            'ativas' => $ativos,
            'inativas' => $inativos,
            'indexaveis' => $indexaveis,
            'noindex' => $noindex,
            'menu' => $menu,
            'fora_menu' => $foraMenu,
            'com_posts' => $comPosts,
            'sem_posts' => $semPosts,
            'total_posts_vinculados' => $totalPosts,
            'total_views' => $totalViews,
            'cobertura_ativas' => $total > 0 ? ($ativos / $total) * 100 : 0.0,
            'cobertura_editorial' => $total > 0 ? ($comPosts / $total) * 100 : 0.0,
            'media_posts_por_categoria' => $comPosts > 0 ? ($totalPosts / $comPosts) : 0.0,
            'media_views_por_categoria' => $comPosts > 0 ? ($totalViews / $comPosts) : 0.0,
        ];
    }

    private function mapCategoriaToForm(array $categoria): array
    {
        return [
            'id' => (int) ($categoria['id'] ?? 0),
            'nome' => trim((string) ($categoria['nome'] ?? '')),
            'slug' => trim((string) ($categoria['slug'] ?? '')),
            'descricao_publica' => trim((string) ($categoria['descricao_publica'] ?? '')),
            'seo_title' => trim((string) ($categoria['seo_title'] ?? '')),
            'seo_description' => trim((string) ($categoria['seo_description'] ?? '')),
            'cor' => trim((string) ($categoria['cor'] ?? '#00d4ff')),
            'ativo' => (int) ($categoria['ativo'] ?? 1) === 1 ? 1 : 0,
            'indexar' => (int) ($categoria['indexar'] ?? 1) === 1 ? 1 : 0,
            'exibir_no_menu' => (int) ($categoria['exibir_no_menu'] ?? 1) === 1 ? 1 : 0,
            'ordem' => (int) ($categoria['ordem'] ?? 0),
        ];
    }

    private function normalizeForm(array $input, int $id = 0): array
    {
        $cor = trim((string) ($input['cor'] ?? '#00d4ff'));
        if ($cor === '') { $cor = '#00d4ff'; }

        return [
            'id' => $id > 0 ? $id : (int) ($input['id'] ?? 0),
            'nome' => trim((string) ($input['nome'] ?? '')),
            'slug' => trim((string) ($input['slug'] ?? '')),
            'descricao_publica' => trim((string) ($input['descricao_publica'] ?? '')),
            'seo_title' => trim((string) ($input['seo_title'] ?? '')),
            'seo_description' => trim((string) ($input['seo_description'] ?? '')),
            'cor' => $cor,
            'ativo' => (int) ($input['ativo'] ?? 0) === 1 ? 1 : 0,
            'indexar' => (int) ($input['indexar'] ?? 0) === 1 ? 1 : 0,
            'exibir_no_menu' => (int) ($input['exibir_no_menu'] ?? 0) === 1 ? 1 : 0,
            'ordem' => max(0, (int) ($input['ordem'] ?? 0)),
        ];
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
        } elseif (in_array($slugBase, self::RESERVED_SLUGS, true)) {
            $errors['slug'] = 'Esse slug e reservado pelo blog e nao pode ser usado como categoria.';
        } elseif ($this->categorias->slugExists($slugBase, $ignoreId)) {
            $errors['slug'] = 'Ja existe uma categoria usando esse slug.';
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $form['cor'])) {
            $errors['cor'] = 'Informe uma cor hexadecimal valida no formato #RRGGBB.';
        }

        if ($form['seo_title'] !== '' && mb_strlen($form['seo_title']) > 60) {
            $errors['seo_title'] = 'O SEO title deve ter no maximo 60 caracteres.';
        }

        if ($form['seo_description'] !== '' && mb_strlen($form['seo_description']) > 160) {
            $errors['seo_description'] = 'A SEO description deve ter no maximo 160 caracteres.';
        }

        return $errors;
    }

    private function normalizeFilters(array $query): array
    {
        return ['busca' => trim((string) ($query['busca'] ?? '')), 'status' => trim((string) ($query['status'] ?? ''))];
    }

    private function normalizeSortDir(string $sort, string $dir): array
    {
        $allowedSort = ['nome', 'slug', 'ativo', 'indexar', 'exibir_no_menu', 'ordem', 'total_posts', 'total_views'];
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
