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
use App\Services\Site\SitemapCacheService;
use App\Support\SystemActivityLogger;
use DateTimeImmutable;
use Throwable;

final class PostsService
{
    private const POST_TRASH_RETENTION_DAYS = 15;

    public function __construct(
        private PostRepository $posts,
        private CategoriaPostRepository $categorias,
        private MidiaService $midia,
        private SitemapCacheService $sitemapCache,
    ) {
    }

    public function getIndexViewModel(array $query): array
    {
        $filters = $this->normalizeFilters($query);
        $page = $this->clampInt($this->readInt($query, ['pagina', 'page'], 1), 1, 9999);
        $perPage = $this->clampInt($this->readInt($query, ['por_pagina', 'per_page'], 10), 5, 50);
        [$sort, $dir] = $this->normalizeSortDir((string) ($query['sort'] ?? 'data'), (string) ($query['dir'] ?? 'desc'));
        $summary = $this->decorateIndexSummary($this->posts->summaryFiltered($filters));

        return [
            'title' => 'Posts',
            'filters' => $filters,
            'sort' => $sort,
            'dir' => $dir,
            'summary' => $summary,
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

        return $this->buildFormViewModel('edit', $form, $errors, null, [
            'orphan_files' => $this->findOrphanBodyFiles($form),
        ]);
    }

    public function createPost(array $input, array $files, ?int $authorId): array
    {
        $form = $this->normalizeForm($input);
        [$categorias, $categoriasById] = $this->categoriaMaps();
        $errors = $this->validateForm($form, $categoriasById);
        $slug = $this->posts->nextAvailableSlug($this->slugify($form['slug'] !== '' ? $form['slug'] : $form['titulo']));

        if ($errors === []) {
            $this->applyMediaUploads($form, $files, $errors, $slug);
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'viewModel' => $this->buildFormViewModel('create', $form, $errors, $categorias),
            ];
        }

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
            'proximo_post_id' => (int) ($form['proximo_post_id'] ?? 0),
        ]);

        $this->sitemapCache->refreshQuietly();

        return ['ok' => true, 'id' => $postId, 'slug' => $slug];
    }

    public function updatePost(int $id, array $input, array $files, ?int $authorId): array
    {
        $post = $this->posts->findAdminById($id);
        if ($post === null) {
            return ['ok' => false, 'not_found' => true];
        }

        $form = $this->normalizeForm($input, $id);
        [$categorias, $categoriasById] = $this->categoriaMaps();
        $errors = $this->validateForm($form, $categoriasById, $id);
        $slug = $this->posts->nextAvailableSlug($this->slugify($form['slug'] !== '' ? $form['slug'] : $form['titulo']), $id);

        if ($errors === []) {
            $this->applyMediaUploads($form, $files, $errors, $slug, $post);
        }

        if ($errors === []) {
            $this->migratePostMediaForSlugChange($form, $post, $slug, $errors);
        }

        if ($errors === []) {
            $this->reconcilePostMediaReferences($form, $slug, $errors);
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'viewModel' => $this->buildFormViewModel('edit', $form, $errors, $categorias),
            ];
        }

        $categoriaSelecionada = $categoriasById[(int) $form['categoria_post_id']] ?? null;
        $oldSlug = trim((string) ($post['slug'] ?? ''));

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
            'proximo_post_id' => (int) ($form['proximo_post_id'] ?? 0),
        ]);

        if ($oldSlug !== '' && $oldSlug !== $slug) {
            $this->posts->storeSlugHistory($id, $oldSlug);
        }

        $this->sitemapCache->refreshQuietly();

        return ['ok' => true, 'id' => $id, 'slug' => $slug];
    }

    public function uploadInlineImage(array $input, array $files): array
    {
        $slugBase = $this->resolveInlinePostSlug($input);
        if ($slugBase === '') {
            return ['ok' => false, 'error' => 'Informe um titulo ou slug antes de enviar a imagem do conteudo.'];
        }

        $result = $this->midia->storePostBodyImage($files['imagem'] ?? null, $slugBase);
        if (($result['ok'] ?? false) !== true) {
            return ['ok' => false, 'error' => (string) ($result['error'] ?? 'Falha no upload da imagem.')];
        }

        $path = trim((string) ($result['path'] ?? ''));
        if ($path === '') {
            return ['ok' => false, 'error' => 'Nenhuma imagem foi enviada.'];
        }

        return ['ok' => true, 'path' => $path, 'url' => url('/' . ltrim($path, '/'))];
    }

    public function copyInlineImageFromLibrary(array $input): array
    {
        $slugBase = $this->resolveInlinePostSlug($input);
        if ($slugBase === '') {
            return ['ok' => false, 'error' => 'Informe um titulo ou slug antes de selecionar a imagem da biblioteca.'];
        }

        $sourcePath = trim((string) ($input['path'] ?? ''));
        if ($sourcePath === '') {
            return ['ok' => false, 'error' => 'Selecione uma imagem da biblioteca para continuar.'];
        }

        $result = $this->midia->cloneManagedImageToPost($sourcePath, $slugBase);
        if (($result['ok'] ?? false) !== true) {
            return ['ok' => false, 'error' => (string) ($result['error'] ?? 'Falha ao duplicar a imagem da biblioteca.')];
        }

        $path = trim((string) ($result['path'] ?? ''));
        if ($path === '') {
            return ['ok' => false, 'error' => 'Nao foi possivel preparar a copia da imagem selecionada.'];
        }

        return [
            'ok' => true,
            'path' => $path,
            'url' => url('/' . ltrim($path, '/')),
            'item' => is_array($result['item'] ?? null) ? $result['item'] : null,
        ];
    }

    public function copyLibraryMediaToPost(array $input): array
    {
        $slugBase = $this->resolveInlinePostSlug($input);
        if ($slugBase === '') {
            SystemActivityLogger::write('media', 'copy_library_media_post_failed', [
                'reason' => 'missing_identity',
                'media_type' => (string) ($input['media_type'] ?? ''),
            ]);
            return ['ok' => false, 'error' => 'Informe um titulo ou slug antes de selecionar a midia da biblioteca.'];
        }

        $sourcePath = trim((string) ($input['path'] ?? ''));
        if ($sourcePath === '') {
            SystemActivityLogger::write('media', 'copy_library_media_post_failed', [
                'reason' => 'missing_path',
                'slug' => $slugBase,
                'media_type' => (string) ($input['media_type'] ?? ''),
            ]);
            return ['ok' => false, 'error' => 'Selecione uma midia da biblioteca para continuar.'];
        }

        $type = strtolower(trim((string) ($input['media_type'] ?? '')));
        $options = [];
        if ($type === 'audio') {
            $options['audio_role'] = (string) ($input['audio_role'] ?? '');
        }
        if ($type === 'image') {
            $postRole = strtolower(trim((string) ($input['post_role'] ?? '')));
            if (in_array($postRole, ['capa', 'thumb'], true)) {
                $options['post_role'] = $postRole;
            }
        }

        $result = $this->midia->cloneManagedMediaToPost($sourcePath, $slugBase, $type, $options);
        if (($result['ok'] ?? false) !== true) {
            SystemActivityLogger::write('media', 'copy_library_media_post_failed', [
                'slug' => $slugBase,
                'source_path' => $sourcePath,
                'media_type' => $type,
                'error' => (string) ($result['error'] ?? ''),
            ]);
            return ['ok' => false, 'error' => (string) ($result['error'] ?? 'Falha ao duplicar a midia da biblioteca.')];
        }

        $path = trim((string) ($result['path'] ?? ''));
        if ($path === '') {
            SystemActivityLogger::write('media', 'copy_library_media_post_failed', [
                'slug' => $slugBase,
                'source_path' => $sourcePath,
                'media_type' => $type,
                'reason' => 'empty_target_path',
            ]);
            return ['ok' => false, 'error' => 'Nao foi possivel preparar a copia da midia selecionada.'];
        }

        return [
            'ok' => true,
            'path' => $path,
            'url' => url('/' . ltrim($path, '/')),
            'item' => is_array($result['item'] ?? null) ? $result['item'] : null,
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
            'proximo_post_id' => 0,
        ]);

        $this->sitemapCache->refreshQuietly();

        return ['ok' => true, 'id' => $newId, 'slug' => $slug];
    }

    public function cleanupOrphanBodyFiles(int $id): array
    {
        $post = $this->posts->findAdminById($id);
        if ($post === null) {
            return ['ok' => false, 'not_found' => true];
        }

        $form = $this->mapPostToForm($post);
        $orphans = $this->findOrphanBodyFiles($form);
        $removed = 0;

        foreach ($orphans as $item) {
            $resolved = $this->resolveUploadFileForDelete((string) ($item['relative_path'] ?? ''));
            if ($resolved !== null && is_file($resolved) && @unlink($resolved)) {
                $removed++;
            }
        }

        return ['ok' => true, 'removed' => $removed];
    }

    public function cleanupOrphanBodyImages(int $id): array
    {
        return $this->cleanupOrphanBodyFiles($id);
    }

    public function getDeleteViewModel(int $id): ?array
    {
        $post = $this->posts->findAdminById($id);
        if ($post === null) {
            return null;
        }

        return ['title' => 'Excluir Post', 'post' => $post];
    }

    public function deletePost(int $id, ?array $actor = null): array
    {
        $post = $this->posts->findAdminById($id);
        if ($post === null) {
            return ['ok' => false, 'not_found' => true];
        }

        SystemActivityLogger::write('posts', 'post_delete_started', [
            'post_id' => $id,
            'slug' => (string) ($post['slug'] ?? ''),
            'title' => (string) ($post['titulo'] ?? ''),
            'actor' => $this->normalizeDeletionActor($actor),
        ]);

        $trash = $this->movePostMediaToTrash($post, $actor);

        $cleanup = $this->cleanupExpiredPostTrash();

        $this->posts->deleteById($id);
        $this->sitemapCache->refreshQuietly();

        SystemActivityLogger::write('posts', 'post_deleted', [
            'post_id' => $id,
            'slug' => (string) ($post['slug'] ?? ''),
            'title' => (string) ($post['titulo'] ?? ''),
            'trash' => $trash,
            'trash_cleanup' => $cleanup,
            'actor' => $this->normalizeDeletionActor($actor),
        ]);

        return [
            'ok' => true,
            'id' => $id,
            'trash' => $trash,
            'trash_cleanup' => $cleanup,
        ];
    }

    private function applyMediaUploads(array &$form, array $files, array &$errors, string $slug, ?array $existingPost = null): void
    {
        $map = [
            'imagem_capa_upload' => ['field' => 'imagem_capa', 'error_key' => 'imagem_capa', 'role' => 'capa'],
            'imagem_thumb_upload' => ['field' => 'imagem_thumb', 'error_key' => 'imagem_thumb', 'role' => 'thumb'],
        ];

        foreach ($map as $uploadKey => $config) {
            $result = $this->midia->storePostRoleImage($files[$uploadKey] ?? null, $slug, $config['role']);
            if (($result['ok'] ?? false) !== true) {
                $errors[$config['error_key']] = (string) ($result['error'] ?? 'Falha no upload da imagem.');
                continue;
            }

            if (($result['skipped'] ?? false) === true) {
                continue;
            }

            $newPath = trim((string) ($result['path'] ?? ''));
            if ($newPath === '') {
                continue;
            }

            $oldPath = trim((string) ($form[$config['field']] ?? ''));
            $form[$config['field']] = $newPath;

            if ($existingPost !== null) {
                $fallbackOld = trim((string) ($existingPost[$config['field']] ?? ''));
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
    }

    private function resolveInlinePostSlug(array $input): string
    {
        $slugBase = $this->slugify(trim((string) ($input['slug'] ?? '')));
        if ($slugBase !== '') {
            return $slugBase;
        }

        return $this->slugify(trim((string) ($input['titulo'] ?? '')));
    }

    private function movePostMediaToTrash(array $post, ?array $actor = null): array
    {
        $slug = $this->slugify((string) ($post['slug'] ?? ''));
        if ($slug === '') {
            SystemActivityLogger::write('posts', 'post_media_trash_skipped', [
                'post_id' => (int) ($post['id'] ?? 0),
                'reason' => 'missing_slug',
            ]);
            return ['moved' => false, 'reason' => 'missing_slug'];
        }

        $sourceDir = $this->postMediaDirectory($slug);
        if (!is_dir($sourceDir)) {
            SystemActivityLogger::write('posts', 'post_media_trash_skipped', [
                'post_id' => (int) ($post['id'] ?? 0),
                'slug' => $slug,
                'reason' => 'missing_directory',
            ]);
            return ['moved' => false, 'reason' => 'missing_directory'];
        }

        $trashRoot = $this->postTrashRootDirectory();
        if (!is_dir($trashRoot) && !@mkdir($trashRoot, 0777, true) && !is_dir($trashRoot)) {
            SystemActivityLogger::write('posts', 'post_media_trash_failed', [
                'post_id' => (int) ($post['id'] ?? 0),
                'slug' => $slug,
                'reason' => 'trash_unavailable',
            ]);
            return ['moved' => false, 'reason' => 'trash_unavailable'];
        }

        $timestamp = date('Ymd-His');
        $postId = max(0, (int) ($post['id'] ?? 0));
        $folderName = $postId > 0 ? $postId . '_' . $timestamp : 'post_' . $timestamp;
        $destinationDir = $trashRoot . DIRECTORY_SEPARATOR . $folderName;
        $suffix = 2;

        while (file_exists($destinationDir)) {
            $destinationDir = $trashRoot . DIRECTORY_SEPARATOR . $folderName . '_' . $suffix;
            $suffix++;
        }

        if (!@rename($sourceDir, $destinationDir)) {
            SystemActivityLogger::write('posts', 'post_media_trash_failed', [
                'post_id' => $postId,
                'slug' => $slug,
                'reason' => 'move_failed',
            ]);
            return ['moved' => false, 'reason' => 'move_failed'];
        }

        $manifest = [
            'post_id' => $postId,
            'slug' => $slug,
            'titulo' => trim((string) ($post['titulo'] ?? '')),
            'deleted_at' => date('c'),
            'deleted_by' => $this->normalizeDeletionActor($actor),
            'original_media_path' => 'uploads/posts/' . $slug,
            'trash_media_path' => 'uploads/trash/posts/' . basename($destinationDir),
        ];

        @file_put_contents(
            $destinationDir . DIRECTORY_SEPARATOR . 'manifest.json',
            (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        SystemActivityLogger::write('posts', 'post_media_trashed', [
            'post_id' => $postId,
            'slug' => $slug,
            'trash_path' => 'uploads/trash/posts/' . basename($destinationDir),
            'actor' => $this->normalizeDeletionActor($actor),
        ]);

        return [
            'moved' => true,
            'path' => 'uploads/trash/posts/' . basename($destinationDir),
        ];
    }

    private function normalizeDeletionActor(?array $actor): array
    {
        $actor = is_array($actor) ? $actor : [];

        return [
            'id' => isset($actor['id']) && is_numeric($actor['id']) ? (int) $actor['id'] : null,
            'usuario' => trim((string) ($actor['usuario'] ?? '')),
            'nome' => trim((string) ($actor['nome'] ?? '')),
            'email' => trim((string) ($actor['email'] ?? '')),
        ];
    }

    private function postMediaDirectory(string $slug): string
    {
        return dirname(__DIR__, 3)
            . DIRECTORY_SEPARATOR . 'public'
            . DIRECTORY_SEPARATOR . 'uploads'
            . DIRECTORY_SEPARATOR . 'posts'
            . DIRECTORY_SEPARATOR . $slug;
    }

    private function postTrashRootDirectory(): string
    {
        return dirname(__DIR__, 3)
            . DIRECTORY_SEPARATOR . 'public'
            . DIRECTORY_SEPARATOR . 'uploads'
            . DIRECTORY_SEPARATOR . 'trash'
            . DIRECTORY_SEPARATOR . 'posts';
    }

    private function cleanupExpiredPostTrash(int $retentionDays = self::POST_TRASH_RETENTION_DAYS): array
    {
        $trashRoot = $this->postTrashRootDirectory();
        if (!is_dir($trashRoot)) {
            return ['removed' => 0, 'failed' => 0];
        }

        $retentionDays = max(1, $retentionDays);
        $threshold = time() - ($retentionDays * 86400);
        $removed = 0;
        $failed = 0;

        foreach (new \FilesystemIterator($trashRoot, \FilesystemIterator::SKIP_DOTS) as $item) {
            if (!$item instanceof \SplFileInfo || !$item->isDir()) {
                continue;
            }

            $path = $item->getPathname();
            if (!$this->isTrashPostDirectorySafe($path, $trashRoot)) {
                continue;
            }

            $modifiedAt = (int) $item->getMTime();
            if ($modifiedAt > $threshold) {
                continue;
            }

            if ($this->deleteDirectoryRecursively($path, $trashRoot)) {
                $removed++;
            } else {
                $failed++;
            }
        }

        if ($removed > 0 || $failed > 0) {
            SystemActivityLogger::write('posts', 'post_trash_cleanup_run', [
                'retention_days' => $retentionDays,
                'removed' => $removed,
                'failed' => $failed,
            ]);
        }

        return ['removed' => $removed, 'failed' => $failed];
    }

    private function isTrashPostDirectorySafe(string $path, string $trashRoot): bool
    {
        $trashRootReal = realpath($trashRoot);
        $pathReal = realpath($path);
        if ($trashRootReal === false || $pathReal === false) {
            return false;
        }

        if (!str_starts_with($pathReal, $trashRootReal . DIRECTORY_SEPARATOR) && $pathReal !== $trashRootReal) {
            return false;
        }

        return basename($pathReal) !== '' && basename($pathReal) !== '.' && basename($pathReal) !== '..';
    }

    private function deleteDirectoryRecursively(string $path, string $trashRoot): bool
    {
        if (!$this->isTrashPostDirectorySafe($path, $trashRoot)) {
            return false;
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $item) {
                if (!$item instanceof \SplFileInfo) {
                    continue;
                }

                $target = $item->getPathname();
                if ($item->isDir()) {
                    if (!@rmdir($target) && is_dir($target)) {
                        return false;
                    }
                    continue;
                }

                if (!@unlink($target) && is_file($target)) {
                    return false;
                }
            }

            return @rmdir($path) || !is_dir($path);
        } catch (Throwable) {
            return false;
        }
    }

    private function decorateIndexSummary(array $summary): array
    {
        $totalPosts = max(0, (int) ($summary['total_posts'] ?? 0));
        $publicados = max(0, (int) ($summary['publicados'] ?? 0));
        $destaques = max(0, (int) ($summary['destaques'] ?? 0));
        $destaquesPublicados = max(0, (int) ($summary['destaques_publicados'] ?? 0));
        $totalViews = max(0, (int) ($summary['total_views'] ?? 0));
        $totalCurtidas = max(0, (int) ($summary['total_curtidas'] ?? 0));
        $totalComentarios = max(0, (int) ($summary['total_comentarios'] ?? 0));
        $totalInteracoes = $totalCurtidas + $totalComentarios;

        return array_merge($summary, [
            'destaque_cobertura_publicados' => $publicados > 0
                ? round(($destaquesPublicados / $publicados) * 100, 1)
                : 0.0,
            'views_por_post' => $totalPosts > 0
                ? (int) round($totalViews / $totalPosts)
                : 0,
            'views_por_publicado' => $publicados > 0
                ? (int) round($totalViews / $publicados)
                : 0,
            'total_interacoes' => $totalInteracoes,
            'taxa_engajamento' => $totalViews > 0
                ? round(($totalInteracoes / $totalViews) * 100, 1)
                : 0.0,
        ]);
    }

    private function migratePostMediaForSlugChange(array &$form, array $existingPost, string $newSlug, array &$errors): void
    {
        $oldSlug = $this->slugify((string) ($existingPost['slug'] ?? ''));
        $newSlug = $this->slugify($newSlug);

        if ($oldSlug === '' || $newSlug === '' || $oldSlug === $newSlug) {
            return;
        }

        $migration = $this->movePostMediaDirectory($oldSlug, $newSlug);
        if (($migration['ok'] ?? false) !== true) {
            $errors['slug'] = (string) ($migration['error'] ?? 'Nao foi possivel migrar a midia do post ao alterar o slug.');
            return;
        }

        $replacements = is_array($migration['replacements'] ?? null) ? $migration['replacements'] : [];

        foreach (['imagem_capa', 'imagem_thumb'] as $field) {
            $form[$field] = $this->applyPostMediaReplacements((string) ($form[$field] ?? ''), $replacements, $oldSlug, $newSlug);
        }

        $form['conteudo'] = $this->applyPostMediaReplacements((string) ($form['conteudo'] ?? ''), $replacements, $oldSlug, $newSlug);
    }

    private function reconcilePostMediaReferences(array &$form, string $slug, array &$errors): void
    {
        $targetSlug = $this->slugify($slug);
        if ($targetSlug === '') {
            return;
        }

        $candidates = [];
        foreach (['imagem_capa', 'imagem_thumb', 'conteudo'] as $field) {
            $candidates = array_merge($candidates, $this->extractPostMediaSlugs((string) ($form[$field] ?? '')));
        }

        $sourceSlugs = array_values(array_unique(array_filter($candidates, static fn (string $value): bool => $value !== '' && $value !== $targetSlug)));
        foreach ($sourceSlugs as $sourceSlug) {
            $migration = $this->movePostMediaDirectory($sourceSlug, $targetSlug);
            if (($migration['ok'] ?? false) !== true) {
                $errors['slug'] = (string) ($migration['error'] ?? 'Nao foi possivel reconciliar a midia do post com o slug atual.');
                return;
            }

            $replacements = is_array($migration['replacements'] ?? null) ? $migration['replacements'] : [];
            foreach (['imagem_capa', 'imagem_thumb'] as $field) {
                $form[$field] = $this->applyPostMediaReplacements((string) ($form[$field] ?? ''), $replacements, $sourceSlug, $targetSlug);
            }
            $form['conteudo'] = $this->applyPostMediaReplacements((string) ($form['conteudo'] ?? ''), $replacements, $sourceSlug, $targetSlug);
        }
    }

    private function movePostMediaDirectory(string $oldSlug, string $newSlug): array
    {
        $postsRoot = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'posts';
        $oldDir = $postsRoot . DIRECTORY_SEPARATOR . $oldSlug;
        $newDir = $postsRoot . DIRECTORY_SEPARATOR . $newSlug;
        $replacements = [];

        if (!is_dir($oldDir)) {
            return ['ok' => true, 'replacements' => []];
        }

        if (!file_exists($newDir)) {
            if (@rename($oldDir, $newDir)) {
                $replacements = array_merge($replacements, $this->renamePostMediaFiles($newDir, $oldSlug, $newSlug));
                return ['ok' => true, 'replacements' => $replacements];
            }
        }

        if (!is_dir($newDir) && !@mkdir($newDir, 0775, true) && !is_dir($newDir)) {
            return ['ok' => false, 'error' => 'Nao foi possivel preparar a nova pasta de midia do post.'];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($oldDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }

            $source = $item->getPathname();
            $relative = substr($source, strlen($oldDir) + 1);
            $target = $newDir . DIRECTORY_SEPARATOR . $relative;

            if ($item->isDir()) {
                if (!is_dir($target) && !@mkdir($target, 0775, true) && !is_dir($target)) {
                    return ['ok' => false, 'error' => 'Nao foi possivel recriar a estrutura da pasta do post.'];
                }
                @rmdir($source);
                continue;
            }

            $targetDir = dirname($target);
            if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                return ['ok' => false, 'error' => 'Nao foi possivel preparar a pasta de destino da midia do post.'];
            }

            if (is_file($target)) {
                @unlink($source);
                continue;
            }

            if (!@rename($source, $target)) {
                return ['ok' => false, 'error' => 'Nao foi possivel mover os arquivos de midia do post para o novo slug.'];
            }
        }

        @rmdir($oldDir);

        $replacements = array_merge($replacements, $this->renamePostMediaFiles($newDir, $oldSlug, $newSlug));

        return ['ok' => true, 'replacements' => $replacements];
    }

    private function renamePostMediaFiles(string $directory, string $oldSlug, string $newSlug): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $publicRoot = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR;
        $replacements = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo || !$item->isFile()) {
                continue;
            }

            $basename = $item->getBasename();
            if (!str_contains($basename, $oldSlug)) {
                continue;
            }

            $newBasename = str_replace($oldSlug, $newSlug, $basename);
            if ($newBasename === $basename) {
                continue;
            }

            $oldAbsolute = $item->getPathname();
            $newAbsolute = $item->getPath() . DIRECTORY_SEPARATOR . $newBasename;

            if (is_file($newAbsolute)) {
                @unlink($oldAbsolute);
                continue;
            }

            if (!@rename($oldAbsolute, $newAbsolute)) {
                continue;
            }

            $oldRelative = str_replace('\\', '/', substr($oldAbsolute, strlen($publicRoot)));
            $newRelative = str_replace('\\', '/', substr($newAbsolute, strlen($publicRoot)));
            $replacements[] = ['from' => $oldRelative, 'to' => $newRelative];
        }

        return $replacements;
    }

    private function applyPostMediaReplacements(string $value, array $replacements, string $oldSlug, string $newSlug): string
    {
        if ($value === '') {
            return $value;
        }

        $oldSegment = 'uploads/posts/' . $oldSlug . '/';
        $newSegment = 'uploads/posts/' . $newSlug . '/';
        $result = str_replace(
            [$oldSegment, '/' . $oldSegment, url('/' . $oldSegment)],
            [$newSegment, '/' . $newSegment, url('/' . $newSegment)],
            $value
        );

        usort($replacements, static function (array $left, array $right): int {
            return strlen((string) ($right['from'] ?? '')) <=> strlen((string) ($left['from'] ?? ''));
        });

        foreach ($replacements as $replacement) {
            $from = trim((string) ($replacement['from'] ?? ''));
            $to = trim((string) ($replacement['to'] ?? ''));
            if ($from === '' || $to === '') {
                continue;
            }

            $result = str_replace(
                [$from, '/' . $from, url('/' . $from)],
                [$to, '/' . $to, url('/' . $to)],
                $result
            );
        }

        return $result;
    }

    private function extractPostMediaSlugs(string $value): array
    {
        if ($value === '') {
            return [];
        }

        preg_match_all('~uploads/posts/([^/"\'\s<>]+)/~i', $value, $matches);
        $items = $matches[1] ?? [];
        if (!is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map(fn (mixed $item): string => $this->slugify((string) $item), $items)));
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = max(0, $bytes);
        $index = 0;

        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }

        return number_format($size, $index === 0 ? 0 : 1, ',', '.') . ' ' . $units[$index];
    }

    private function buildFormViewModel(string $mode, array $form, array $errors = [], ?array $categorias = null, array $extra = []): array
    {
        $isEdit = $mode === 'edit';
        $supportsNextStep = $this->posts->supportsNextStep();
        $nextStepOptions = $this->posts->listPublishedForNextStepSelect((int) ($form['id'] ?? 0));
        $availableCategorias = $categorias ?? $this->categorias->listForSelect();
        $categoriasById = [];
        foreach ($availableCategorias as $categoria) {
            if (!is_array($categoria)) {
                continue;
            }

            $categoriasById[(int) ($categoria['id'] ?? 0)] = $categoria;
        }

        return array_merge([
            'title' => $isEdit ? 'Editar Post' : 'Criar Post',
            'mode' => $mode,
            'form' => $form,
            'errors' => $errors,
            'categorias' => $availableCategorias,
            'supports_next_step' => $supportsNextStep,
            'next_step_options' => $nextStepOptions,
            'media_items' => $this->midia->recentMedia(24),
            'image_media_items' => $this->midia->recentMedia(48, 'image'),
            'audio_media_items' => $this->midia->recentMedia(12, 'audio'),
            'video_media_items' => $this->midia->recentMedia(12, 'video'),
            'orphan_files' => [],
            'publication_checklist' => $this->buildPublicationChecklist($form, $errors, $categoriasById, $supportsNextStep),
        ], $extra);
    }

    private function buildPublicationChecklist(array $form, array $errors, array $categoriasById, bool $supportsNextStep): array
    {
        $items = [];
        $stats = ['success' => 0, 'warning' => 0, 'error' => 0];
        $status = trim((string) ($form['status'] ?? 'rascunho'));
        $titulo = trim((string) ($form['titulo'] ?? ''));
        $slug = $this->slugify((string) (($form['slug'] ?? '') !== '' ? $form['slug'] : $titulo));
        $conteudoHtml = (string) ($form['conteudo'] ?? '');
        $conteudoTexto = $this->plainTextFromHtml($conteudoHtml);
        $categoriaId = (int) ($form['categoria_post_id'] ?? 0);
        $dataPublicacao = $this->normalizeDateTimeForDatabase((string) ($form['data_publicacao'] ?? ''));
        $nextStepId = max(0, (int) ($form['proximo_post_id'] ?? 0));
        $missingMedia = $this->findMissingMediaReferences($form);
        $missingAltImages = $this->findImagesMissingAlt($conteudoHtml);
        $orphanFiles = $this->findOrphanBodyFiles($form);

        $addItem = static function (string $key, string $statusKey, string $title, string $message, string $group = 'editorial') use (&$items, &$stats): void {
            $items[] = [
                'key' => $key,
                'status' => $statusKey,
                'title' => $title,
                'message' => $message,
                'group' => $group,
            ];
            if (isset($stats[$statusKey])) {
                $stats[$statusKey]++;
            }
        };

        if ($titulo === '') {
            $addItem('titulo', 'error', 'Titulo', 'Informe o titulo do post.');
        } elseif (mb_strlen($titulo) > 200) {
            $addItem('titulo', 'error', 'Titulo', 'O titulo passou do limite de 200 caracteres.');
        } else {
            $addItem('titulo', 'success', 'Titulo', 'Titulo preenchido e pronto para publicacao.');
        }

        if ($slug === '') {
            $addItem('slug', 'error', 'Slug', 'Nao foi possivel gerar um slug valido.');
        } else {
            $addItem('slug', 'success', 'Slug', 'Slug atual: ' . $slug);
        }

        if ($conteudoTexto === '') {
            $addItem('conteudo', 'error', 'Conteudo', 'O post ainda nao tem conteudo editorial salvo.');
        } else {
            $addItem('conteudo', 'success', 'Conteudo', 'Conteudo preenchido com ' . mb_strlen($conteudoTexto) . ' caracteres visiveis.');
        }

        if (!isset($categoriasById[$categoriaId])) {
            $addItem('categoria', 'error', 'Categoria', 'Selecione uma categoria valida para o post.');
        } else {
            $categoriaNome = trim((string) ($categoriasById[$categoriaId]['nome'] ?? 'Categoria'));
            $addItem('categoria', 'success', 'Categoria', 'Categoria selecionada: ' . $categoriaNome . '.');
        }

        if (!in_array($status, ['publicado', 'rascunho', 'agendado'], true)) {
            $addItem('status', 'error', 'Status', 'Selecione um status valido.');
        } elseif ($status === 'publicado') {
            $addItem('status', 'success', 'Status', 'Post marcado para publicacao imediata.');
        } elseif ($status === 'agendado') {
            $addItem('status', $dataPublicacao === null ? 'error' : 'warning', 'Status', $dataPublicacao === null
                ? 'Post agendado sem data valida.'
                : 'Post em modo agendado. Revise a data antes de publicar.');
        } else {
            $addItem('status', 'warning', 'Status', 'Post permanece em rascunho ate a publicacao.');
        }

        if ($dataPublicacao === null) {
            $addItem('publicacao', 'error', 'Data de publicacao', 'Informe uma data de publicacao valida.');
        } elseif ($status === 'agendado' && strtotime($dataPublicacao) <= time()) {
            $addItem('publicacao', 'error', 'Data de publicacao', 'Posts agendados precisam de uma data futura.');
        } elseif ($status === 'publicado' && strtotime($dataPublicacao) > time()) {
            $addItem('publicacao', 'warning', 'Data de publicacao', 'A data esta no futuro mesmo com status publicado.');
        } else {
            $addItem('publicacao', 'success', 'Data de publicacao', 'Data configurada: ' . date('d/m/Y H:i', strtotime($dataPublicacao)));
        }

        $coverPath = trim((string) ($form['imagem_capa'] ?? ''));
        if ($coverPath === '') {
            $addItem('capa', 'warning', 'Imagem de capa', 'O post ainda esta sem capa.');
        } elseif (!$this->assetReferenceExists($coverPath)) {
            $addItem('capa', 'error', 'Imagem de capa', 'A capa configurada nao foi encontrada no ambiente local.');
        } else {
            $addItem('capa', 'success', 'Imagem de capa', 'Capa pronta para o front.');
        }

        $thumbPath = trim((string) ($form['imagem_thumb'] ?? ''));
        if ($thumbPath === '') {
            $addItem('thumb', 'warning', 'Thumbnail', 'O post ainda esta sem thumb.');
        } elseif (!$this->assetReferenceExists($thumbPath)) {
            $addItem('thumb', 'error', 'Thumbnail', 'A thumb configurada nao foi encontrada no ambiente local.');
        } else {
            $addItem('thumb', 'success', 'Thumbnail', 'Thumb pronta para cards e listagens.');
        }

        $seoTitle = trim((string) ($form['seo_title'] ?? ''));
        $seoDescription = trim((string) ($form['seo_description'] ?? ''));
        if ($seoTitle === '' && $seoDescription === '') {
            $addItem('seo', 'warning', 'SEO', 'SEO title e description ainda estao vazios.');
        } elseif (mb_strlen($seoTitle) > 200 || mb_strlen($seoDescription) > 300) {
            $addItem('seo', 'error', 'SEO', 'Os campos de SEO passaram do limite permitido.');
        } else {
            $parts = [];
            if ($seoTitle !== '') {
                $parts[] = 'title ok';
            }
            if ($seoDescription !== '') {
                $parts[] = 'description ok';
            }
            $addItem('seo', 'success', 'SEO', 'Campos prontos: ' . implode(' e ', $parts) . '.');
        }

        if (!$supportsNextStep) {
            $addItem('next_step', 'warning', 'Proximo passo', 'O banco atual ainda nao suporta CTA dedicado de proximo passo.');
        } elseif ($nextStepId <= 0) {
            $addItem('next_step', 'warning', 'Proximo passo', 'Nenhum post recomendado foi selecionado.');
        } elseif (isset($errors['proximo_post_id'])) {
            $addItem('next_step', 'error', 'Proximo passo', (string) $errors['proximo_post_id']);
        } else {
            $nextStep = $this->posts->findPublishedById($nextStepId);
            if ($nextStep === null) {
                $addItem('next_step', 'error', 'Proximo passo', 'O post recomendado nao foi encontrado como publicado.');
            } else {
                $addItem('next_step', 'success', 'Proximo passo', 'CTA aponta para: ' . trim((string) ($nextStep['titulo'] ?? 'post publicado')) . '.');
            }
        }

        if ($missingMedia !== []) {
            $addItem(
                'media',
                'error',
                'Midia referenciada',
                count($missingMedia) . ' arquivo(s) citado(s) no HTML nao foram encontrados localmente.',
                'tecnico'
            );
        } else {
            $addItem('media', 'success', 'Midia referenciada', 'Todas as midias citadas no HTML foram encontradas.', 'tecnico');
        }

        if ($missingAltImages !== []) {
            $addItem(
                'alt',
                'warning',
                'Alt de imagens',
                count($missingAltImages) . ' imagem(ns) do HTML estao sem alt preenchido.',
                'tecnico'
            );
        } else {
            $addItem('alt', 'success', 'Alt de imagens', 'Imagens do HTML com alt preenchido ou sem imagens inline pendentes.', 'tecnico');
        }

        if ($orphanFiles !== []) {
            $addItem(
                'orphans',
                'warning',
                'Arquivos soltos',
                count($orphanFiles) . ' arquivo(s) da pasta do post nao aparecem mais no HTML salvo.',
                'tecnico'
            );
        } else {
            $addItem('orphans', 'success', 'Arquivos soltos', 'Nenhum arquivo solto detectado na pasta do post.', 'tecnico');
        }

        $overall = 'success';
        $headline = 'Pronto para publicar.';
        if ($stats['error'] > 0) {
            $overall = 'error';
            $headline = 'Publicacao bloqueada ate corrigir os erros criticos.';
        } elseif ($stats['warning'] > 0) {
            $overall = 'warning';
            $headline = 'Post utilizavel, mas ainda com alertas editoriais.';
        }

        return [
            'status' => $overall,
            'headline' => $headline,
            'stats' => $stats,
            'items' => $items,
            'missing_media' => $missingMedia,
            'missing_alt_images' => $missingAltImages,
            'runtime' => [
                'slug' => $this->slugify((string) ($form['slug'] ?? '')),
                'managed_files' => $this->listPostManagedFiles($form),
                'known_existing_uploads' => $this->listExistingUploadReferences($form),
            ],
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
            'proximo_post_id' => max(0, (int) ($post['proximo_post_id'] ?? 0)),
            'data_publicacao' => $this->formatDateTimeForInput((string) ($post['data_publicacao'] ?? '')),
            'tempo_leitura' => max(1, (int) ($post['tempo_leitura'] ?? 5)),
        ];
    }

    private function findOrphanBodyFiles(array $form): array
    {
        $slug = $this->slugify((string) ($form['slug'] ?? ''));
        if ($slug === '') {
            return [];
        }

        $directory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'posts' . DIRECTORY_SEPARATOR . $slug;
        if (!is_dir($directory)) {
            return [];
        }

        $protected = array_fill_keys(array_filter([
            ltrim(trim((string) ($form['imagem_capa'] ?? '')), '/'),
            ltrim(trim((string) ($form['imagem_thumb'] ?? '')), '/'),
        ]), true);

        $referenced = [];
        preg_match_all('~uploads/posts/' . preg_quote($slug, '~') . '/[^"\')\s<>]+~i', (string) ($form['conteudo'] ?? ''), $matches);
        foreach (($matches[0] ?? []) as $path) {
            $clean = ltrim((string) $path, '/');
            if ($clean !== '') {
                $referenced[$clean] = true;
            }
        }

        $items = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            $relativePath = 'uploads/posts/' . $slug . '/' . str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
            if (isset($protected[$relativePath]) || isset($referenced[$relativePath])) {
                continue;
            }

            $extension = strtolower($file->getExtension());
            $items[] = [
                'name' => $file->getFilename(),
                'relative_path' => $relativePath,
                'public_url' => url('/' . $relativePath),
                'size_label' => $this->formatBytes((int) $file->getSize()),
                'modified_label' => date('d/m/Y H:i', (int) $file->getMTime()),
                'is_image' => in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true),
            ];
        }

        usort($items, static function (array $left, array $right): int {
            return strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        return $items;
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
            'proximo_post_id' => max(0, (int) ($input['proximo_post_id'] ?? 0)),
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

        $nextStepId = max(0, (int) ($form['proximo_post_id'] ?? 0));
        if ($nextStepId > 0) {
            if (!$this->posts->supportsNextStep()) {
                $errors['proximo_post_id'] = 'Recurso de proximo passo indisponivel neste banco. Execute a migracao da coluna proximo_post_id.';
                return $errors;
            }
            $currentId = max(0, (int) ($ignoreId ?? ($form['id'] ?? 0)));
            if ($currentId > 0 && $nextStepId === $currentId) {
                $errors['proximo_post_id'] = 'Selecione um post diferente para o proximo passo.';
            } elseif ($this->posts->findPublishedById($nextStepId) === null) {
                $errors['proximo_post_id'] = 'O proximo passo precisa apontar para um post publicado valido.';
            }
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

    private function findImagesMissingAlt(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        preg_match_all('/<img\b[^>]*>/i', $html, $matches);
        $items = [];

        foreach ((array) ($matches[0] ?? []) as $index => $tag) {
            $src = '';
            if (preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', (string) $tag, $srcMatch)) {
                $src = trim((string) ($srcMatch[1] ?? ''));
            }

            $hasAlt = preg_match('/\balt\s*=\s*["\']([^"\']*)["\']/i', (string) $tag, $altMatch) === 1;
            $alt = $hasAlt ? trim((string) ($altMatch[1] ?? '')) : '';
            if (!$hasAlt || $alt === '') {
                $items[] = [
                    'index' => $index + 1,
                    'src' => $src,
                ];
            }
        }

        return $items;
    }

    private function listPostManagedFiles(array $form): array
    {
        $slug = $this->slugify((string) ($form['slug'] ?? ''));
        if ($slug === '') {
            return [];
        }

        $directory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'posts' . DIRECTORY_SEPARATOR . $slug;
        if (!is_dir($directory)) {
            return [];
        }

        $items = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            $items[] = 'uploads/posts/' . $slug . '/' . str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
        }

        sort($items);

        return $items;
    }

    private function listExistingUploadReferences(array $form): array
    {
        $paths = [];

        foreach ([
            (string) ($form['imagem_capa'] ?? ''),
            (string) ($form['imagem_thumb'] ?? ''),
        ] as $value) {
            $normalized = $this->normalizeAssetReference($value);
            if ($normalized !== '' && $this->assetReferenceExists($normalized)) {
                $paths[$normalized] = true;
            }
        }

        preg_match_all('~(?:src|href|poster)\s*=\s*["\']([^"\']+)["\']~i', (string) ($form['conteudo'] ?? ''), $matches);
        foreach ((array) ($matches[1] ?? []) as $value) {
            $normalized = $this->normalizeAssetReference((string) $value);
            if ($normalized !== '' && $this->assetReferenceExists($normalized)) {
                $paths[$normalized] = true;
            }
        }

        preg_match_all('~data-audio-(?:narracao|ambiente)\s*=\s*["\']([^"\']+)["\']~i', (string) ($form['conteudo'] ?? ''), $audioMatches);
        foreach ((array) ($audioMatches[1] ?? []) as $value) {
            $normalized = $this->normalizeAssetReference((string) $value);
            if ($normalized !== '' && $this->assetReferenceExists($normalized)) {
                $paths[$normalized] = true;
            }
        }

        return array_values(array_keys($paths));
    }

    private function findMissingMediaReferences(array $form): array
    {
        $paths = [];

        foreach ([
            (string) ($form['imagem_capa'] ?? ''),
            (string) ($form['imagem_thumb'] ?? ''),
        ] as $value) {
            $normalized = $this->normalizeAssetReference($value);
            if ($normalized !== '') {
                $paths[$normalized] = true;
            }
        }

        preg_match_all('~(?:src|href)\s*=\s*["\']([^"\']+)["\']~i', (string) ($form['conteudo'] ?? ''), $matches);
        foreach ((array) ($matches[1] ?? []) as $value) {
            $normalized = $this->normalizeAssetReference((string) $value);
            if ($normalized !== '') {
                $paths[$normalized] = true;
            }
        }

        $missing = [];
        foreach (array_keys($paths) as $path) {
            if (!$this->assetReferenceExists($path)) {
                $missing[] = $path;
            }
        }

        return $missing;
    }

    private function assetReferenceExists(string $value): bool
    {
        $normalized = $this->normalizeAssetReference($value);
        if ($normalized === '') {
            return true;
        }

        $fullPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        return is_file($fullPath);
    }

    private function normalizeAssetReference(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('~^(data:|blob:|https?://)~i', $value)) {
            $parsedPath = parse_url($value, PHP_URL_PATH);
            if (!is_string($parsedPath) || $parsedPath === '') {
                return '';
            }
            $value = $parsedPath;
        }

        $value = ltrim(str_replace('\\', '/', $value), '/');
        if ($value === '' || !str_starts_with($value, 'uploads/')) {
            return '';
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
