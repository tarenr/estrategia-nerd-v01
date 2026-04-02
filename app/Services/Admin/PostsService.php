<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Services/Admin/PostsService.php
 * @project     Estrategia Nerd
 * @purpose     Service da Central de Posts (Admin)
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\CategoriaPostRepository;
use App\Repositories\PostRepository;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class PostsService
{
    public function __construct(
        private PostRepository $posts,
        private CategoriaPostRepository $categorias,
    ) {
    }

    public function getIndexViewModel(array $query): array
    {
        $filters = $this->normalizeFilters($query);
        $page = $this->clampInt($this->readInt($query, ['pagina', 'page'], 1), 1, 9999);
        $perPage = $this->clampInt($this->readInt($query, ['por_pagina', 'per_page'], 10), 5, 50);
        [$sort, $dir] = $this->normalizeSortDir((string) ($query['sort'] ?? 'data'), (string) ($query['dir'] ?? 'desc'));

        return [
            'title' => 'Posts',
            'filters' => $filters,
            'sort' => $sort,
            'dir' => $dir,
            'summary' => $this->posts->summaryFiltered($filters),
            'pagination' => $this->posts->paginateAdmin($filters, $page, $perPage, $sort, $dir),
            'categorias' => $this->categorias->listForSelect(),
        ];
    }

    public function getCreateViewModel(array $old = [], array $errors = []): array
    {
        return $this->buildFormViewModel('create', $this->normalizeForm($old), $errors);
    }

    public function getEditViewModel(int $id, array $old = [], array $errors = []): ?array
    {
        $post = $this->posts->findAdminById($id);
        if ($post === null) {
            return null;
        }

        $form = $old !== []
            ? array_replace($this->mapPostToForm($post), $this->normalizeForm($old, $id))
            : $this->mapPostToForm($post);

        return $this->buildFormViewModel('edit', $form, $errors);
    }

    public function createPost(array $input, ?int $authorId): array
    {
        $form = $this->normalizeForm($input);
        [$categorias, $categoriasById] = $this->categoriaMaps();
        $errors = $this->validateForm($form, $categoriasById);

        if ($errors !== []) {
            return [
                'ok' => false,
                'viewModel' => $this->buildFormViewModel('create', $form, $errors, $categorias),
            ];
        }

        $slug = $this->posts->nextAvailableSlug($this->slugify($form['slug'] !== '' ? $form['slug'] : $form['titulo']));
        $categoriaSelecionada = $categoriasById[(int) $form['categoria_post_id']] ?? null;

        $postId = $this->posts->insertAdmin([
            'titulo' => (string) $form['titulo'],
            'slug' => $slug,
            'resumo' => (string) $form['resumo'],
            'conteudo' => (string) $form['conteudo'],
            'categoria' => $this->resolveLegacyCategoriaSlug((string) ($categoriaSelecionada['slug'] ?? '')),
            'categoria_post_id' => (int) $form['categoria_post_id'],
            'imagem_capa' => (string) $form['imagem_capa'],
            'imagem_thumb' => (string) $form['imagem_thumb'],
            'autor_id' => $authorId ?: 1,
            'data_publicacao' => $this->normalizeDateTimeForDatabase((string) $form['data_publicacao']) ?? date('Y-m-d H:i:s'),
            'tempo_leitura' => $this->resolveReadingTime((string) $form['conteudo'], (int) $form['tempo_leitura']),
            'seo_title' => (string) $form['seo_title'],
            'seo_description' => (string) $form['seo_description'],
            'seo_keywords' => (string) $form['seo_keywords'],
            'tags' => (string) $form['tags'],
            'status' => (string) $form['status'],
            'destaque' => (int) $form['destaque'],
        ]);

        return [
            'ok' => true,
            'id' => $postId,
            'slug' => $slug,
        ];
    }

    public function updatePost(int $id, array $input, ?int $authorId): array
    {
        $post = $this->posts->findAdminById($id);
        if ($post === null) {
            return ['ok' => false, 'not_found' => true];
        }

        $form = $this->normalizeForm($input, $id);
        [$categorias, $categoriasById] = $this->categoriaMaps();
        $errors = $this->validateForm($form, $categoriasById, $id);

        if ($errors !== []) {
            return [
                'ok' => false,
                'viewModel' => $this->buildFormViewModel('edit', $form, $errors, $categorias),
            ];
        }

        $slug = $this->posts->nextAvailableSlug($this->slugify($form['slug'] !== '' ? $form['slug'] : $form['titulo']), $id);
        $categoriaSelecionada = $categoriasById[(int) $form['categoria_post_id']] ?? null;

        $this->posts->updateAdmin($id, [
            'titulo' => (string) $form['titulo'],
            'slug' => $slug,
            'resumo' => (string) $form['resumo'],
            'conteudo' => (string) $form['conteudo'],
            'categoria' => $this->resolveLegacyCategoriaSlug((string) ($categoriaSelecionada['slug'] ?? '')),
            'categoria_post_id' => (int) $form['categoria_post_id'],
            'imagem_capa' => (string) $form['imagem_capa'],
            'imagem_thumb' => (string) $form['imagem_thumb'],
            'autor_id' => $authorId ?: (int) ($post['autor_id'] ?? 1),
            'data_publicacao' => $this->normalizeDateTimeForDatabase((string) $form['data_publicacao']) ?? date('Y-m-d H:i:s'),
            'tempo_leitura' => $this->resolveReadingTime((string) $form['conteudo'], (int) $form['tempo_leitura']),
            'seo_title' => (string) $form['seo_title'],
            'seo_description' => (string) $form['seo_description'],
            'seo_keywords' => (string) $form['seo_keywords'],
            'tags' => (string) $form['tags'],
            'status' => (string) $form['status'],
            'destaque' => (int) $form['destaque'],
        ]);

        return [
            'ok' => true,
            'id' => $id,
            'slug' => $slug,
        ];
    }

    public function duplicatePost(int $id, ?int $authorId): array
    {
        $post = $this->posts->findAdminById($id);
        if ($post === null) {
            return ['ok' => false, 'not_found' => true];
        }

        $tituloBase = trim((string) ($post['titulo'] ?? ''));
        $tituloCopia = $tituloBase !== '' ? $tituloBase . ' (Copia)' : 'Post (Copia)';
        $slug = $this->posts->nextAvailableSlug($this->slugify($tituloCopia));

        $newId = $this->posts->insertAdmin([
            'titulo' => $tituloCopia,
            'slug' => $slug,
            'resumo' => (string) ($post['resumo'] ?? ''),
            'conteudo' => (string) ($post['conteudo'] ?? ''),
            'categoria' => (string) ($post['categoria'] ?? 'gadgets'),
            'categoria_post_id' => (int) ($post['categoria_post_id'] ?? 0),
            'imagem_capa' => (string) ($post['imagem_capa'] ?? ''),
            'imagem_thumb' => (string) ($post['imagem_thumb'] ?? ''),
            'autor_id' => $authorId ?: (int) ($post['autor_id'] ?? 1),
            'data_publicacao' => date('Y-m-d H:i:s'),
            'tempo_leitura' => max(1, (int) ($post['tempo_leitura'] ?? 5)),
            'seo_title' => (string) ($post['seo_title'] ?? ''),
            'seo_description' => (string) ($post['seo_description'] ?? ''),
            'seo_keywords' => (string) ($post['seo_keywords'] ?? ''),
            'tags' => (string) ($post['tags'] ?? ''),
            'status' => 'rascunho',
            'destaque' => 0,
        ]);

        return [
            'ok' => true,
            'id' => $newId,
            'slug' => $slug,
        ];
    }

    public function getDeleteViewModel(int $id): ?array
    {
        $post = $this->posts->findAdminById($id);
        if ($post === null) {
            return null;
        }

        return [
            'title' => 'Excluir Post',
            'post' => $post,
        ];
    }

    public function deletePost(int $id): array
    {
        $post = $this->posts->findAdminById($id);
        if ($post === null) {
            return ['ok' => false, 'not_found' => true];
        }

        $imageCandidates = array_values(array_filter([
            (string) ($post['imagem_capa'] ?? ''),
            (string) ($post['imagem_thumb'] ?? ''),
        ], static fn (string $value): bool => trim($value) !== ''));

        foreach ($imageCandidates as $imagePath) {
            $resolved = $this->resolveUploadFileForDelete($imagePath);
            if ($resolved !== null && is_file($resolved)) {
                @unlink($resolved);
            }
        }

        $this->posts->deleteById($id);

        return [
            'ok' => true,
            'id' => $id,
        ];
    }

    private function buildFormViewModel(string $mode, array $form, array $errors = [], ?array $categorias = null): array
    {
        $isEdit = $mode === 'edit';

        return [
            'title' => $isEdit ? 'Editar Post' : 'Criar Post',
            'mode' => $mode,
            'form' => $form,
            'errors' => $errors,
            'categorias' => $categorias ?? $this->categorias->listForSelect(),
        ];
    }

    private function mapPostToForm(array $post): array
    {
        return [
            'id' => (int) ($post['id'] ?? 0),
            'titulo' => trim((string) ($post['titulo'] ?? '')),
            'slug' => trim((string) ($post['slug'] ?? '')),
            'resumo' => trim((string) ($post['resumo'] ?? '')),
            'conteudo' => trim((string) ($post['conteudo'] ?? '')),
            'categoria_post_id' => (int) ($post['categoria_post_id'] ?? 0),
            'imagem_capa' => trim((string) ($post['imagem_capa'] ?? '')),
            'imagem_thumb' => trim((string) ($post['imagem_thumb'] ?? '')),
            'seo_title' => trim((string) ($post['seo_title'] ?? '')),
            'seo_description' => trim((string) ($post['seo_description'] ?? '')),
            'seo_keywords' => trim((string) ($post['seo_keywords'] ?? '')),
            'tags' => trim((string) ($post['tags'] ?? '')),
            'status' => trim((string) ($post['status'] ?? 'rascunho')),
            'destaque' => (int) ($post['destaque'] ?? 0) === 1 ? 1 : 0,
            'data_publicacao' => $this->formatDateTimeForInput((string) ($post['data_publicacao'] ?? '')),
            'tempo_leitura' => max(1, (int) ($post['tempo_leitura'] ?? 5)),
        ];
    }

    private function normalizeForm(array $input, int $id = 0): array
    {
        $agora = new DateTimeImmutable('now');

        return [
            'id' => $id > 0 ? $id : (int) ($input['id'] ?? 0),
            'titulo' => trim((string) ($input['titulo'] ?? '')),
            'slug' => trim((string) ($input['slug'] ?? '')),
            'resumo' => trim((string) ($input['resumo'] ?? '')),
            'conteudo' => trim((string) ($input['conteudo'] ?? $input['conteudoHidden'] ?? '')),
            'categoria_post_id' => (int) ($input['categoria_post_id'] ?? 0),
            'imagem_capa' => trim((string) ($input['imagem_capa'] ?? '')),
            'imagem_thumb' => trim((string) ($input['imagem_thumb'] ?? '')),
            'seo_title' => trim((string) ($input['seo_title'] ?? '')),
            'seo_description' => trim((string) ($input['seo_description'] ?? '')),
            'seo_keywords' => trim((string) ($input['seo_keywords'] ?? '')),
            'tags' => trim((string) ($input['tags'] ?? '')),
            'status' => trim((string) ($input['status'] ?? 'rascunho')),
            'destaque' => isset($input['destaque']) ? 1 : 0,
            'data_publicacao' => trim((string) ($input['data_publicacao'] ?? $agora->format('Y-m-d\TH:i'))),
            'tempo_leitura' => max(1, (int) ($input['tempo_leitura'] ?? 5)),
        ];
    }

    private function validateForm(array $form, array $categoriasById, ?int $ignoreId = null): array
    {
        $errors = [];

        if ($form['titulo'] === '') {
            $errors['titulo'] = 'Informe o titulo do post.';
        } elseif (mb_strlen((string) $form['titulo']) > 200) {
            $errors['titulo'] = 'O titulo deve ter no maximo 200 caracteres.';
        }

        if ($this->slugify((string) ($form['slug'] !== '' ? $form['slug'] : $form['titulo'])) === '') {
            $errors['slug'] = 'Nao foi possivel gerar um slug valido para o post.';
        }

        if ($this->plainTextFromHtml((string) $form['conteudo']) === '') {
            $errors['conteudo'] = 'Informe o conteudo do post.';
        }

        if (!isset($categoriasById[(int) $form['categoria_post_id']])) {
            $errors['categoria_post_id'] = 'Selecione uma categoria valida.';
        }

        if (!in_array($form['status'], ['publicado', 'rascunho', 'agendado'], true)) {
            $errors['status'] = 'Selecione um status valido.';
        }

        $dataPublicacao = $this->normalizeDateTimeForDatabase((string) $form['data_publicacao']);
        if ($dataPublicacao === null) {
            $errors['data_publicacao'] = 'Informe uma data de publicacao valida.';
        } elseif ($form['status'] === 'agendado' && strtotime($dataPublicacao) <= time()) {
            $errors['data_publicacao'] = 'Posts agendados precisam de uma data futura.';
        }

        if (mb_strlen((string) $form['seo_title']) > 200) {
            $errors['seo_title'] = 'O SEO title deve ter no maximo 200 caracteres.';
        }

        if (mb_strlen((string) $form['seo_description']) > 300) {
            $errors['seo_description'] = 'A SEO description deve ter no maximo 300 caracteres.';
        }

        return $errors;
    }

    private function categoriaMaps(): array
    {
        $categorias = $this->categorias->listForSelect();
        $categoriasById = [];

        foreach ($categorias as $categoria) {
            $categoriasById[(int) ($categoria['id'] ?? 0)] = $categoria;
        }

        return [$categorias, $categoriasById];
    }

    private function resolveUploadFileForDelete(string $path): ?string
    {
        $raw = trim($path);
        if ($raw === '') {
            return null;
        }

        $parsed = parse_url($raw);
        if (is_array($parsed) && isset($parsed['path']) && is_string($parsed['path'])) {
            $raw = $parsed['path'];
        }

        $raw = ltrim($raw, '/\\');
        if ($raw === '') {
            return null;
        }

        if (!str_starts_with($raw, 'uploads/')) {
            $raw = 'uploads/' . basename($raw);
        }

        $publicRoot = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public';
        $uploadsDir = $publicRoot . DIRECTORY_SEPARATOR . 'uploads';
        $target = $publicRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw);

        $uploadsReal = realpath($uploadsDir);
        if ($uploadsReal === false) {
            return null;
        }

        $targetReal = realpath($target);
        if ($targetReal !== false) {
            return str_starts_with($targetReal, $uploadsReal) && is_file($targetReal) ? $targetReal : null;
        }

        $candidateDir = realpath(dirname($target));
        if ($candidateDir === false || !str_starts_with($candidateDir, $uploadsReal)) {
            return null;
        }

        return is_file($target) ? $target : null;
    }

    private function normalizeFilters(array $query): array
    {
        return [
            'status' => trim((string) ($query['status'] ?? '')),
            'categoria' => (int) ($query['categoria'] ?? 0),
            'destaque' => trim((string) ($query['destaque'] ?? '')),
            'busca' => trim((string) ($query['busca'] ?? '')),
        ];
    }

    private function normalizeSortDir(string $sort, string $dir): array
    {
        $allowedSort = ['id', 'titulo', 'categoria', 'status', 'data', 'views', 'curtidas', 'comentarios'];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'data';
        }

        return [$sort, strtolower(trim($dir)) === 'asc' ? 'asc' : 'desc'];
    }

    private function resolveReadingTime(string $conteudo, int $tempoLeitura): int
    {
        $texto = $this->plainTextFromHtml($conteudo);
        $palavras = $texto === '' ? 0 : count(array_filter(preg_split('/\s+/', $texto) ?: []));
        $calculado = max(1, (int) ceil($palavras / 200));

        return max($calculado, $tempoLeitura > 0 ? $tempoLeitura : 1);
    }

    private function resolveLegacyCategoriaSlug(string $slug): string
    {
        $allowed = ['gadgets', 'hardware', 'games', 'cultura', 'dicas', 'lifestyle'];
        return in_array($slug, $allowed, true) ? $slug : 'gadgets';
    }

    private function formatDateTimeForInput(string $value): string
    {
        $normalized = $this->normalizeDateTimeForDatabase($value);
        if ($normalized === null) {
            return (new DateTimeImmutable('now'))->format('Y-m-d\TH:i');
        }

        return (new DateTimeImmutable($normalized))->format('Y-m-d\TH:i');
    }

    private function normalizeDateTimeForDatabase(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof DateTimeImmutable) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        try {
            return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    private function plainTextFromHtml(string $html): string
    {
        $text = trim(strip_tags($html));
        return preg_replace('/\s+/', ' ', $text) ?? $text;
    }

    private function slugify(string $value): string
    {
        $value = trim(mb_strtolower($value));
        if ($value === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $normalized = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($normalized) && $normalized !== '') {
                $value = $normalized;
            }
        }

        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
        return trim(mb_substr($value, 0, 190), '-');
    }

    private function readInt(array $query, array $keys, int $default): int
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $query)) {
                return (int) $query[$key];
            }
        }

        return $default;
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
