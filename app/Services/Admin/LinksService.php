<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\LinkRepository;

final class LinksService
{
    public function __construct(
        private LinkRepository $links,
        private MidiaService $midia,
    )
    {
    }

    public function getIndexViewModel(array $query = []): array
    {
        $filters = $this->normalizeFilters($query);
        $page = $this->clampInt((int) ($query['page'] ?? 1), 1, 9999);
        $perPage = $this->clampInt((int) ($query['per_page'] ?? 10), 5, 50);
        [$sort, $dir] = $this->normalizeSortDir((string) ($query['sort'] ?? 'posicao'), (string) ($query['dir'] ?? 'asc'));

        $filteredItems = $this->links->listAdmin($filters, $sort, $dir);
        $pagination = $this->links->paginateAdmin($filters, $page, $perPage, $sort, $dir);

        $total = count($filteredItems);
        $ativos = count(array_filter($filteredItems, static fn (array $item): bool => (string) ($item['status'] ?? '') === 'ativo'));
        $destaques = count(array_filter($filteredItems, static fn (array $item): bool => (int) ($item['destaque'] ?? 0) === 1));
        $expirados = count(array_filter($filteredItems, static fn (array $item): bool => (string) ($item['status'] ?? '') === 'expirado'));
        $sociais = count(array_filter($filteredItems, static fn (array $item): bool => (string) ($item['tipo'] ?? '') === 'rede_social'));

        return [
            'title' => 'Links',
            'items' => $pagination['items'] ?? [],
            'filters' => $filters,
            'sort' => $sort,
            'dir' => $dir,
            'pagination' => $pagination,
            'summary' => [
                'total' => $total,
                'ativos' => $ativos,
                'destaques' => $destaques,
                'expirados' => $expirados,
                'sociais' => $sociais,
            ],
        ];
    }

    public function getCreateViewModel(array $old = [], array $errors = []): array
    {
        return $this->buildFormViewModel('create', $this->normalizeForm($old), $errors);
    }

    public function getEditViewModel(int $id, array $old = [], array $errors = []): ?array
    {
        $link = $this->links->findById($id);
        if ($link === null) {
            return null;
        }

        $form = $old !== []
            ? array_replace($this->mapLinkToForm($link), $this->normalizeForm($old, $id))
            : $this->mapLinkToForm($link);

        return $this->buildFormViewModel('edit', $form, $errors, $link);
    }

    public function getDeleteViewModel(int $id): ?array
    {
        $link = $this->links->findById($id);
        if ($link === null) {
            return null;
        }

        return [
            'title' => 'Excluir Link',
            'link' => $link,
        ];
    }

    public function createLink(array $input): array
    {
        $form = $this->normalizeForm($input);
        $errors = $this->validateForm($form);

        if ($errors === []) {
            $slugPreview = $this->slugify($form['slug'] !== '' ? $form['slug'] : $form['titulo']);
            $this->applyImageUpload($form, $_FILES ?? [], $errors, $slugPreview);
        }

        if ($errors !== []) {
            return ['ok' => false, 'viewModel' => $this->buildFormViewModel('create', $form, $errors)];
        }

        $slugBase = $this->slugify($form['slug'] !== '' ? $form['slug'] : $form['titulo']);
        $slug = $this->links->nextAvailableSlug($slugBase);
        $id = $this->links->insertAdmin([
            'titulo' => $form['titulo'],
            'slug' => $slug,
            'url' => $form['url'],
            'tipo' => $form['tipo'],
            'descricao' => $form['descricao'],
            'imagem' => $form['imagem'],
            'posicao' => $form['posicao'],
            'status' => $form['status'],
            'destaque' => $form['destaque'],
            'expira_em' => $form['expira_em'],
            'observacao_status' => $form['observacao_status'],
        ]);

        return ['ok' => true, 'id' => $id, 'slug' => $slug];
    }

    public function updateLink(int $id, array $input): array
    {
        $link = $this->links->findById($id);
        if ($link === null) {
            return ['ok' => false, 'not_found' => true];
        }

        $form = $this->normalizeForm($input, $id);
        $errors = $this->validateForm($form, $id);

        if ($errors === []) {
            $slugPreview = $this->slugify($form['slug'] !== '' ? $form['slug'] : $form['titulo']);
            $this->applyImageUpload($form, $_FILES ?? [], $errors, $slugPreview, $link);
        }

        if ($errors !== []) {
            return ['ok' => false, 'viewModel' => $this->buildFormViewModel('edit', $form, $errors, $link)];
        }

        $slugBase = $this->slugify($form['slug'] !== '' ? $form['slug'] : $form['titulo']);
        $slug = $this->links->nextAvailableSlug($slugBase, $id);
        $this->links->updateAdmin($id, [
            'titulo' => $form['titulo'],
            'slug' => $slug,
            'url' => $form['url'],
            'tipo' => $form['tipo'],
            'descricao' => $form['descricao'],
            'imagem' => $form['imagem'],
            'posicao' => $form['posicao'],
            'status' => $form['status'],
            'destaque' => $form['destaque'],
            'expira_em' => $form['expira_em'],
            'observacao_status' => $form['observacao_status'],
        ]);

        return ['ok' => true, 'id' => $id, 'slug' => $slug];
    }

    public function deleteLink(int $id): array
    {
        $link = $this->links->findById($id);
        if ($link === null) {
            return ['ok' => false, 'not_found' => true];
        }

        $this->links->deleteById($id);
        return ['ok' => true];
    }

    private function buildFormViewModel(string $mode, array $form, array $errors = [], ?array $link = null): array
    {
        return [
            'title' => $mode === 'edit' ? 'Editar Link' : 'Criar Link',
            'mode' => $mode,
            'form' => $form,
            'errors' => $errors,
            'link' => $link,
            'media_items' => $this->midia->recentImages(12),
        ];
    }

    private function applyImageUpload(array &$form, array $files, array &$errors, string $slug, ?array $existingLink = null): void
    {
        $slug = $slug !== '' ? $slug : 'link';
        $result = $this->midia->storeUploadedImage($files['imagem_upload'] ?? null, 'links', $slug . '-cover', true);
        if (($result['ok'] ?? false) !== true) {
            $errors['imagem'] = (string) ($result['error'] ?? 'Falha no upload da imagem do link.');
            return;
        }

        if (($result['skipped'] ?? false) === true) {
            return;
        }

        $newPath = trim((string) ($result['path'] ?? ''));
        if ($newPath === '') {
            return;
        }

        $oldPath = trim((string) ($form['imagem'] ?? ''));
        $form['imagem'] = $newPath;

        if ($existingLink !== null) {
            $fallbackOld = trim((string) ($existingLink['imagem'] ?? ''));
            if ($oldPath === '') {
                $oldPath = $fallbackOld;
            }
        }

        if ($oldPath !== '' && $oldPath !== $newPath) {
            $resolved = $this->resolveUploadFileForDelete($oldPath);
            if ($resolved !== null && is_file($resolved)) {
                @unlink($resolved);
            }
        }
    }

    private function mapLinkToForm(array $link): array
    {
        return [
            'id' => (int) ($link['id'] ?? 0),
            'titulo' => trim((string) ($link['titulo'] ?? '')),
            'slug' => trim((string) ($link['slug'] ?? '')),
            'url' => trim((string) ($link['url'] ?? '')),
            'tipo' => trim((string) ($link['tipo'] ?? 'conteudo')),
            'descricao' => trim((string) ($link['descricao'] ?? '')),
            'imagem' => trim((string) ($link['imagem'] ?? '')),
            'posicao' => (int) ($link['posicao'] ?? 0),
            'status' => trim((string) ($link['status'] ?? 'ativo')),
            'destaque' => (int) ($link['destaque'] ?? 0) === 1 ? 1 : 0,
            'expira_em' => $this->toDateTimeLocal((string) ($link['expira_em'] ?? '')),
            'observacao_status' => trim((string) ($link['observacao_status'] ?? '')),
        ];
    }

    private function normalizeForm(array $input, int $id = 0): array
    {
        $tipo = trim((string) ($input['tipo'] ?? 'conteudo'));
        if (!in_array($tipo, ['afiliado', 'oferta', 'conteudo', 'rede_social', 'servico'], true)) {
            $tipo = 'conteudo';
        }

        $status = trim((string) ($input['status'] ?? 'ativo'));
        if (!in_array($status, ['ativo', 'oculto', 'expirado', 'quebrado'], true)) {
            $status = 'ativo';
        }

        return [
            'id' => $id > 0 ? $id : (int) ($input['id'] ?? 0),
            'titulo' => trim((string) ($input['titulo'] ?? '')),
            'slug' => trim((string) ($input['slug'] ?? '')),
            'url' => trim((string) ($input['url'] ?? '')),
            'tipo' => $tipo,
            'descricao' => trim((string) ($input['descricao'] ?? '')),
            'imagem' => trim((string) ($input['imagem'] ?? '')),
            'posicao' => max(0, (int) ($input['posicao'] ?? 0)),
            'status' => $status,
            'destaque' => (int) ($input['destaque'] ?? 0) === 1 ? 1 : 0,
            'expira_em' => $this->normalizeDateTime((string) ($input['expira_em'] ?? '')),
            'observacao_status' => trim((string) ($input['observacao_status'] ?? '')),
        ];
    }

    private function validateForm(array $form, ?int $ignoreId = null): array
    {
        $errors = [];

        if ($form['titulo'] === '') {
            $errors['titulo'] = 'Informe o titulo do link.';
        } elseif (mb_strlen($form['titulo']) > 150) {
            $errors['titulo'] = 'O titulo deve ter no maximo 150 caracteres.';
        }

        $slugBase = $this->slugify($form['slug'] !== '' ? $form['slug'] : $form['titulo']);
        if ($slugBase === '') {
            $errors['slug'] = 'Nao foi possivel gerar um slug valido para o link.';
        }

        if ($form['url'] === '') {
            $errors['url'] = 'Informe a URL de destino.';
        } elseif (!$this->isValidUrl($form['url'])) {
            $errors['url'] = 'Informe uma URL valida. Use http(s):// ou um caminho interno iniciando com /.';
        }

        if ($form['descricao'] !== '' && mb_strlen($form['descricao']) > 255) {
            $errors['descricao'] = 'A descricao deve ter no maximo 255 caracteres.';
        }

        if ($form['imagem'] !== '' && mb_strlen($form['imagem']) > 255) {
            $errors['imagem'] = 'O caminho da imagem deve ter no maximo 255 caracteres.';
        }

        if ($form['observacao_status'] !== '' && mb_strlen($form['observacao_status']) > 255) {
            $errors['observacao_status'] = 'A observacao deve ter no maximo 255 caracteres.';
        }

        return $errors;
    }

    private function normalizeFilters(array $query): array
    {
        return [
            'busca' => trim((string) ($query['busca'] ?? '')),
            'tipo' => trim((string) ($query['tipo'] ?? '')),
            'status' => trim((string) ($query['status'] ?? '')),
            'destaque' => trim((string) ($query['destaque'] ?? '')),
        ];
    }

    private function normalizeSortDir(string $sort, string $dir): array
    {
        $allowedSort = ['titulo', 'tipo', 'status', 'posicao', 'expira_em', 'updated_at'];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'posicao';
        }

        return [$sort, strtolower(trim($dir)) === 'desc' ? 'desc' : 'asc'];
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
        return trim(mb_substr($value, 0, 150), '-');
    }

    private function normalizeDateTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function toDateTimeLocal(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }

        return date('Y-m-d\TH:i', $timestamp);
    }

    private function isValidUrl(string $value): bool
    {
        if (str_starts_with($value, '/')) {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
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
        if ($raw === '' || !str_starts_with($raw, 'uploads/')) {
            return null;
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
}
