<?php
declare(strict_types=1);

namespace App\Services\Admin;

use finfo;

final class MidiaService
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];
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

        $usage = $this->collectPostMediaUsage();
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
            'upload' => ['accept' => '.jpg,.jpeg,.png,.webp,.gif,.svg', 'max_size_label' => '8 MB'],
        ];
    }

    public function recentImages(int $limit = 12): array
    {
        $items = array_values(array_filter($this->scanMediaItems($this->collectPostMediaUsage()), static fn (array $item): bool => ($item['is_image'] ?? false) === true));
        $items = $this->sortItems($items, 'data', 'desc');
        return array_slice($items, 0, max(1, $limit));
    }

    public function storeUploadedImage(mixed $file, string $folder = 'posts', ?string $preferredBase = null, bool $overwrite = false): array
    {
        $this->ensureDirectories();

        if (!is_array($file) || !isset($file['error'])) {
            return ['ok' => true, 'skipped' => true, 'path' => null];
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'skipped' => true, 'path' => null];
        }

        $validation = $this->validateUpload($file);
        if ($validation !== []) {
            return ['ok' => false, 'error' => (string) ($validation['arquivo'] ?? 'Falha no upload da imagem.')];
        }

        $originalName = (string) ($file['name'] ?? 'imagem');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $baseName = $preferredBase ?? pathinfo($originalName, PATHINFO_FILENAME);
        $safeBase = $this->slugify($baseName !== '' ? $baseName : 'imagem');
        if ($safeBase === '') {
            $safeBase = 'imagem';
        }

        $relativeDir = 'uploads/' . trim($folder, '/');
        $absoluteDir = $this->publicRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            return ['ok' => false, 'error' => 'Nao foi possivel criar a pasta de destino do upload.'];
        }

        if ($overwrite) {
            $this->deleteFilesByBase($absoluteDir, $safeBase);
            $filename = $safeBase . '.' . $extension;
        } else {
            $filename = $this->nextAvailableFilename($absoluteDir, $safeBase, $extension);
        }

        $target = $absoluteDir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            return ['ok' => false, 'error' => 'Nao foi possivel mover o arquivo enviado.'];
        }

        return ['ok' => true, 'skipped' => false, 'path' => $relativeDir . '/' . $filename];
    }

    public function storePostRoleImage(mixed $file, string $slug, string $role): array
    {
        $slug = $this->slugify($slug);
        $role = $this->slugify($role);
        if ($slug === '' || $role === '') {
            return ['ok' => false, 'error' => 'Nao foi possivel preparar o nome da imagem do post.'];
        }

        return $this->storeUploadedImage($file, 'posts/' . $slug, $slug . '-' . $role, true);
    }

    public function storePostBodyImage(mixed $file, string $slug): array
    {
        $slug = $this->slugify($slug);
        if ($slug === '') {
            return ['ok' => false, 'error' => 'Nao foi possivel preparar o nome da imagem do conteudo.'];
        }

        $relativeDir = 'uploads/posts/' . $slug;
        $absoluteDir = $this->publicRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            return ['ok' => false, 'error' => 'Nao foi possivel criar a pasta do post.'];
        }

        $sequence = $this->nextPostBodySequence($absoluteDir, $slug);
        $base = sprintf('%s-%02d', $slug, $sequence);

        return $this->storeUploadedImage($file, 'posts/' . $slug, $base, false);
    }

    public function upload(mixed $file, array $query = []): array
    {
        $result = $this->storeUploadedImage($file, 'media/' . date('Y/m'));
        if (($result['ok'] ?? false) !== true) {
            return ['ok' => false, 'viewModel' => $this->getIndexViewModel($query, ['arquivo' => (string) ($result['error'] ?? 'Falha no upload do arquivo.')])];
        }

        return ['ok' => true, 'path' => $result['path'] ?? null];
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
        $items = $this->applyFilters($this->scanMediaItems($this->collectPostMediaUsage()), $filters);
        $removed = 0;

        foreach ($items as $item) {
            if (($item['is_orphan'] ?? false) !== true || ($item['is_managed_upload'] ?? false) !== true) {
                continue;
            }

            $resolved = $this->resolveManagedFile((string) ($item['relative_path'] ?? ''));
            if ($resolved !== null && is_file($resolved['absolute_path']) && @unlink($resolved['absolute_path'])) {
                $removed++;
            }
        }

        return ['ok' => true, 'removed' => $removed];
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
                $isImage = in_array($extension, self::IMAGE_EXTENSIONS, true);
                $size = (int) $fileInfo->getSize();
                $modifiedAt = (int) $fileInfo->getMTime();
                $directory = trim(str_replace('\\', '/', substr($fileInfo->getPath(), strlen($this->publicRoot()) + 1)), '/');
                $mime = $this->detectMimeType($absolutePath);
                [$width, $height] = $isImage ? $this->detectDimensions($absolutePath) : [null, null];

                $isManagedUpload = str_starts_with($relativePath, 'uploads/');
                $usageState = $this->resolveUsageState($relativePath, $usage, $isManagedUpload, $relativeRoot);
                $linkedPosts = $this->linkedPostsForPath($relativePath, $usage);
                $primaryPost = $linkedPosts[0] ?? null;
                $primaryPostSlug = trim((string) ($primaryPost['slug'] ?? ''));
                $primaryPostTitle = trim((string) ($primaryPost['title'] ?? ''));
                $fallbackSlug = $this->extractPostSlug($relativePath);
                $postSlug = $primaryPostSlug !== '' ? $primaryPostSlug : $fallbackSlug;
                $postFilterUrl = $postSlug !== '' ? url('/admin/posts?busca=' . rawurlencode($postSlug)) : '';

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
                    'post_slug' => $postSlug,
                    'post_title' => $primaryPostTitle,
                    'post_filter_url' => $postFilterUrl,
                    'size' => $size,
                    'size_label' => $this->formatBytes($size),
                    'modified_at' => $modifiedAt,
                    'modified_label' => date('d/m/Y H:i', $modifiedAt),
                    'mime' => $mime,
                    'is_image' => $isImage,
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
                $haystack = mb_strtolower(implode(' ', [(string) ($item['name'] ?? ''), (string) ($item['directory'] ?? ''), (string) ($item['mime'] ?? ''), (string) ($item['post_slug'] ?? '')]));
                if (!str_contains($haystack, $busca)) {
                    return false;
                }
            }

            if ($tipo === 'imagem' && (($item['is_image'] ?? false) !== true)) {
                return false;
            }

            if ($tipo === 'outros' && (($item['is_image'] ?? false) === true)) {
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
        $size = 0;
        $institutional = 0;
        $managedUploads = 0;
        $postMedia = 0;

        foreach ($items as $item) {
            $directory = (string) ($item['directory'] ?? 'uploads');
            $directories[$directory] = true;
            $size += (int) ($item['size'] ?? 0);

            if (($item['is_image'] ?? false) === true) {
                $images++;
            }

            if ((string) ($item['library'] ?? '') === 'Institucional') {
                $institutional++;
            }

            if (($item['is_managed_upload'] ?? false) === true) {
                $managedUploads++;
            }

            if ((string) ($item['usage_state'] ?? '') === 'in_use') {
                $postMedia++;
            }
        }

        $total = count($items);
        $others = max(0, $total - $images);
        $orphans = count(array_filter($items, static fn (array $item): bool => ($item['is_orphan'] ?? false) === true));
        $coveragePosts = $managedUploads > 0 ? ($postMedia / $managedUploads) * 100 : 0.0;
        $orphanRate = $managedUploads > 0 ? ($orphans / $managedUploads) * 100 : 0.0;
        $averageSize = $total > 0 ? (int) round($size / $total) : 0;

        return [
            'total' => $total,
            'images' => $images,
            'others' => $others,
            'directories' => count($directories),
            'institutional' => $institutional,
            'managed_uploads' => $managedUploads,
            'post_media' => $postMedia,
            'orphans' => $orphans,
            'coverage_posts' => $coveragePosts,
            'orphan_rate' => $orphanRate,
            'size_bytes' => $size,
            'size_label' => $this->formatBytes($size),
            'average_size_bytes' => $averageSize,
            'average_size_label' => $this->formatBytes($averageSize),
        ];
    }

    private function validateUpload(mixed $file): array
    {
        if (!is_array($file) || !isset($file['error'])) {
            return ['arquivo' => 'Selecione um arquivo para enviar.'];
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['arquivo' => 'Selecione uma imagem para enviar.'];
        }
        if ($error !== UPLOAD_ERR_OK) {
            return ['arquivo' => 'Falha no upload do arquivo.'];
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > 8 * 1024 * 1024) {
            return ['arquivo' => 'Envie uma imagem de ate 8 MB.'];
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return ['arquivo' => 'Arquivo enviado invalido.'];
        }

        $mime = $this->detectMimeType($tmpName);
        if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            return ['arquivo' => 'Formato nao permitido. Envie JPG, PNG, WEBP, GIF ou SVG.'];
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            return ['arquivo' => 'Extensao nao permitida para upload.'];
        }

        return [];
    }

    private function resolveManagedFile(string $path): ?array
    {
        $relativePath = trim(urldecode($path));
        if ($relativePath === '') {
            return null;
        }

        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (!str_starts_with($relativePath, 'uploads/')) {
            return null;
        }

        $absolutePath = $this->publicRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $uploadsRoot = realpath($this->uploadsRoot());
        if ($uploadsRoot === false) {
            return null;
        }

        $realFile = realpath($absolutePath);
        if ($realFile === false || !str_starts_with($realFile, $uploadsRoot) || !is_file($realFile)) {
            return null;
        }

        $fileInfo = new \SplFileInfo($realFile);
        $extension = strtolower($fileInfo->getExtension());
        $isImage = in_array($extension, self::IMAGE_EXTENSIONS, true);
        [$width, $height] = $isImage ? $this->detectDimensions($realFile) : [null, null];

        return [
            'name' => $fileInfo->getFilename(),
            'relative_path' => $relativePath,
            'absolute_path' => $realFile,
            'public_url' => url('/' . $relativePath),
            'size' => (int) $fileInfo->getSize(),
            'size_label' => $this->formatBytes((int) $fileInfo->getSize()),
            'mime' => $this->detectMimeType($realFile),
            'is_image' => $isImage,
            'dimensions_label' => ($width && $height) ? ($width . ' x ' . $height) : '-',
            'modified_label' => date('d/m/Y H:i', (int) $fileInfo->getMTime()),
        ];
    }

    private function normalizeFilters(array $query): array
    {
        return [
            'busca' => trim((string) ($query['busca'] ?? '')),
            'tipo' => trim((string) ($query['tipo'] ?? '')),
            'estado' => trim((string) ($query['estado'] ?? '')),
        ];
    }

    private function collectPostMediaUsage(): array
    {
        if (!$this->pdo instanceof \PDO) {
            return ['protected' => [], 'content' => []];
        }

        $protected = [];
        $content = [];

        $stmt = $this->pdo->query('SELECT id, titulo, slug, imagem_capa, imagem_thumb, conteudo FROM posts');
        if (!$stmt) {
            return ['protected' => [], 'content' => []];
        }

        $references = [];

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $postRef = [
                'id' => (int) ($row['id'] ?? 0),
                'slug' => trim((string) ($row['slug'] ?? '')),
                'title' => $this->cleanPostTitle((string) ($row['titulo'] ?? '')),
            ];

            foreach (['imagem_capa', 'imagem_thumb'] as $field) {
                $path = ltrim(trim((string) ($row[$field] ?? '')), '/');
                if ($path !== '') {
                    $protected[$path] = true;
                    $this->registerMediaReference($references, $path, $postRef);
                }
            }

            preg_match_all('~uploads/[^"\')\s<>]+~i', (string) ($row['conteudo'] ?? ''), $matches);
            foreach (($matches[0] ?? []) as $path) {
                $clean = ltrim((string) $path, '/');
                if ($clean !== '') {
                    $content[$clean] = true;
                    $this->registerMediaReference($references, $clean, $postRef);
                }
            }
        }

        return ['protected' => $protected, 'content' => $content, 'references' => $references];
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

        return false;
    }

    private function isOrphanMediaItem(string $relativePath, array $usage): bool
    {
        $relativePath = ltrim($relativePath, '/');
        if (!str_starts_with($relativePath, 'uploads/posts/')) {
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
        $relativePath = ltrim($relativePath, '/');
        $references = $usage['references'] ?? [];
        $items = $references[$relativePath] ?? [];

        if (!is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, static fn (mixed $item): bool => is_array($item)));
    }

    private function registerMediaReference(array &$references, string $path, array $postRef): void
    {
        $path = ltrim(trim($path), '/');
        $postId = (int) ($postRef['id'] ?? 0);
        if ($path === '' || $postId <= 0) {
            return;
        }

        if (!isset($references[$path]) || !is_array($references[$path])) {
            $references[$path] = [];
        }

        foreach ($references[$path] as $existing) {
            if ((int) ($existing['id'] ?? 0) === $postId) {
                return;
            }
        }

        $references[$path][] = [
            'id' => $postId,
            'slug' => trim((string) ($postRef['slug'] ?? '')),
            'title' => trim((string) ($postRef['title'] ?? '')),
        ];
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

    private function nextPostBodySequence(string $directory, string $slug): int
    {
        $prefix = $slug . '-';
        $max = 0;

        foreach (glob($directory . DIRECTORY_SEPARATOR . $slug . '-*') ?: [] as $path) {
            $basename = pathinfo($path, PATHINFO_FILENAME);
            if (!str_starts_with($basename, $prefix)) {
                continue;
            }

            $suffix = substr($basename, strlen($prefix));
            if ($suffix === 'capa' || $suffix === 'thumb') {
                continue;
            }

            if (preg_match('/^(\d{2,})$/', $suffix, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return $max + 1;
    }

    private function deleteFilesByBase(string $directory, string $base): void
    {
        foreach (glob($directory . DIRECTORY_SEPARATOR . $base . '.*') ?: [] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
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

        $media = $uploads . DIRECTORY_SEPARATOR . 'media';
        if (!is_dir($media)) {
            mkdir($media, 0775, true);
        }

        $posts = $uploads . DIRECTORY_SEPARATOR . 'posts';
        if (!is_dir($posts)) {
            mkdir($posts, 0775, true);
        }

        $settings = $uploads . DIRECTORY_SEPARATOR . 'configuracoes';
        if (!is_dir($settings)) {
            mkdir($settings, 0775, true);
        }
    }

    private function publicRoot(): string
    {
        return base_path('public');
    }

    private function uploadsRoot(): string
    {
        return base_path('public/uploads');
    }
}
