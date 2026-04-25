<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Support\SystemActivityLogger;
use finfo;

final class MidiaService
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
    private const AUDIO_EXTENSIONS = ['mp3', 'wav', 'ogg', 'm4a', 'aac'];
    private const VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov', 'ogv'];
    private const IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];
    private const AUDIO_MIME_TYPES = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'audio/mp4', 'audio/x-m4a', 'audio/aac'];
    private const VIDEO_MIME_TYPES = ['video/mp4', 'video/webm', 'video/quicktime', 'video/ogg'];
    private const LIBRARY_DIRECTORIES = [
        ['relative' => 'uploads', 'label' => 'Upload'],
        ['relative' => 'assets/brand', 'label' => 'Institucional'],
    ];

    public function __construct(
        private ?\PDO $pdo = null,
    ) {
    }

    public function getIndexViewModel(array $query = [], array $errors = []): array
    {
        $this->ensureDirectories();

        $filters = $this->normalizeFilters($query);
        $page = $this->clampInt((int) ($query['page'] ?? 1), 1, 9999);
        $perPage = $this->clampInt((int) ($query['per_page'] ?? 12), 8, 48);
        [$sort, $dir] = $this->normalizeSortDir((string) ($query['sort'] ?? 'data'), (string) ($query['dir'] ?? 'desc'));

        $usage = $this->collectMediaUsage();
        $allItems = $this->scanMediaItems($usage);
        $filteredItems = $this->applyFilters($allItems, $filters);
        $sortedItems = $this->sortItems($filteredItems, $sort, $dir);

        return [
            'title' => 'Midia',
            'filters' => $filters,
            'sort' => $sort,
            'dir' => $dir,
            'summary' => $this->buildSummary($allItems),
            'pagination' => $this->paginate($sortedItems, $page, $perPage),
            'errors' => $errors,
            'upload' => $this->buildUploadConfig($query),
        ];
    }

    public function recentImages(int $limit = 12): array
    {
        return $this->recentMedia($limit, 'image');
    }

    public function recentMedia(int $limit = 24, ?string $type = null): array
    {
        $items = $this->scanMediaItems($this->collectMediaUsage());
        if ($type !== null && $type !== '') {
            $items = array_values(array_filter($items, static fn (array $item): bool => (($item['media_type'] ?? 'other') === $type)));
        }
        $items = $this->sortItems($items, 'data', 'desc');
        return array_slice($items, 0, max(1, $limit));
    }

    public function storeUploadedImage(mixed $file, string $folder = 'posts', ?string $preferredBase = null, bool $overwrite = false): array
    {
        return $this->storeUploadedMedia($file, $folder, $preferredBase, $overwrite, 'image');
    }

    public function storePostRoleImage(mixed $file, string $slug, string $role): array
    {
        $slug = $this->slugify($slug);
        $role = $this->slugify($role);
        if ($slug === '' || $role === '' || !in_array($role, ['capa', 'thumb'], true)) {
            return ['ok' => false, 'error' => 'Nao foi possivel preparar o nome da imagem do post.'];
        }

        $result = $this->storeUploadedMedia($file, 'posts/' . $slug . '/images', $role, true, 'image');
        if (($result['ok'] ?? false) === true) {
            $this->cleanupLegacyPostRoleImage($slug, $role);
        }

        return $result;
    }

    public function storePostBodyImage(mixed $file, string $slug): array
    {
        $target = $this->preparePostBodyImageTarget($slug);
        if (($target['ok'] ?? false) !== true) {
            return ['ok' => false, 'error' => (string) ($target['error'] ?? 'Nao foi possivel preparar a imagem do conteudo.')];
        }

        return $this->storeUploadedMedia(
            $file,
            (string) ($target['storage_dir'] ?? 'posts'),
            (string) ($target['base'] ?? ''),
            false,
            'image'
        );
    }

    public function cloneManagedImageToPost(string $path, string $slug): array
    {
        return $this->cloneManagedMediaToPost($path, $slug, 'image');
    }

    public function cloneManagedPostRoleImage(string $path, string $slug, string $role): array
    {
        return $this->cloneManagedMediaToPost($path, $slug, 'image', ['post_role' => $role]);
    }

    public function cloneManagedMediaToPost(string $path, string $slug, string $type, array $options = []): array
    {
        $normalizedType = $this->normalizeRequestedMediaType($type);
        if ($normalizedType === '') {
            SystemActivityLogger::write('media', 'clone_media_to_post_failed', [
                'slug' => $slug,
                'source_path' => $path,
                'requested_type' => $type,
                'reason' => 'invalid_type',
            ]);
            return ['ok' => false, 'error' => 'Tipo de midia invalido para copia.'];
        }

        $postRole = $normalizedType === 'image' ? $this->slugify((string) ($options['post_role'] ?? '')) : '';
        $audioRole = $normalizedType === 'audio' ? $this->normalizeAudioRole((string) ($options['audio_role'] ?? '')) : '';
        $target = $this->preparePostMediaTarget($slug, $normalizedType, $postRole, $audioRole);
        if (($target['ok'] ?? false) !== true) {
            SystemActivityLogger::write('media', 'clone_media_to_post_failed', [
                'slug' => $slug,
                'source_path' => $path,
                'media_type' => $normalizedType,
                'reason' => 'target_prepare_failed',
                'error' => (string) ($target['error'] ?? ''),
            ]);
            return ['ok' => false, 'error' => (string) ($target['error'] ?? 'Nao foi possivel preparar a midia do post.')];
        }

        $source = $this->resolveManagedFile($path);
        if ($source === null) {
            SystemActivityLogger::write('media', 'clone_media_to_post_failed', [
                'slug' => $slug,
                'source_path' => $path,
                'media_type' => $normalizedType,
                'reason' => 'source_not_found',
            ]);
            return ['ok' => false, 'error' => 'Midia da biblioteca nao encontrada.'];
        }

        if ((string) ($source['media_type'] ?? '') !== $normalizedType) {
            SystemActivityLogger::write('media', 'clone_media_to_post_failed', [
                'slug' => $slug,
                'source_path' => $path,
                'media_type' => $normalizedType,
                'source_media_type' => (string) ($source['media_type'] ?? ''),
                'reason' => 'type_mismatch',
            ]);
            return ['ok' => false, 'error' => 'A midia selecionada nao corresponde ao tipo solicitado.'];
        }

        $extension = strtolower((string) pathinfo((string) ($source['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = strtolower((string) pathinfo((string) ($source['relative_path'] ?? ''), PATHINFO_EXTENSION));
        }

        $allowedExtensions = $this->allowedExtensionsForType($normalizedType);
        if ($extension === '' || ($allowedExtensions !== [] && !in_array($extension, $allowedExtensions, true))) {
            SystemActivityLogger::write('media', 'clone_media_to_post_failed', [
                'slug' => $slug,
                'source_path' => $path,
                'media_type' => $normalizedType,
                'reason' => 'invalid_extension',
            ]);
            return ['ok' => false, 'error' => 'Nao foi possivel identificar a extensao da midia selecionada.'];
        }

        $base = (string) ($target['base'] ?? '');
        $relativeDir = (string) ($target['relative_dir'] ?? '');
        $absoluteDir = (string) ($target['absolute_dir'] ?? '');
        $overwrite = ($target['overwrite'] ?? false) === true;
        if ($base === '' || $relativeDir === '' || $absoluteDir === '') {
            SystemActivityLogger::write('media', 'clone_media_to_post_failed', [
                'slug' => $slug,
                'source_path' => $path,
                'media_type' => $normalizedType,
                'reason' => 'invalid_target',
            ]);
            return ['ok' => false, 'error' => 'Destino da midia nao pode ser determinado.'];
        }

        if ($overwrite) {
            $this->deleteFilesByBase($absoluteDir, $base);
            $filename = $base . '.' . $extension;
        } elseif ($this->isPostMediaShortBase($base)) {
            $filename = $this->nextSequencedFilename($absoluteDir, $base, $extension);
        } else {
            $filename = $this->nextAvailableFilename($absoluteDir, $base, $extension);
        }

        $relativePath = $relativeDir . '/' . $filename;
        $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $filename;

        if (!@copy((string) ($source['absolute_path'] ?? ''), $absolutePath)) {
            SystemActivityLogger::write('media', 'clone_media_to_post_failed', [
                'slug' => $slug,
                'source_path' => $path,
                'media_type' => $normalizedType,
                'target_path' => $relativePath,
                'reason' => 'copy_failed',
            ]);
            return ['ok' => false, 'error' => 'Falha ao duplicar a midia da biblioteca para o post.'];
        }

        if ($normalizedType === 'image' && $postRole !== '') {
            $this->cleanupLegacyPostRoleImage($this->slugify($slug), $postRole);
        }

        $item = $this->resolveManagedFile($relativePath);
        if ($item === null) {
            SystemActivityLogger::write('media', 'clone_media_to_post_failed', [
                'slug' => $slug,
                'source_path' => $path,
                'media_type' => $normalizedType,
                'target_path' => $relativePath,
                'reason' => 'target_resolve_failed',
            ]);
            return ['ok' => false, 'error' => 'A copia da midia foi criada, mas nao pode ser localizada.'];
        }

        SystemActivityLogger::write('media', 'clone_media_to_post_succeeded', [
            'slug' => $this->slugify($slug),
            'source_path' => $path,
            'media_type' => $normalizedType,
            'target_path' => $relativePath,
            'post_role' => $postRole,
            'audio_role' => $audioRole,
        ]);

        return ['ok' => true, 'path' => $relativePath, 'item' => $item];
    }

    public function upload(mixed $file, array $query = []): array
    {
        $upload = $this->buildUploadConfig($query);
        $forcedType = (string) ($upload['media_type'] ?? '');
        $validation = $this->validateUpload($file, $forcedType !== '' ? $forcedType : null);
        if (($validation['ok'] ?? false) !== true) {
            return ['ok' => false, 'viewModel' => $this->getIndexViewModel($query, ['arquivo' => (string) ($validation['arquivo'] ?? 'Falha no upload do arquivo.')])];
        }

        $type = (string) ($validation['type'] ?? 'other');
        $folder = $this->buildUploadFolder($type, $upload);
        $preferredBase = $this->buildUploadBaseName($type, $file, $upload);
        $result = $this->storeUploadedMedia($file, $folder, $preferredBase, false, $type);
        if (($result['ok'] ?? false) !== true) {
            return ['ok' => false, 'viewModel' => $this->getIndexViewModel($query, ['arquivo' => (string) ($result['error'] ?? 'Falha no upload do arquivo.')])];
        }

        return [
            'ok' => true,
            'path' => $result['path'] ?? null,
            'item' => isset($result['path']) ? $this->resolveManagedFile((string) $result['path']) : null,
            'redirect_query' => $this->buildUploadRedirectQuery($query, $upload),
        ];
    }

    public function getDeleteViewModel(string $path): ?array
    {
        $resolved = $this->resolveManagedFile($path);
        if ($resolved === null) {
            return null;
        }

        return ['title' => 'Excluir Midia', 'item' => $resolved];
    }

    public function delete(string $path): array
    {
        $resolved = $this->resolveManagedFile($path);
        if ($resolved === null) {
            return ['ok' => false, 'not_found' => true];
        }

        @unlink($resolved['absolute_path']);
        return ['ok' => true];
    }

    public function cleanupVisibleOrphans(array $query = []): array
    {
        $filters = $this->normalizeFilters($query);
        $filters['estado'] = 'orfa';
        $items = $this->applyFilters($this->scanMediaItems($this->collectMediaUsage()), $filters);
        $removed = 0;
        $failed = 0;
        $attempted = 0;

        foreach ($items as $item) {
            if (($item['is_orphan'] ?? false) !== true || ($item['is_managed_upload'] ?? false) !== true) {
                continue;
            }

            $resolved = $this->resolveManagedFile((string) ($item['relative_path'] ?? ''));
            $targetPath = $resolved['absolute_path'] ?? $this->resolveManagedAbsoluteFromItem($item);
            if (!is_string($targetPath) || $targetPath === '') {
                $failed++;
                continue;
            }

            $attempted++;
            if (is_file($targetPath) && @unlink($targetPath)) {
                $removed++;
            } else {
                $failed++;
            }
        }

        return ['ok' => true, 'removed' => $removed, 'failed' => $failed, 'attempted' => $attempted];
    }

    private function scanMediaItems(array $usage = []): array
    {
        $items = [];

        foreach (self::LIBRARY_DIRECTORIES as $library) {
            $relativeRoot = (string) ($library['relative'] ?? '');
            $label = (string) ($library['label'] ?? 'Midia');
            $root = $this->publicRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeRoot);
            if (!is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                    continue;
                }

                $absolutePath = $fileInfo->getPathname();
                $relativePath = str_replace('\\', '/', substr($absolutePath, strlen($this->publicRoot()) + 1));
                $extension = strtolower($fileInfo->getExtension());
                $size = (int) $fileInfo->getSize();
                $modifiedAt = (int) $fileInfo->getMTime();
                $directory = trim(str_replace('\\', '/', substr($fileInfo->getPath(), strlen($this->publicRoot()) + 1)), '/');
                $mime = $this->detectMimeType($absolutePath);
                $mediaType = $this->detectMediaType($extension, $mime);
                $isImage = $mediaType === 'image';
                $isAudio = $mediaType === 'audio';
                $isVideo = $mediaType === 'video';
                [$width, $height] = $isImage ? $this->detectDimensions($absolutePath) : [null, null];

                $isManagedUpload = str_starts_with($relativePath, 'uploads/');
                $usageState = $this->resolveUsageState($relativePath, $usage, $isManagedUpload, $relativeRoot);
                $linkedEntities = $this->linkedEntitiesForPath($relativePath, $usage);
                $linkedPosts = $this->linkedPostsForPath($relativePath, $usage);
                $primaryPost = $linkedPosts[0] ?? null;
                $primaryPostSlug = trim((string) ($primaryPost['slug'] ?? ''));
                $primaryPostTitle = trim((string) ($primaryPost['title'] ?? ''));
                $fallbackSlug = $this->extractPostSlug($relativePath);
                $postSlug = $primaryPostSlug !== '' ? $primaryPostSlug : $fallbackSlug;
                $postFilterUrl = $postSlug !== '' ? url('/admin/posts?busca=' . rawurlencode($postSlug)) : '';
                $linkedEntitiesByType = $this->groupReferencesByType($linkedEntities);
                $linkedEntitiesLabel = $this->buildLinkedEntitiesLabel($linkedEntitiesByType);
                $linkedEntitiesSearchText = mb_strtolower(implode(' ', array_map(
                    static function (array $ref): string {
                        return implode(' ', [
                            (string) ($ref['type_label'] ?? ''),
                            (string) ($ref['title'] ?? ''),
                            (string) ($ref['slug'] ?? ''),
                            (string) ($ref['context'] ?? ''),
                        ]);
                    },
                    $linkedEntities
                )));

                $items[] = [
                    'name' => $fileInfo->getFilename(),
                    'basename' => pathinfo($fileInfo->getFilename(), PATHINFO_FILENAME),
                    'extension' => $extension,
                    'relative_path' => $relativePath,
                    'absolute_path' => $absolutePath,
                    'public_url' => url('/' . $relativePath),
                    'directory' => $directory,
                    'library' => $label,
                    'library_key' => $relativeRoot,
                    'is_managed_upload' => $isManagedUpload,
                    'usage_state' => $usageState,
                    'status_label' => $this->statusLabel($usageState),
                    'is_in_use' => $usageState === 'in_use',
                    'is_orphan' => $usageState === 'orphan',
                    'linked_posts' => $linkedPosts,
                    'linked_posts_count' => count($linkedPosts),
                    'linked_entities' => $linkedEntities,
                    'linked_entities_count' => count($linkedEntities),
                    'linked_entities_by_type' => $linkedEntitiesByType,
                    'linked_entities_label' => $linkedEntitiesLabel,
                    'linked_entities_search_text' => $linkedEntitiesSearchText,
                    'post_slug' => $postSlug,
                    'post_title' => $primaryPostTitle,
                    'post_filter_url' => $postFilterUrl,
                    'size' => $size,
                    'size_label' => $this->formatBytes($size),
                    'modified_at' => $modifiedAt,
                    'modified_label' => date('d/m/Y H:i', $modifiedAt),
                    'mime' => $mime,
                    'media_type' => $mediaType,
                    'media_type_label' => $this->mediaTypeLabel($mediaType),
                    'is_image' => $isImage,
                    'is_audio' => $isAudio,
                    'is_video' => $isVideo,
                    'width' => $width,
                    'height' => $height,
                    'dimensions_label' => ($width && $height) ? ($width . ' x ' . $height) : '-',
                ];
            }
        }

        return $items;
    }

    private function applyFilters(array $items, array $filters): array
    {
        $busca = mb_strtolower((string) ($filters['busca'] ?? ''));
        $tipo = (string) ($filters['tipo'] ?? '');
        $estado = (string) ($filters['estado'] ?? '');

        return array_values(array_filter($items, static function (array $item) use ($busca, $tipo, $estado): bool {
            if ($busca !== '') {
                $haystack = mb_strtolower(implode(' ', [
                    (string) ($item['name'] ?? ''),
                    (string) ($item['directory'] ?? ''),
                    (string) ($item['mime'] ?? ''),
                    (string) ($item['media_type_label'] ?? ''),
                    (string) ($item['post_slug'] ?? ''),
                    (string) ($item['linked_entities_search_text'] ?? ''),
                ]));
                if (!str_contains($haystack, $busca)) {
                    return false;
                }
            }

            $mediaType = (string) ($item['media_type'] ?? 'other');
            if ($tipo === 'imagem' && $mediaType !== 'image') {
                return false;
            }

            if ($tipo === 'audio' && $mediaType !== 'audio') {
                return false;
            }

            if ($tipo === 'video' && $mediaType !== 'video') {
                return false;
            }

            if ($tipo === 'outros' && $mediaType !== 'other') {
                return false;
            }

            $usageState = (string) ($item['usage_state'] ?? 'available');

            if ($estado === 'orfa' && $usageState !== 'orphan') {
                return false;
            }

            if ($estado === 'uso' && $usageState !== 'in_use') {
                return false;
            }

            return true;
        }));
    }

    private function sortItems(array $items, string $sort, string $dir): array
    {
        usort($items, static function (array $left, array $right) use ($sort, $dir): int {
            $result = 0;
            switch ($sort) {
                case 'nome':
                    $result = strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
                    break;
                case 'tamanho':
                    $result = ((int) ($left['size'] ?? 0)) <=> ((int) ($right['size'] ?? 0));
                    break;
                case 'tipo':
                    $result = strcasecmp((string) ($left['mime'] ?? ''), (string) ($right['mime'] ?? ''));
                    break;
                case 'data':
                default:
                    $result = ((int) ($left['modified_at'] ?? 0)) <=> ((int) ($right['modified_at'] ?? 0));
                    break;
            }

            if ($result === 0) {
                $result = strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
            }

            return $dir === 'asc' ? $result : -$result;
        });

        return $items;
    }

    private function paginate(array $items, int $page, int $perPage): array
    {
        $total = count($items);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        return ['items' => array_slice($items, $offset, $perPage), 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages];
    }

    private function buildSummary(array $items): array
    {
        $directories = [];
        $images = 0;
        $audio = 0;
        $video = 0;
        $size = 0;
        $institutional = 0;
        $managedUploads = 0;
        $inUseMedia = 0;

        foreach ($items as $item) {
            $directory = (string) ($item['directory'] ?? 'uploads');
            $directories[$directory] = true;
            $size += (int) ($item['size'] ?? 0);

            $mediaType = (string) ($item['media_type'] ?? 'other');
            if ($mediaType === 'image') {
                $images++;
            } elseif ($mediaType === 'audio') {
                $audio++;
            } elseif ($mediaType === 'video') {
                $video++;
            }

            if ((string) ($item['library'] ?? '') === 'Institucional') {
                $institutional++;
            }

            if (($item['is_managed_upload'] ?? false) === true) {
                $managedUploads++;
            }

            if ((string) ($item['usage_state'] ?? '') === 'in_use') {
                $inUseMedia++;
            }
        }

        $total = count($items);
        $others = max(0, $total - $images - $audio - $video);
        $orphans = count(array_filter($items, static fn (array $item): bool => ($item['is_orphan'] ?? false) === true));
        $coveragePosts = $managedUploads > 0 ? ($inUseMedia / $managedUploads) * 100 : 0.0;
        $orphanRate = $managedUploads > 0 ? ($orphans / $managedUploads) * 100 : 0.0;
        $averageSize = $total > 0 ? (int) round($size / $total) : 0;

        return [
            'total' => $total,
            'images' => $images,
            'audio' => $audio,
            'video' => $video,
            'others' => $others,
            'directories' => count($directories),
            'institutional' => $institutional,
            'managed_uploads' => $managedUploads,
            'post_media' => $inUseMedia,
            'in_use_media' => $inUseMedia,
            'orphans' => $orphans,
            'coverage_posts' => $coveragePosts,
            'coverage_uploads' => $coveragePosts,
            'orphan_rate' => $orphanRate,
            'size_bytes' => $size,
            'size_label' => $this->formatBytes($size),
            'average_size_bytes' => $averageSize,
            'average_size_label' => $this->formatBytes($averageSize),
        ];
    }

    private function storeUploadedMedia(mixed $file, string $folder = 'media', ?string $preferredBase = null, bool $overwrite = false, ?string $forcedType = null): array
    {
        $this->ensureDirectories();

        if (!is_array($file) || !isset($file['error'])) {
            return ['ok' => true, 'skipped' => true, 'path' => null];
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'skipped' => true, 'path' => null];
        }

        $validation = $this->validateUpload($file, $forcedType);
        if (($validation['ok'] ?? false) !== true) {
            return ['ok' => false, 'error' => (string) ($validation['arquivo'] ?? 'Falha no upload do arquivo.')];
        }

        $type = (string) ($validation['type'] ?? $forcedType ?? 'other');
        $extension = strtolower((string) ($validation['extension'] ?? pathinfo((string) ($file['name'] ?? 'arquivo'), PATHINFO_EXTENSION)));
        $originalName = (string) ($file['name'] ?? 'arquivo');
        $baseName = $preferredBase ?? pathinfo($originalName, PATHINFO_FILENAME);
        $safeBase = $this->slugify($baseName !== '' ? $baseName : 'arquivo');
        if ($safeBase === '') {
            $safeBase = match ($type) {
                'image' => 'imagem',
                'audio' => 'audio',
                'video' => 'video',
                default => 'arquivo',
            };
        }

        $relativeDir = 'uploads/' . trim($this->sanitizeUploadFolder($folder), '/');
        $absoluteDir = $this->publicRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            return ['ok' => false, 'error' => 'Nao foi possivel criar a pasta de destino do upload.'];
        }

        if ($overwrite) {
            $this->deleteFilesByBase($absoluteDir, $safeBase);
            $filename = $safeBase . '.' . $extension;
        } elseif ($preferredBase !== null && $this->isPostMediaShortBase($safeBase)) {
            $filename = $this->nextSequencedFilename($absoluteDir, $safeBase, $extension);
        } else {
            $filename = $this->nextAvailableFilename($absoluteDir, $safeBase, $extension);
        }

        $target = $absoluteDir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            return ['ok' => false, 'error' => 'Nao foi possivel mover o arquivo enviado.'];
        }

        return [
            'ok' => true,
            'skipped' => false,
            'path' => $relativeDir . '/' . $filename,
            'type' => $type,
            'mime' => (string) ($validation['mime'] ?? ''),
        ];
    }

    private function buildUploadConfig(array $input = []): array
    {
        $mediaType = $this->normalizeRequestedMediaType((string) ($input['media_type'] ?? ''));
        $scope = $this->normalizeUploadScope((string) ($input['context'] ?? 'library'));
        $postSlug = $scope === 'post' ? $this->slugify((string) ($input['post_slug'] ?? '')) : '';
        $postTitle = trim((string) ($input['post_title'] ?? ''));
        $audioRole = $this->normalizeAudioRole((string) ($input['audio_role'] ?? ''));

        if ($postSlug === '' && $scope === 'post' && $postTitle !== '') {
            $postSlug = $this->slugify($postTitle);
        }

        if ($postSlug === '') {
            $scope = 'library';
        }

        $destinationCode = 'public/uploads/media/{tipo}/ANO/MES';
        $destinationLabel = 'Biblioteca central por tipo';
        if ($scope === 'post' && $postSlug !== '') {
            $destinationCode = 'public/uploads/posts/' . $postSlug . '/';
            $destinationCode .= $mediaType !== '' ? $this->mediaStorageDirectory($mediaType) : '{images|audio|video}';
            $destinationLabel = 'Post ' . $postSlug;
        }

        return [
            'accept' => $this->uploadAcceptList($mediaType),
            'max_size_label' => $this->uploadMaxSizeLabel($mediaType),
            'scope' => $scope,
            'scope_label' => $scope === 'post' ? 'Post especifico' : 'Biblioteca central',
            'media_type' => $mediaType,
            'media_type_label' => $mediaType !== '' ? $this->mediaTypeLabel($mediaType) : 'Midia',
            'post_slug' => $postSlug,
            'post_title' => $postTitle,
            'audio_role' => $audioRole,
            'destination_code' => $destinationCode,
            'destination_label' => $destinationLabel,
        ];
    }

    private function normalizeRequestedMediaType(string $type): string
    {
        $type = strtolower(trim($type));
        return in_array($type, ['image', 'audio', 'video'], true) ? $type : '';
    }

    private function normalizeUploadScope(string $scope): string
    {
        return strtolower(trim($scope)) === 'post' ? 'post' : 'library';
    }

    private function normalizeAudioRole(string $role): string
    {
        $role = $this->slugify($role);
        return in_array($role, ['narracao', 'ambiente'], true) ? $role : '';
    }

    private function buildUploadFolder(string $type, array $upload): string
    {
        $scope = (string) ($upload['scope'] ?? 'library');
        $postSlug = trim((string) ($upload['post_slug'] ?? ''));
        if ($scope === 'post' && $postSlug !== '') {
            return 'posts/' . $postSlug . '/' . $this->mediaStorageDirectory($type);
        }

        return 'media/' . $this->mediaStorageDirectory($type) . '/' . date('Y/m');
    }

    private function buildUploadBaseName(string $type, mixed $file, array $upload): ?string
    {
        $scope = (string) ($upload['scope'] ?? 'library');
        $postSlug = trim((string) ($upload['post_slug'] ?? ''));
        if ($scope !== 'post' || $postSlug === '' || !is_array($file)) {
            return null;
        }

        if ($type === 'audio') {
            return match ((string) ($upload['audio_role'] ?? '')) {
                'narracao' => 'nar',
                'ambiente' => 'amb',
                default => 'aud',
            };
        }

        return match ($type) {
            'image' => 'img',
            'video' => 'vid',
            default => 'file',
        };
    }

    private function buildUploadRedirectQuery(array $input, array $upload): array
    {
        $query = [
            'uploaded' => 1,
            'busca' => trim((string) ($input['busca'] ?? '')),
            'tipo' => trim((string) ($input['tipo'] ?? '')),
            'estado' => trim((string) ($input['estado'] ?? '')),
            'sort' => trim((string) ($input['sort'] ?? '')),
            'dir' => trim((string) ($input['dir'] ?? '')),
            'per_page' => (int) ($input['per_page'] ?? 0),
            'media_type' => trim((string) ($upload['media_type'] ?? '')),
            'context' => trim((string) ($upload['scope'] ?? 'library')),
            'post_slug' => trim((string) ($upload['post_slug'] ?? '')),
            'post_title' => trim((string) ($upload['post_title'] ?? '')),
        ];

        return array_filter($query, static fn (mixed $value): bool => !($value === '' || $value === null || $value === 0));
    }

    private function uploadAcceptList(string $type = ''): string
    {
        return match ($type) {
            'image' => '.jpg,.jpeg,.png,.webp,.gif,.svg',
            'audio' => '.mp3,.wav,.ogg,.m4a,.aac',
            'video' => '.mp4,.webm,.mov,.ogv',
            default => '.jpg,.jpeg,.png,.webp,.gif,.svg,.mp3,.wav,.ogg,.m4a,.aac,.mp4,.webm,.mov,.ogv',
        };
    }

    private function uploadMaxSizeLabel(string $type = ''): string
    {
        return match ($type) {
            'image' => '8 MB para imagens',
            'audio' => '25 MB para audios',
            'video' => '80 MB para videos',
            default => '8 MB para imagens, 25 MB para audios e 80 MB para videos',
        };
    }

    private function detectMediaType(string $extension, string $mime): string
    {
        $extension = strtolower(trim($extension));
        $mime = strtolower(trim($mime));

        if (in_array($extension, self::IMAGE_EXTENSIONS, true) || str_starts_with($mime, 'image/')) {
            return 'image';
        }

        if (in_array($extension, self::AUDIO_EXTENSIONS, true) || str_starts_with($mime, 'audio/')) {
            return 'audio';
        }

        if (in_array($extension, self::VIDEO_EXTENSIONS, true) || str_starts_with($mime, 'video/')) {
            return 'video';
        }

        return 'other';
    }

    private function mediaTypeLabel(string $type): string
    {
        return match ($type) {
            'image' => 'Imagem',
            'audio' => 'Audio',
            'video' => 'Video',
            default => 'Outro arquivo',
        };
    }

    private function mediaStorageDirectory(string $type): string
    {
        return match ($type) {
            'image' => 'images',
            'audio' => 'audio',
            'video' => 'video',
            default => 'files',
        };
    }

    private function maxUploadSizeForType(string $type): int
    {
        return match ($type) {
            'image' => 8 * 1024 * 1024,
            'audio' => 25 * 1024 * 1024,
            'video' => 80 * 1024 * 1024,
            default => 8 * 1024 * 1024,
        };
    }

    private function allowedExtensionsForType(string $type): array
    {
        return match ($type) {
            'image' => self::IMAGE_EXTENSIONS,
            'audio' => self::AUDIO_EXTENSIONS,
            'video' => self::VIDEO_EXTENSIONS,
            default => [],
        };
    }

    private function allowedMimeTypesForType(string $type): array
    {
        return match ($type) {
            'image' => self::IMAGE_MIME_TYPES,
            'audio' => self::AUDIO_MIME_TYPES,
            'video' => self::VIDEO_MIME_TYPES,
            default => [],
        };
    }

    private function sanitizeUploadFolder(string $folder): string
    {
        $folder = trim(str_replace('\\', '/', $folder), '/');
        if ($folder === '') {
            return 'media';
        }

        $segments = array_values(array_filter(explode('/', $folder), static function (string $segment): bool {
            return $segment !== '' && $segment !== '.' && $segment !== '..';
        }));

        return implode('/', $segments);
    }

    private function validateUpload(mixed $file, ?string $forcedType = null): array
    {
        if (!is_array($file) || !isset($file['error'])) {
            return ['arquivo' => 'Selecione um arquivo para enviar.'];
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error == UPLOAD_ERR_NO_FILE) {
            return ['arquivo' => 'Selecione uma midia para enviar.'];
        }
        if ($error !== UPLOAD_ERR_OK) {
            return ['arquivo' => 'Falha no upload do arquivo.'];
        }

        $size = (int) ($file['size'] ?? 0);
        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return ['arquivo' => 'Arquivo enviado invalido.'];
        }

        $originalName = (string) ($file['name'] ?? 'arquivo');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mime = $this->detectMimeType($tmpName);
        $type = $this->detectMediaType($extension, $mime);

        if ($forcedType !== null && $forcedType !== '' && $type !== $forcedType) {
            return ['arquivo' => 'O arquivo enviado nao corresponde ao tipo esperado.'];
        }

        if ($type === 'other') {
            return ['arquivo' => 'Formato nao permitido. Envie imagem, audio ou video compativeis.'];
        }

        if ($size <= 0 || $size > $this->maxUploadSizeForType($type)) {
            return ['arquivo' => 'Envie um arquivo dentro do limite para ' . mb_strtolower($this->mediaTypeLabel($type)) . '.'];
        }

        if (!in_array($mime, $this->allowedMimeTypesForType($type), true)) {
            return ['arquivo' => 'MIME nao permitido para este tipo de midia.'];
        }

        if (!in_array($extension, $this->allowedExtensionsForType($type), true)) {
            return ['arquivo' => 'Extensao nao permitida para upload.'];
        }

        return [
            'ok' => true,
            'type' => $type,
            'mime' => $mime,
            'extension' => $extension,
        ];
    }
    private function resolveManagedFile(string $path): ?array
    {
        $relativePath = $this->normalizeManagedPath($path);
        if ($relativePath === null) {
            return null;
        }

        $absolutePath = $this->publicRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $uploadsRoot = realpath($this->managedUploadsRoot());
        if ($uploadsRoot === false) {
            return null;
        }

        $realFile = realpath($absolutePath);
        if ($realFile === false || !$this->pathStartsWith($realFile, $uploadsRoot) || !is_file($realFile)) {
            return null;
        }

        $fileInfo = new \SplFileInfo($realFile);
        $extension = strtolower($fileInfo->getExtension());
        $mime = $this->detectMimeType($realFile);
        $mediaType = $this->detectMediaType($extension, $mime);
        $isImage = $mediaType === 'image';
        $isAudio = $mediaType === 'audio';
        $isVideo = $mediaType === 'video';
        [$width, $height] = $isImage ? $this->detectDimensions($realFile) : [null, null];

        return [
            'name' => $fileInfo->getFilename(),
            'relative_path' => $relativePath,
            'absolute_path' => $realFile,
            'public_url' => url('/' . $relativePath),
            'size' => (int) $fileInfo->getSize(),
            'size_label' => $this->formatBytes((int) $fileInfo->getSize()),
            'mime' => $mime,
            'media_type' => $mediaType,
            'media_type_label' => $this->mediaTypeLabel($mediaType),
            'is_image' => $isImage,
            'is_audio' => $isAudio,
            'is_video' => $isVideo,
            'dimensions_label' => ($width && $height) ? ($width . ' x ' . $height) : '-',
            'modified_label' => date('d/m/Y H:i', (int) $fileInfo->getMTime()),
        ];
    }

    private function resolveManagedAbsoluteFromItem(array $item): ?string
    {
        $absolutePath = trim((string) ($item['absolute_path'] ?? ''));
        if ($absolutePath === '' || !is_file($absolutePath)) {
            return null;
        }

        $uploadsRoot = realpath($this->managedUploadsRoot());
        $realFile = realpath($absolutePath);
        if ($uploadsRoot === false || $realFile === false || !$this->pathStartsWith($realFile, $uploadsRoot)) {
            return null;
        }

        return $realFile;
    }

    private function normalizeManagedPath(string $path): ?string
    {
        $relativePath = trim(urldecode($path));
        if ($relativePath === '') {
            return null;
        }

        if (preg_match('~^https?://~i', $relativePath) === 1) {
            $parsedPath = parse_url($relativePath, PHP_URL_PATH);
            $relativePath = is_string($parsedPath) ? $parsedPath : '';
        }

        $relativePath = str_replace('\\', '/', $relativePath);
        $uploadsPos = strpos($relativePath, '/uploads/');
        if ($uploadsPos !== false) {
            $relativePath = substr($relativePath, $uploadsPos + 1);
        }

        $relativePath = ltrim($relativePath, '/');
        if ($relativePath === '' || !str_starts_with($relativePath, 'uploads/')) {
            return null;
        }

        return $relativePath;
    }

    private function normalizeFilters(array $query): array
    {
        return [
            'busca' => trim((string) ($query['busca'] ?? '')),
            'tipo' => trim((string) ($query['tipo'] ?? '')),
            'estado' => trim((string) ($query['estado'] ?? '')),
        ];
    }

    private function collectMediaUsage(): array
    {
        if (!$this->pdo instanceof \PDO) {
            return ['protected' => [], 'content' => [], 'references' => []];
        }

        $usage = ['protected' => [], 'content' => [], 'references' => []];

        foreach ($this->safeFetchAll('SELECT id, titulo, slug, imagem_capa, imagem_thumb, conteudo FROM posts') as $row) {
            $postId = (int) ($row['id'] ?? 0);
            $postSlug = trim((string) ($row['slug'] ?? ''));
            $postTitle = $this->cleanPostTitle((string) ($row['titulo'] ?? ''));
            $postAdminUrl = $postId > 0 ? url('/admin/editar-post?id=' . $postId) : '';

            foreach (['imagem_capa' => 'Capa', 'imagem_thumb' => 'Thumb'] as $field => $contextLabel) {
                foreach ($this->extractTrackedPaths((string) ($row[$field] ?? '')) as $path) {
                    $usage['protected'][$path] = true;
                    $this->registerMediaReference($usage['references'], $path, [
                        'type' => 'post',
                        'type_label' => 'Post',
                        'id' => $postId,
                        'slug' => $postSlug,
                        'title' => $postTitle,
                        'context' => $contextLabel,
                        'admin_url' => $postAdminUrl,
                        'dedupe_key' => 'post|' . $postId . '|' . $field,
                    ]);
                }
            }

            foreach ($this->extractTrackedPaths((string) ($row['conteudo'] ?? '')) as $path) {
                $usage['content'][$path] = true;
                $this->registerMediaReference($usage['references'], $path, [
                    'type' => 'post',
                    'type_label' => 'Post',
                    'id' => $postId,
                    'slug' => $postSlug,
                    'title' => $postTitle,
                    'context' => 'Conteudo',
                    'admin_url' => $postAdminUrl,
                    'dedupe_key' => 'post|' . $postId . '|conteudo|' . $path,
                ]);
            }
        }

        foreach ($this->safeFetchAll('SELECT id, titulo, slug, imagem FROM links') as $row) {
            $linkId = (int) ($row['id'] ?? 0);
            $linkSlug = trim((string) ($row['slug'] ?? ''));
            $linkTitle = trim((string) ($row['titulo'] ?? ''));
            $linkAdminUrl = $linkId > 0 ? url('/admin/editar-link?id=' . $linkId) : '';

            foreach ($this->extractTrackedPaths((string) ($row['imagem'] ?? '')) as $path) {
                $usage['protected'][$path] = true;
                $this->registerMediaReference($usage['references'], $path, [
                    'type' => 'link',
                    'type_label' => 'Link',
                    'id' => $linkId,
                    'slug' => $linkSlug,
                    'title' => $linkTitle,
                    'context' => 'Imagem',
                    'admin_url' => $linkAdminUrl,
                    'dedupe_key' => 'link|' . $linkId . '|imagem',
                ]);
            }
        }

        $configImageKeys = ['logo_url', 'brand_symbol_url', 'favicon_url', 'bio_avatar_url', 'sobre_imagem_url'];
        foreach ($this->safeFetchAll('SELECT chave, valor FROM configuracoes') as $row) {
            $key = trim((string) ($row['chave'] ?? ''));
            if ($key === '') {
                continue;
            }

            $isImageKey = in_array($key, $configImageKeys, true);
            foreach ($this->extractTrackedPaths((string) ($row['valor'] ?? '')) as $path) {
                $usage['protected'][$path] = true;
                if (!$isImageKey) {
                    $usage['content'][$path] = true;
                }

                $this->registerMediaReference($usage['references'], $path, [
                    'type' => 'config',
                    'type_label' => 'Configuracao',
                    'id' => 0,
                    'slug' => $key,
                    'title' => $this->cleanConfigKeyLabel($key),
                    'context' => $isImageKey ? 'Imagem' : 'Referencia',
                    'admin_url' => url('/admin/configuracoes'),
                    'dedupe_key' => 'config|' . $key . '|' . $path,
                ]);
            }
        }

        foreach ($this->safeFetchAll('SELECT id, nome, usuario, avatar_tipo, avatar_imagem FROM usuarios') as $row) {
            if (trim((string) ($row['avatar_tipo'] ?? '')) !== 'foto') {
                continue;
            }

            $userId = (int) ($row['id'] ?? 0);
            $userName = trim((string) ($row['nome'] ?? ''));
            $username = trim((string) ($row['usuario'] ?? ''));
            $userAdminUrl = $userId > 0 ? url('/admin/editar-usuario?id=' . $userId) : '';

            foreach ($this->extractTrackedPaths((string) ($row['avatar_imagem'] ?? '')) as $path) {
                $usage['protected'][$path] = true;
                $this->registerMediaReference($usage['references'], $path, [
                    'type' => 'user',
                    'type_label' => 'Usuario',
                    'id' => $userId,
                    'slug' => $username,
                    'title' => $userName !== '' ? $userName : $username,
                    'context' => 'Avatar',
                    'admin_url' => $userAdminUrl,
                    'dedupe_key' => 'user|' . $userId . '|avatar',
                ]);
            }
        }

        $this->collectTemplateAssetReferences($usage);

        return $usage;
    }

    private function isMediaInUse(string $relativePath, array $usage): bool
    {
        $relativePath = ltrim($relativePath, '/');

        if (isset(($usage['protected'] ?? [])[$relativePath])) {
            return true;
        }

        if (isset(($usage['content'] ?? [])[$relativePath])) {
            return true;
        }

        $references = $usage['references'][$relativePath] ?? [];
        if (is_array($references) && $references !== []) {
            return true;
        }

        return false;
    }

    private function isOrphanMediaItem(string $relativePath, array $usage): bool
    {
        $relativePath = ltrim($relativePath, '/');
        $isManagedOrphanCandidate = str_starts_with($relativePath, 'uploads/posts/')
            || str_starts_with($relativePath, 'uploads/links/');

        if (!$isManagedOrphanCandidate) {
            return false;
        }

        return !$this->isMediaInUse($relativePath, $usage);
    }

    private function resolveUsageState(string $relativePath, array $usage, bool $isManagedUpload, string $libraryKey): string
    {
        if ($libraryKey === 'assets/brand' || !$isManagedUpload) {
            return 'institutional';
        }

        if ($this->isMediaInUse($relativePath, $usage)) {
            return 'in_use';
        }

        if ($this->isOrphanMediaItem($relativePath, $usage)) {
            return 'orphan';
        }

        return 'available';
    }

    private function statusLabel(string $usageState): string
    {
        return match ($usageState) {
            'in_use' => 'Em uso',
            'orphan' => 'Orfa',
            'institutional' => 'Institucional',
            default => 'Disponivel',
        };
    }

    private function linkedPostsForPath(string $relativePath, array $usage): array
    {
        $items = [];
        foreach ($this->linkedEntitiesForPath($relativePath, $usage) as $reference) {
            if ((string) ($reference['type'] ?? '') !== 'post') {
                continue;
            }

            $postId = (int) ($reference['id'] ?? 0);
            if ($postId <= 0) {
                continue;
            }

            $items[$postId] = [
                'id' => $postId,
                'slug' => trim((string) ($reference['slug'] ?? '')),
                'title' => trim((string) ($reference['title'] ?? '')),
            ];
        }

        return array_values($items);
    }

    private function linkedEntitiesForPath(string $relativePath, array $usage): array
    {
        $relativePath = ltrim($relativePath, '/');
        $references = $usage['references'] ?? [];
        $items = $references[$relativePath] ?? [];
        if (!is_array($items) || $items === []) {
            return [];
        }

        return array_values(array_map(static function (array $item): array {
            unset($item['_key']);
            return $item;
        }, array_values(array_filter($items, static fn (mixed $item): bool => is_array($item)))));
    }

    private function registerMediaReference(array &$references, string $path, array $reference): void
    {
        $path = ltrim(trim($path), '/');
        $type = trim((string) ($reference['type'] ?? ''));
        if ($path === '' || $type === '') {
            return;
        }

        $normalized = [
            'type' => $type,
            'type_label' => trim((string) ($reference['type_label'] ?? $this->referenceTypeLabel($type))),
            'id' => (int) ($reference['id'] ?? 0),
            'slug' => trim((string) ($reference['slug'] ?? '')),
            'title' => trim((string) ($reference['title'] ?? '')),
            'context' => trim((string) ($reference['context'] ?? '')),
            'admin_url' => trim((string) ($reference['admin_url'] ?? '')),
        ];

        if ($normalized['title'] === '' && $normalized['slug'] !== '') {
            $normalized['title'] = $normalized['slug'];
        }

        $dedupeKey = trim((string) ($reference['dedupe_key'] ?? ''));
        if ($dedupeKey === '') {
            $dedupeKey = implode('|', [
                $normalized['type'],
                (string) $normalized['id'],
                $normalized['slug'],
                $normalized['title'],
                $normalized['context'],
            ]);
        }
        $normalized['_key'] = $dedupeKey;

        if (!isset($references[$path]) || !is_array($references[$path])) {
            $references[$path] = [];
        }

        foreach ($references[$path] as $existing) {
            if ((string) ($existing['_key'] ?? '') === $dedupeKey) {
                return;
            }
        }

        $references[$path][] = $normalized;
    }

    private function groupReferencesByType(array $references): array
    {
        $grouped = [];
        foreach ($references as $reference) {
            $type = trim((string) ($reference['type'] ?? ''));
            if ($type === '') {
                continue;
            }

            if (!isset($grouped[$type])) {
                $grouped[$type] = [
                    'type' => $type,
                    'label' => trim((string) ($reference['type_label'] ?? $this->referenceTypeLabel($type))),
                    'count' => 0,
                ];
            }
            $grouped[$type]['count']++;
        }

        $result = array_values($grouped);
        usort($result, static function (array $left, array $right): int {
            $countDiff = ((int) ($right['count'] ?? 0)) <=> ((int) ($left['count'] ?? 0));
            if ($countDiff !== 0) {
                return $countDiff;
            }
            return strcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        return $result;
    }

    private function buildLinkedEntitiesLabel(array $grouped): string
    {
        if ($grouped === []) {
            return '';
        }

        $parts = [];
        foreach ($grouped as $item) {
            $label = trim((string) ($item['label'] ?? ''));
            $count = max(0, (int) ($item['count'] ?? 0));
            if ($label === '' || $count <= 0) {
                continue;
            }
            $parts[] = $label . ': ' . $count;
        }

        return implode(' | ', $parts);
    }

    private function referenceTypeLabel(string $type): string
    {
        return match ($type) {
            'post' => 'Post',
            'link' => 'Link',
            'config' => 'Configuracao',
            'user' => 'Usuario',
            'template' => 'Template',
            default => 'Outro',
        };
    }

    private function safeFetchAll(string $sql): array
    {
        if (!$this->pdo instanceof \PDO) {
            return [];
        }

        try {
            $stmt = $this->pdo->query($sql);
            if (!$stmt) {
                return [];
            }

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function extractTrackedPaths(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $candidates = [$value];
        preg_match_all('~(?:uploads|assets/brand)/[^"\')\s<>]+~i', $value, $matches);
        foreach (($matches[0] ?? []) as $match) {
            $candidates[] = (string) $match;
        }

        $paths = [];
        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeTrackedPath((string) $candidate);
            if ($normalized === null) {
                continue;
            }
            $paths[$normalized] = true;
        }

        return array_keys($paths);
    }

    private function normalizeTrackedPath(string $path): ?string
    {
        $relativePath = trim(urldecode($path));
        if ($relativePath === '') {
            return null;
        }

        if (preg_match('~^https?://~i', $relativePath) === 1) {
            $parsedPath = parse_url($relativePath, PHP_URL_PATH);
            $relativePath = is_string($parsedPath) ? $parsedPath : '';
        }

        $relativePath = str_replace('\\', '/', $relativePath);
        $uploadsPos = strpos($relativePath, '/uploads/');
        if ($uploadsPos !== false) {
            $relativePath = substr($relativePath, $uploadsPos + 1);
        }

        $brandPos = strpos($relativePath, '/assets/brand/');
        if ($brandPos !== false) {
            $relativePath = substr($relativePath, $brandPos + 1);
        }

        $relativePath = ltrim($relativePath, '/');
        if ($relativePath === '') {
            return null;
        }

        if (!str_starts_with($relativePath, 'uploads/') && !str_starts_with($relativePath, 'assets/brand/')) {
            return null;
        }

        return $relativePath;
    }

    private function collectTemplateAssetReferences(array &$usage): void
    {
        $viewsRoot = base_path('app/Views');
        if (!is_dir($viewsRoot)) {
            return;
        }

        $projectRoot = base_path();
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewsRoot, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            $extension = strtolower($fileInfo->getExtension());
            if (!in_array($extension, ['php', 'html'], true)) {
                continue;
            }

            $content = @file_get_contents($fileInfo->getPathname());
            if (!is_string($content) || $content === '') {
                continue;
            }

            preg_match_all("~(?:^|[\\s\"'\\(=])/?(assets/brand/[a-z0-9._/-]+)~i", $content, $matches);
            $assetPaths = $matches[1] ?? [];
            if (!is_array($assetPaths) || $assetPaths === []) {
                continue;
            }

            $absoluteFile = $fileInfo->getPathname();
            $relativeFile = str_replace('\\', '/', substr($absoluteFile, strlen($projectRoot) + 1));
            foreach ($assetPaths as $assetPath) {
                $normalized = $this->normalizeTrackedPath((string) $assetPath);
                if ($normalized === null) {
                    continue;
                }

                $usage['protected'][$normalized] = true;
                $this->registerMediaReference($usage['references'], $normalized, [
                    'type' => 'template',
                    'type_label' => 'Template',
                    'id' => 0,
                    'slug' => $relativeFile,
                    'title' => $relativeFile,
                    'context' => 'Referencia',
                    'admin_url' => '',
                    'dedupe_key' => 'template|' . $relativeFile . '|' . $normalized,
                ]);
            }
        }
    }

    private function cleanConfigKeyLabel(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return 'Configuracao';
        }

        $label = str_replace('_', ' ', mb_strtolower($key));
        $label = preg_replace('/\s+/', ' ', trim($label)) ?? $label;
        return mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');
    }

    private function cleanPostTitle(string $title): string
    {
        $title = preg_replace('/\[\[(.*?)\]\]/u', '$1', $title) ?? $title;
        $title = preg_replace('/\s+/', ' ', trim($title)) ?? trim($title);
        return $title;
    }

    private function extractPostSlug(string $relativePath): string
    {
        if (preg_match('~^uploads/posts/([^/]+)/~', $relativePath, $match)) {
            return (string) ($match[1] ?? '');
        }

        return '';
    }

    private function normalizeSortDir(string $sort, string $dir): array
    {
        $allowed = ['data', 'nome', 'tamanho', 'tipo'];
        if (!in_array($sort, $allowed, true)) {
            $sort = 'data';
        }

        return [$sort, strtolower(trim($dir)) === 'asc' ? 'asc' : 'desc'];
    }

    private function nextAvailableFilename(string $directory, string $base, string $extension): string
    {
        $candidate = $base . '.' . $extension;
        $counter = 2;
        while (is_file($directory . DIRECTORY_SEPARATOR . $candidate)) {
            $candidate = $base . '-' . $counter . '.' . $extension;
            $counter++;
        }

        return $candidate;
    }

    private function isPostMediaShortBase(string $base): bool
    {
        return in_array($base, ['img', 'vid', 'aud', 'nar', 'amb'], true);
    }

    private function nextSequencedFilename(string $directory, string $base, string $extension): string
    {
        $counter = 1;
        do {
            $candidate = sprintf('%s-%03d.%s', $base, $counter, $extension);
            $counter++;
        } while (is_file($directory . DIRECTORY_SEPARATOR . $candidate));

        return $candidate;
    }

    private function preparePostBodyImageTarget(string $slug): array
    {
        return $this->preparePostMediaTarget($slug, 'image');
    }

    private function preparePostMediaTarget(string $slug, string $type, string $postRole = '', string $audioRole = ''): array
    {
        $slug = $this->slugify($slug);
        $type = $this->normalizeRequestedMediaType($type);
        $postRole = $this->slugify($postRole);
        $audioRole = $this->normalizeAudioRole($audioRole);

        if ($slug === '' || $type === '') {
            return ['ok' => false, 'error' => 'Nao foi possivel preparar o destino da midia do post.'];
        }

        if ($type === 'image' && $postRole !== '' && !in_array($postRole, ['capa', 'thumb'], true)) {
            $postRole = '';
        }

        $storageDir = 'posts/' . $slug . '/' . $this->mediaStorageDirectory($type);
        $relativeDir = 'uploads/' . $storageDir;
        $absoluteDir = $this->publicRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            return ['ok' => false, 'error' => 'Nao foi possivel criar a pasta do post.'];
        }

        $base = match ($type) {
            'image' => $postRole !== '' ? $postRole : 'img',
            'audio' => match ($audioRole) {
                'narracao' => 'nar',
                'ambiente' => 'amb',
                default => 'aud',
            },
            'video' => 'vid',
            default => 'file',
        };

        return [
            'ok' => true,
            'storage_dir' => $storageDir,
            'relative_dir' => $relativeDir,
            'absolute_dir' => $absoluteDir,
            'base' => $base,
            'overwrite' => $type === 'image' && $postRole !== '',
        ];
    }

    private function deleteFilesByBase(string $directory, string $base): void
    {
        foreach (glob($directory . DIRECTORY_SEPARATOR . $base . '.*') ?: [] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function cleanupLegacyPostRoleImage(string $slug, string $role): void
    {
        $directory = $this->publicRoot()
            . DIRECTORY_SEPARATOR
            . 'uploads'
            . DIRECTORY_SEPARATOR
            . 'posts'
            . DIRECTORY_SEPARATOR
            . $slug
            . DIRECTORY_SEPARATOR
            . 'images';

        if (!is_dir($directory)) {
            return;
        }

        $this->deleteFilesByBase($directory, $slug . '-' . $role);
    }

    private function detectMimeType(string $path): string
    {
        try {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($path);
            return is_string($mime) && $mime !== '' ? $mime : 'application/octet-stream';
        } catch (\Throwable) {
            return 'application/octet-stream';
        }
    }

    private function detectDimensions(string $path): array
    {
        $info = @getimagesize($path);
        if (!is_array($info) || !isset($info[0], $info[1])) {
            return [null, null];
        }

        return [(int) $info[0], (int) $info[1]];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB'];
        $value = $bytes / 1024;
        foreach ($units as $unit) {
            if ($value < 1024 || $unit === 'GB') {
                return number_format($value, $value < 10 ? 1 : 0, ',', '.') . ' ' . $unit;
            }
            $value /= 1024;
        }

        return number_format($value, 1, ',', '.') . ' GB';
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
        return trim(mb_substr($value, 0, 120), '-');
    }

    private function clampInt(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }

    private function ensureDirectories(): void
    {
        $uploads = $this->uploadsRoot();
        if (!is_dir($uploads)) {
            mkdir($uploads, 0775, true);
        }

        foreach ([
            $uploads . DIRECTORY_SEPARATOR . 'media',
            $uploads . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'images',
            $uploads . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'audio',
            $uploads . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'video',
            $uploads . DIRECTORY_SEPARATOR . 'posts',
            $uploads . DIRECTORY_SEPARATOR . 'configuracoes',
        ] as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }
        }
    }

    private function publicRoot(): string
    {
        $configured = trim((string) env('PUBLIC_ROOT', ''));
        if ($configured !== '') {
            $realConfigured = realpath($configured);
            if (is_string($realConfigured) && is_dir($realConfigured)) {
                return $realConfigured;
            }
        }

        $projectRoot = base_path();
        $fallbackPublic = base_path('public');

        if (basename(str_replace('\\', '/', $projectRoot)) === '_app_core') {
            $parentRoot = dirname($projectRoot);
            $parentUploads = $parentRoot . DIRECTORY_SEPARATOR . 'uploads';
            $parentAssets = $parentRoot . DIRECTORY_SEPARATOR . 'assets';
            if (is_dir($parentUploads) || is_dir($parentAssets)) {
                return $parentRoot;
            }
        }

        return $fallbackPublic;
    }

    private function uploadsRoot(): string
    {
        return base_path('public/uploads');
    }

    private function managedUploadsRoot(): string
    {
        $publicRootUploads = $this->publicRoot() . DIRECTORY_SEPARATOR . 'uploads';
        if (is_dir($publicRootUploads)) {
            return $publicRootUploads;
        }

        return $this->uploadsRoot();
    }

    private function pathStartsWith(string $path, string $prefix): bool
    {
        $normalize = static function (string $value): string {
            $value = str_replace('\\', '/', $value);
            return rtrim($value, '/');
        };

        $left = $normalize($path);
        $right = $normalize($prefix);

        if (DIRECTORY_SEPARATOR === '\\') {
            $left = mb_strtolower($left, 'UTF-8');
            $right = mb_strtolower($right, 'UTF-8');
        }

        return str_starts_with($left, $right);
    }
}
