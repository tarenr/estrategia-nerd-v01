<?php

declare(strict_types=1);


namespace Scripts\ContentSync;

use PDO;
use RuntimeException;
use ZipArchive;

final class ContentSyncManager
{
    public function __construct(private array $config)
    {
        date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Sao_Paulo');
    }

    public function export(string $profileName = 'local'): array
    {
        $profile = $this->profile($profileName);
        $root = $this->packageRoot();
        $lock = $this->acquireRunLock($root, 'export', $profileName, (string) ($profile['label'] ?? $profileName));
        $packageId = (string) ($profile['slug'] ?? $profileName) . '_' . date('Y-m-d_H-i-s');
        $packageDir = $root . DIRECTORY_SEPARATOR . $packageId;
        $dataDir = $packageDir . DIRECTORY_SEPARATOR . 'data';

        if (!is_dir($dataDir) && !mkdir($dataDir, 0777, true) && !is_dir($dataDir)) {
            $this->releaseRunLock($lock);
            throw new RuntimeException('Nao foi possivel criar a pasta do pacote: ' . $packageDir);
        }

        $manifest = [
            'package_id' => $packageId,
            'source_profile' => $profileName,
            'source_profile_label' => (string) ($profile['label'] ?? $profileName),
            'created_at' => date('c'),
            'status' => 'running',
            'is_valid' => false,
            'applied_targets' => [],
            'data_files' => [],
            'uploads' => ['name' => 'uploads.zip', 'included_files' => 0, 'size_bytes' => 0, 'sha1' => null],
            'stats' => ['categories' => 0, 'posts' => 0, 'post_slug_history' => 0, 'links' => 0, 'configuracoes' => 0],
            'error' => null,
        ];

        $tmpDir = '';

        try {
            $pdo = $this->connectPdo((array) ($profile['database'] ?? []));
            $payload = $this->exportPayload($pdo);

            foreach ($payload['files'] as $fileName => $rows) {
                $filePath = $dataDir . DIRECTORY_SEPARATOR . $fileName;
                $this->writeJson($filePath, $rows);
                $manifest['data_files'][$fileName] = $this->fileDetails($filePath, 'data/' . $fileName);
            }

            $manifest['stats'] = [
                'categories' => count((array) ($payload['files']['categoria_post.json'] ?? [])),
                'posts' => count((array) ($payload['files']['posts.json'] ?? [])),
                'post_slug_history' => count((array) ($payload['files']['post_slug_history.json'] ?? [])),
                'links' => count((array) ($payload['files']['links.json'] ?? [])),
                'configuracoes' => count((array) ($payload['files']['configuracoes.json'] ?? [])),
            ];

            $tmpDir = $this->materializeUploadsSubset((array) ($profile['uploads'] ?? []), (array) ($payload['upload_paths'] ?? []));
            $zipPath = $packageDir . DIRECTORY_SEPARATOR . 'uploads.zip';
            $this->compressDirectory($tmpDir, $zipPath);
            $manifest['uploads'] = $this->fileDetails($zipPath, 'uploads.zip');
            $manifest['uploads']['included_files'] = count((array) ($payload['upload_paths'] ?? []));
            $manifest['uploads']['paths'] = array_values((array) ($payload['upload_paths'] ?? []));

            $verification = $this->verifyPackageDirectory($packageDir, $manifest);
            $manifest['verification'] = $verification;
            $manifest['is_valid'] = (bool) ($verification['is_valid'] ?? false);
            $manifest['status'] = 'ready';
            $this->writeManifest($packageDir, $manifest);

            return $manifest;
        } catch (\Throwable $exception) {
            $manifest['status'] = 'failed';
            $manifest['error'] = $exception->getMessage();
            $this->writeManifest($packageDir, $manifest);
            throw $exception;
        } finally {
            if ($tmpDir !== '' && str_contains($tmpDir, sys_get_temp_dir())) {
                $this->removeDirectory($tmpDir);
            }
            $this->releaseRunLock($lock);
        }
    }

    public function status(): array
    {
        $root = $this->packageRoot();
        $items = $this->allPackages();
        $latestProductionApply = null;

        foreach ($items as $item) {
            foreach ((array) ($item['applied_targets'] ?? []) as $apply) {
                if (($apply['target_profile'] ?? '') === 'production') {
                    $latestProductionApply = ['package_id' => (string) ($item['package_id'] ?? ''), 'applied_at' => (string) ($apply['applied_at'] ?? '')];
                    break 2;
                }
            }
        }

        return [
            'package_root' => $root,
            'total_packages' => count($items),
            'latest' => $items[0] ?? null,
            'latest_production_apply' => $latestProductionApply,
            'running' => $this->readRunLock($root),
            'items' => $items,
        ];
    }

    public function codeStatus(): array
    {
        $root = $this->codePackageRoot();
        $items = $this->allCodePackages();

        return [
            'package_root' => $root,
            'total_packages' => count($items),
            'latest' => $items[0] ?? null,
            'items' => $items,
        ];
    }

    public function verify(?string $packageId = null): array
    {
        $package = $this->packageById($packageId);
        if ($package === null) {
            throw new RuntimeException('Nenhum pacote encontrado para verificar.');
        }

        $verification = $this->verifyPackageDirectory((string) $package['_dir'], $package);
        $package['verification'] = $verification;
        $package['is_valid'] = (bool) ($verification['is_valid'] ?? false);
        $this->writeManifest((string) $package['_dir'], $package);

        return $package;
    }

    public function apply(?string $packageId, string $targetProfile = 'production', bool $force = false): array
    {
        if (!$force) {
            throw new RuntimeException('Publicacao exige confirmacao explicita.');
        }

        $package = $this->packageById($packageId);
        if ($package === null) {
            throw new RuntimeException('Nenhum pacote encontrado para aplicar.');
        }

        $verification = $this->verifyPackageDirectory((string) $package['_dir'], $package);
        if (!(bool) ($verification['is_valid'] ?? false)) {
            throw new RuntimeException('O pacote selecionado nao passou na verificacao.');
        }

        $profile = $this->profile($targetProfile);
        $lock = $this->acquireRunLock($this->packageRoot(), 'apply', $targetProfile, (string) ($profile['label'] ?? $targetProfile));
        $tmpDir = '';

        try {
            $payload = $this->readPayload((string) $package['_dir']);
            $pdo = $this->connectPdo((array) ($profile['database'] ?? []));
            $tmpDir = $this->extractArchive((string) $package['_dir'] . DIRECTORY_SEPARATOR . 'uploads.zip');

            $pdo->beginTransaction();
            try {
                $categories = $this->applyCategories($pdo, (array) ($payload['categoria_post.json'] ?? []));
                $posts = $this->applyPosts($pdo, (array) ($payload['posts.json'] ?? []), (array) ($payload['post_slug_history.json'] ?? []), $categories);
                $links = $this->applyLinks($pdo, (array) ($payload['links.json'] ?? []));
                $configs = $this->applyConfiguracoes($pdo, (array) ($payload['configuracoes.json'] ?? []));
                $this->assertAppliedPostIntegrity($pdo, (array) ($payload['posts.json'] ?? []));
                $pdo->commit();
            } catch (\Throwable $exception) {
                $pdo->rollBack();
                throw $exception;
            }

            $uploads = $this->applyUploads((array) ($profile['uploads'] ?? []), $tmpDir);
            $apply = [
                'target_profile' => $targetProfile,
                'target_profile_label' => (string) ($profile['label'] ?? $targetProfile),
                'applied_at' => date('c'),
                'result' => ['categorias' => $categories['stats'], 'posts' => $posts, 'links' => $links, 'configuracoes' => $configs, 'uploads' => $uploads],
            ];
            $package['applied_targets'] = array_values(array_merge((array) ($package['applied_targets'] ?? []), [$apply]));
            $package['verification'] = $verification;
            $package['is_valid'] = true;
            $this->writeManifest((string) $package['_dir'], $package);

            return ['package_id' => $package['package_id'] ?? null, 'target_profile' => $targetProfile, 'applied_at' => $apply['applied_at'], 'result' => $apply['result']];
        } finally {
            if ($tmpDir !== '' && str_contains($tmpDir, sys_get_temp_dir())) {
                $this->removeDirectory($tmpDir);
            }
            $this->releaseRunLock($lock);
        }
    }

    public function applyCode(?string $packageId, string $targetProfile = 'production', bool $force = false): array
    {
        if (!$force) {
            throw new RuntimeException('Publicacao de codigo exige confirmacao explicita.');
        }

        $package = $this->codePackageById($packageId);
        if ($package === null) {
            throw new RuntimeException('Nenhum pacote de codigo encontrado para aplicar.');
        }

        $profile = $this->profile($targetProfile);
        $profileLabel = (string) ($profile['label'] ?? $targetProfile);
        $lock = $this->acquireRunLock($this->packageRoot(), 'apply_code', $targetProfile, $profileLabel);
        $tmpDir = '';

        try {
            $zipPath = (string) ($package['zip_path'] ?? '');
            if ($zipPath === '' || !is_file($zipPath)) {
                throw new RuntimeException('Arquivo ZIP do pacote de codigo nao encontrado.');
            }

            $tmpDir = $this->extractArchive($zipPath);
            $sourceDir = $tmpDir . DIRECTORY_SEPARATOR . 'files';
            if (!is_dir($sourceDir)) {
                throw new RuntimeException('Pacote de codigo invalido: pasta "files/" nao encontrada.');
            }

            $deployConfig = $this->codeDeployConfig($targetProfile);
            $result = $this->deployCode($deployConfig, $sourceDir);

            return [
                'package_id' => (string) ($package['package_id'] ?? ''),
                'target_profile' => $targetProfile,
                'target_profile_label' => $profileLabel,
                'applied_at' => date('c'),
                'result' => $result,
            ];
        } finally {
            if ($tmpDir !== '' && str_contains($tmpDir, sys_get_temp_dir())) {
                $this->removeDirectory($tmpDir);
            }
            $this->releaseRunLock($lock);
        }
    }
    private function exportPayload(PDO $pdo): array
    {
        $categories = $this->fetchAll($pdo, 'SELECT id, nome, slug, descricao, ativo, ordem, cor FROM categoria_post ORDER BY ordem ASC, nome ASC, id ASC');
        $posts = $this->fetchAll($pdo, 'SELECT id, titulo, slug, resumo, conteudo, categoria, categoria_id, categoria_post_id, imagem_capa, imagem_thumb, autor_id, data_publicacao, data_atualizacao, tempo_leitura, seo_title, seo_description, seo_keywords, tags, status, destaque FROM posts ORDER BY data_publicacao ASC, id ASC');
        $history = $this->fetchAll($pdo, 'SELECT post_id, slug, created_at FROM post_slug_history ORDER BY id ASC');
        $links = $this->fetchAll($pdo, 'SELECT id, titulo, slug, url, tipo, promocao, desconto_percentual, desconto_contexto, codigo_cupom, secao_publica, subgrupo_publico, descricao, cta_curto, texto_botao, selo, imagem, posicao, status, destaque, expira_em FROM links ORDER BY posicao ASC, id ASC');
        $configs = $this->fetchConfiguracoes($pdo);

        $categorySlugById = [];
        foreach ($categories as $category) {
            $id = (int) ($category['id'] ?? 0);
            if ($id > 0) {
                $categorySlugById[$id] = (string) ($category['slug'] ?? '');
            }
        }

        foreach ($posts as &$post) {
            $categoryPostId = (int) ($post['categoria_post_id'] ?? 0);
            $post['categoria_post_slug'] = $categoryPostId > 0 ? (string) ($categorySlugById[$categoryPostId] ?? '') : '';
        }
        unset($post);

        return [
            'files' => [
                'categoria_post.json' => $categories,
                'posts.json' => $posts,
                'post_slug_history.json' => $history,
                'links.json' => $links,
                'configuracoes.json' => $configs,
            ],
            'upload_paths' => $this->collectUploadPaths($posts, $links, $configs),
        ];
    }

    private function fetchConfiguracoes(PDO $pdo): array
    {
        $keys = array_values(array_filter(array_map('strval', (array) ($this->config['public_config_keys'] ?? []))));
        if ($keys === []) {
            return [];
        }

        $stmt = $pdo->prepare('SELECT chave, valor FROM configuracoes WHERE chave IN (' . implode(', ', array_fill(0, count($keys), '?')) . ') ORDER BY chave ASC');
        $stmt->execute($keys);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function collectUploadPaths(array $posts, array $links, array $configs): array
    {
        $paths = [];

        foreach ($posts as $post) {
            foreach (['imagem_capa', 'imagem_thumb'] as $field) {
                $normalized = $this->normalizeUploadReference((string) ($post[$field] ?? ''));
                if ($normalized !== null) {
                    $paths[$normalized] = true;
                }
            }
            foreach ($this->extractUploadReferencesFromHtml((string) ($post['conteudo'] ?? '')) as $path) {
                $paths[$path] = true;
            }
        }

        foreach ($links as $link) {
            $normalized = $this->normalizeUploadReference((string) ($link['imagem'] ?? ''));
            if ($normalized !== null) {
                $paths[$normalized] = true;
            }
        }

        foreach ($configs as $config) {
            $normalized = $this->normalizeUploadReference((string) ($config['valor'] ?? ''));
            if ($normalized !== null) {
                $paths[$normalized] = true;
            }
        }

        $values = array_keys($paths);
        sort($values);
        return $values;
    }

    private function normalizeUploadReference(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $value) === 1) {
            $path = parse_url($value, PHP_URL_PATH);
            if (!is_string($path) || $path === '') {
                return null;
            }
            $value = $path;
        }

        $value = str_replace('\\', '/', $value);
        $value = preg_replace('#/+#', '/', $value) ?? $value;
        $position = stripos($value, '/uploads/');
        if ($position !== false) {
            $value = substr($value, $position + 1);
        }

        $value = ltrim($value, '/');
        if (!str_starts_with($value, 'uploads/')) {
            return null;
        }

        $relative = trim(substr($value, strlen('uploads/')), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        return 'uploads/' . $relative;
    }

    private function extractUploadReferencesFromHtml(string $html): array
    {
        if ($html === '') {
            return [];
        }

        preg_match_all('/(?:src|href)=["\']([^"\']+)["\']/i', $html, $matches);
        $paths = [];
        foreach ((array) ($matches[1] ?? []) as $match) {
            $normalized = $this->normalizeUploadReference((string) $match);
            if ($normalized !== null) {
                $paths[$normalized] = true;
            }
        }

        return array_keys($paths);
    }

    private function materializeUploadsSubset(array $uploadsConfig, array $paths): string
    {
        $tmpRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'en-content-' . bin2hex(random_bytes(6));
        $uploadsRoot = $tmpRoot . DIRECTORY_SEPARATOR . 'uploads';
        if (!mkdir($uploadsRoot, 0777, true) && !is_dir($uploadsRoot)) {
            throw new RuntimeException('Nao foi possivel criar a pasta temporaria de uploads.');
        }

        if ($paths === []) {
            return $tmpRoot;
        }

        $mode = strtolower((string) ($uploadsConfig['mode'] ?? 'local'));
        if ($mode === 'local') {
            $sourceRoot = rtrim((string) ($uploadsConfig['path'] ?? ''), '\\/');
            if ($sourceRoot === '' || !is_dir($sourceRoot)) {
                throw new RuntimeException('Pasta local de uploads nao encontrada: ' . $sourceRoot);
            }

            foreach ($paths as $relativePath) {
                $relative = substr($relativePath, strlen('uploads/'));
                $sourceFile = $sourceRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                if (!is_file($sourceFile)) {
                    continue;
                }
                $destination = $tmpRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
                $destinationDir = dirname($destination);
                if (!is_dir($destinationDir) && !mkdir($destinationDir, 0777, true) && !is_dir($destinationDir)) {
                    throw new RuntimeException('Nao foi possivel criar a pasta temporaria do pacote.');
                }
                if (!copy($sourceFile, $destination)) {
                    throw new RuntimeException('Falha ao copiar o upload para o pacote: ' . $relativePath);
                }
            }

            return $tmpRoot;
        }

        if ($mode !== 'ftp') {
            throw new RuntimeException('Modo de uploads nao suportado para exportacao: ' . $mode);
        }

        foreach (['host', 'port', 'username', 'password', 'root'] as $required) {
            if (trim((string) ($uploadsConfig[$required] ?? '')) === '') {
                throw new RuntimeException('Configuracao FTP incompleta: faltando ' . $required);
            }
        }

        $ftp = @ftp_connect((string) $uploadsConfig['host'], (int) $uploadsConfig['port'], 30);
        if ($ftp === false) {
            throw new RuntimeException('Nao foi possivel conectar ao FTP configurado.');
        }

        try {
            if (!@ftp_login($ftp, (string) $uploadsConfig['username'], (string) $uploadsConfig['password'])) {
                throw new RuntimeException('Falha de login no FTP.');
            }
            ftp_pasv($ftp, (bool) ($uploadsConfig['passive'] ?? true));
            $root = rtrim((string) $uploadsConfig['root'], '/');

            foreach ($paths as $relativePath) {
                $relative = substr($relativePath, strlen('uploads/'));
                $remoteFile = $root . '/' . $relative;
                $localFile = $tmpRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
                $localDir = dirname($localFile);
                if (!is_dir($localDir) && !mkdir($localDir, 0777, true) && !is_dir($localDir)) {
                    throw new RuntimeException('Nao foi possivel criar a pasta temporaria do pacote.');
                }
                @ftp_get($ftp, $localFile, $remoteFile, FTP_BINARY);
            }
        } finally {
            ftp_close($ftp);
        }

        return $tmpRoot;
    }

    private function applyCategories(PDO $pdo, array $categories): array
    {
        $stats = ['created' => 0, 'updated' => 0];
        $map = [];

        foreach ($categories as $category) {
            $slug = trim((string) ($category['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $existing = $this->fetchOne($pdo, 'SELECT id FROM categoria_post WHERE slug = :slug LIMIT 1', ['slug' => $slug]);
            if ($existing !== null) {
                $stmt = $pdo->prepare('UPDATE categoria_post SET nome = :nome, descricao = :descricao, ativo = :ativo, ordem = :ordem, cor = :cor WHERE id = :id');
                $stmt->execute(['id' => (int) $existing['id'], 'nome' => (string) ($category['nome'] ?? ''), 'descricao' => $this->nullableString($category['descricao'] ?? null), 'ativo' => (int) ($category['ativo'] ?? 1), 'ordem' => (int) ($category['ordem'] ?? 0), 'cor' => (string) ($category['cor'] ?? '#00d4ff')]);
                $categoryId = (int) $existing['id'];
                $stats['updated']++;
            } else {
                $stmt = $pdo->prepare('INSERT INTO categoria_post (nome, slug, descricao, ativo, ordem, cor) VALUES (:nome, :slug, :descricao, :ativo, :ordem, :cor)');
                $stmt->execute(['nome' => (string) ($category['nome'] ?? ''), 'slug' => $slug, 'descricao' => $this->nullableString($category['descricao'] ?? null), 'ativo' => (int) ($category['ativo'] ?? 1), 'ordem' => (int) ($category['ordem'] ?? 0), 'cor' => (string) ($category['cor'] ?? '#00d4ff')]);
                $categoryId = (int) $pdo->lastInsertId();
                $stats['created']++;
            }
            $map[$slug] = $categoryId;
        }

        return ['map' => $map, 'stats' => $stats];
    }
    private function applyPosts(PDO $pdo, array $posts, array $historyRows, array $categoryPayload): array
    {
        $categoryMap = (array) ($categoryPayload['map'] ?? []);
        $historyByPost = [];
        foreach ($historyRows as $row) {
            $postId = (int) ($row['post_id'] ?? 0);
            $slug = trim((string) ($row['slug'] ?? ''));
            if ($postId > 0 && $slug !== '') {
                $historyByPost[$postId][] = $slug;
            }
        }

        $stats = ['created' => 0, 'updated' => 0, 'history_added' => 0];

        foreach ($posts as $post) {
            $sourcePostId = (int) ($post['id'] ?? 0);
            $currentSlug = trim((string) ($post['slug'] ?? ''));
            if ($currentSlug === '') {
                continue;
            }

            $knownSlugs = array_values(array_unique(array_filter(array_merge([$currentSlug], $historyByPost[$sourcePostId] ?? []))));
            $existing = $this->findTargetPost($pdo, $currentSlug, $knownSlugs);
            $currentTargetSlug = (string) ($existing['slug'] ?? '');
            $categorySlug = trim((string) ($post['categoria_post_slug'] ?? ''));
            $categoryId = $categorySlug !== '' ? (int) ($categoryMap[$categorySlug] ?? 0) : 0;
            $authorId = $this->resolveAuthorId($pdo, (int) ($post['autor_id'] ?? 1));

            $data = [
                'titulo' => (string) ($post['titulo'] ?? ''),
                'slug' => $currentSlug,
                'resumo' => (string) ($post['resumo'] ?? ''),
                'conteudo' => (string) ($post['conteudo'] ?? ''),
                'categoria' => (string) ($post['categoria'] ?? 'gadgets'),
                'categoria_post_id' => $categoryId > 0 ? $categoryId : null,
                'imagem_capa' => $this->nullableString($post['imagem_capa'] ?? null),
                'imagem_thumb' => $this->nullableString($post['imagem_thumb'] ?? null),
                'autor_id' => $authorId,
                'data_publicacao' => (string) ($post['data_publicacao'] ?? date('Y-m-d H:i:s')),
                'tempo_leitura' => (int) ($post['tempo_leitura'] ?? 5),
                'seo_title' => $this->nullableString($post['seo_title'] ?? null),
                'seo_description' => $this->nullableString($post['seo_description'] ?? null),
                'seo_keywords' => $this->nullableString($post['seo_keywords'] ?? null),
                'tags' => $this->nullableString($post['tags'] ?? null),
                'status' => (string) ($post['status'] ?? 'rascunho'),
                'destaque' => (int) ($post['destaque'] ?? 0),
            ];

            if ($existing !== null) {
                $stmt = $pdo->prepare('UPDATE posts SET titulo = :titulo, slug = :slug, resumo = :resumo, conteudo = :conteudo, categoria = :categoria, categoria_post_id = :categoria_post_id, imagem_capa = :imagem_capa, imagem_thumb = :imagem_thumb, autor_id = :autor_id, data_publicacao = :data_publicacao, tempo_leitura = :tempo_leitura, seo_title = :seo_title, seo_description = :seo_description, seo_keywords = :seo_keywords, tags = :tags, status = :status, destaque = :destaque WHERE id = :id');
                $stmt->execute($data + ['id' => (int) $existing['id']]);
                $targetPostId = (int) $existing['id'];
                $stats['updated']++;
                if ($currentTargetSlug !== '' && $currentTargetSlug !== $currentSlug && $this->storePostSlug($pdo, $targetPostId, $currentTargetSlug)) {
                    $stats['history_added']++;
                }
            } else {
                $stmt = $pdo->prepare('INSERT INTO posts (titulo, slug, resumo, conteudo, categoria, categoria_post_id, imagem_capa, imagem_thumb, autor_id, data_publicacao, tempo_leitura, seo_title, seo_description, seo_keywords, tags, status, destaque, views, curtidas, comentarios_count, likes_count) VALUES (:titulo, :slug, :resumo, :conteudo, :categoria, :categoria_post_id, :imagem_capa, :imagem_thumb, :autor_id, :data_publicacao, :tempo_leitura, :seo_title, :seo_description, :seo_keywords, :tags, :status, :destaque, 0, 0, 0, 0)');
                $stmt->execute($data);
                $targetPostId = (int) $pdo->lastInsertId();
                $stats['created']++;
            }

            foreach ($knownSlugs as $historySlug) {
                if ($historySlug !== $currentSlug && $this->storePostSlug($pdo, $targetPostId, $historySlug)) {
                    $stats['history_added']++;
                }
            }
        }

        return $stats;
    }

    private function findTargetPost(PDO $pdo, string $currentSlug, array $knownSlugs): ?array
    {
        $direct = $this->fetchOne($pdo, 'SELECT id, slug FROM posts WHERE slug = :slug LIMIT 1', ['slug' => $currentSlug]);
        if ($direct !== null) {
            return $direct;
        }

        foreach ($knownSlugs as $slug) {
            $row = $this->fetchOne($pdo, 'SELECT p.id, p.slug FROM post_slug_history h INNER JOIN posts p ON p.id = h.post_id WHERE h.slug = :slug ORDER BY h.id DESC LIMIT 1', ['slug' => $slug]);
            if ($row !== null) {
                return $row;
            }
        }

        return null;
    }

    private function storePostSlug(PDO $pdo, int $postId, string $slug): bool
    {
        $slug = trim($slug);
        if ($postId <= 0 || $slug === '') {
            return false;
        }

        $existing = $this->fetchOne($pdo, 'SELECT post_id FROM post_slug_history WHERE slug = :slug LIMIT 1', ['slug' => $slug]);
        if ($existing !== null) {
            return (int) ($existing['post_id'] ?? 0) === $postId;
        }

        $stmt = $pdo->prepare('INSERT INTO post_slug_history (post_id, slug, created_at) VALUES (:post_id, :slug, NOW())');
        $stmt->execute(['post_id' => $postId, 'slug' => $slug]);
        return true;
    }

    private function resolveAuthorId(PDO $pdo, int $authorId): int
    {
        if ($authorId > 0) {
            $existing = $this->fetchOne($pdo, 'SELECT id FROM usuarios WHERE id = :id LIMIT 1', ['id' => $authorId]);
            if ($existing !== null) {
                return (int) $existing['id'];
            }
        }

        $fallback = $this->fetchOne($pdo, 'SELECT id FROM usuarios ORDER BY id ASC LIMIT 1');
        return (int) ($fallback['id'] ?? 1);
    }

    private function applyLinks(PDO $pdo, array $links): array
    {
        $stats = ['created' => 0, 'updated' => 0];

        foreach ($links as $link) {
            $slug = trim((string) ($link['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $data = [
                'titulo' => (string) ($link['titulo'] ?? ''),
                'slug' => $slug,
                'url' => (string) ($link['url'] ?? ''),
                'tipo' => (string) ($link['tipo'] ?? 'produto'),
                'promocao' => (int) ($link['promocao'] ?? 0),
                'desconto_percentual' => $this->nullableString($link['desconto_percentual'] ?? null),
                'desconto_contexto' => $this->nullableString($link['desconto_contexto'] ?? null),
                'codigo_cupom' => $this->nullableString($link['codigo_cupom'] ?? null),
                'secao_publica' => (string) ($link['secao_publica'] ?? 'produtos'),
                'subgrupo_publico' => $this->nullableString($link['subgrupo_publico'] ?? null),
                'descricao' => $this->nullableString($link['descricao'] ?? null),
                'cta_curto' => $this->nullableString($link['cta_curto'] ?? null),
                'texto_botao' => $this->nullableString($link['texto_botao'] ?? null),
                'selo' => $this->nullableString($link['selo'] ?? null),
                'imagem' => $this->nullableString($link['imagem'] ?? null),
                'posicao' => (int) ($link['posicao'] ?? 0),
                'status' => (string) ($link['status'] ?? 'ativo'),
                'destaque' => (int) ($link['destaque'] ?? 0),
                'expira_em' => $this->nullableString($link['expira_em'] ?? null),
            ];

            $existing = $this->fetchOne($pdo, 'SELECT id FROM links WHERE slug = :slug LIMIT 1', ['slug' => $slug]);
            if ($existing !== null) {
                $stmt = $pdo->prepare('UPDATE links SET titulo = :titulo, slug = :slug, url = :url, tipo = :tipo, promocao = :promocao, desconto_percentual = :desconto_percentual, desconto_contexto = :desconto_contexto, codigo_cupom = :codigo_cupom, secao_publica = :secao_publica, subgrupo_publico = :subgrupo_publico, descricao = :descricao, cta_curto = :cta_curto, texto_botao = :texto_botao, selo = :selo, imagem = :imagem, posicao = :posicao, status = :status, destaque = :destaque, expira_em = :expira_em WHERE id = :id');
                $stmt->execute($data + ['id' => (int) $existing['id']]);
                $stats['updated']++;
            } else {
                $stmt = $pdo->prepare('INSERT INTO links (titulo, slug, url, tipo, promocao, desconto_percentual, desconto_contexto, codigo_cupom, secao_publica, subgrupo_publico, descricao, cta_curto, texto_botao, selo, imagem, posicao, status, destaque, expira_em) VALUES (:titulo, :slug, :url, :tipo, :promocao, :desconto_percentual, :desconto_contexto, :codigo_cupom, :secao_publica, :subgrupo_publico, :descricao, :cta_curto, :texto_botao, :selo, :imagem, :posicao, :status, :destaque, :expira_em)');
                $stmt->execute($data);
                $stats['created']++;
            }
        }

        return $stats;
    }

    private function applyConfiguracoes(PDO $pdo, array $configs): array
    {
        $stats = ['saved' => 0];
        if ($configs === []) {
            return $stats;
        }

        $stmt = $pdo->prepare('INSERT INTO configuracoes (chave, valor, updated_at) VALUES (:chave, :valor, NOW()) ON DUPLICATE KEY UPDATE valor = VALUES(valor), updated_at = NOW()');
        foreach ($configs as $config) {
            $key = trim((string) ($config['chave'] ?? ''));
            if ($key === '') {
                continue;
            }
            $stmt->execute(['chave' => $key, 'valor' => (string) ($config['valor'] ?? '')]);
            $stats['saved']++;
        }

        return $stats;
    }

    private function assertAppliedPostIntegrity(PDO $pdo, array $posts): void
    {
        $slugs = array_values(array_unique(array_filter(array_map(
            static fn (array $post): string => trim((string) ($post['slug'] ?? '')),
            $posts
        ))));

        if ($slugs === []) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($slugs), '?'));
        $stmt = $pdo->prepare('SELECT slug, COUNT(*) AS total FROM posts WHERE slug IN (' . $placeholders . ') GROUP BY slug');
        $stmt->execute($slugs);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $counts = [];
        foreach ($rows as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $counts[$slug] = (int) ($row['total'] ?? 0);
        }

        $missing = [];
        $duplicated = [];
        foreach ($slugs as $slug) {
            $total = (int) ($counts[$slug] ?? 0);
            if ($total <= 0) {
                $missing[] = $slug;
                continue;
            }

            if ($total > 1) {
                $duplicated[] = $slug;
            }
        }

        if ($missing !== [] || $duplicated !== []) {
            $parts = [];
            if ($missing !== []) {
                $parts[] = 'slugs ausentes: ' . implode(', ', array_slice($missing, 0, 10));
            }
            if ($duplicated !== []) {
                $parts[] = 'slugs duplicados: ' . implode(', ', array_slice($duplicated, 0, 10));
            }

            throw new RuntimeException('Falha na validacao de integridade apos aplicacao (' . implode(' | ', $parts) . ').');
        }
    }

    private function applyUploads(array $uploadsConfig, string $tmpDir): array
    {
        $files = $this->listFiles($tmpDir . DIRECTORY_SEPARATOR . 'uploads');
        $mode = strtolower((string) ($uploadsConfig['mode'] ?? 'local'));

        if ($mode === 'local') {
            $root = rtrim((string) ($uploadsConfig['path'] ?? ''), '\\/');
            if ($root === '') {
                throw new RuntimeException('Destino local de uploads nao configurado.');
            }

            foreach ($files as $file) {
                $relative = substr(str_replace('\\', '/', $file), strlen(str_replace('\\', '/', $tmpDir)) + 1);
                $relative = preg_replace('#^uploads/#', '', $relative) ?? $relative;
                $destination = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                $destinationDir = dirname($destination);
                if (!is_dir($destinationDir) && !mkdir($destinationDir, 0777, true) && !is_dir($destinationDir)) {
                    throw new RuntimeException('Nao foi possivel criar a pasta local de uploads.');
                }
                if (!copy($file, $destination)) {
                    throw new RuntimeException('Falha ao copiar o upload local: ' . $relative);
                }
            }

            return ['uploaded' => count($files), 'mode' => 'local'];
        }

        if ($mode !== 'ftp') {
            throw new RuntimeException('Modo de uploads nao suportado para aplicacao: ' . $mode);
        }

        foreach (['host', 'port', 'username', 'password', 'root'] as $required) {
            if (trim((string) ($uploadsConfig[$required] ?? '')) === '') {
                throw new RuntimeException('Configuracao FTP incompleta: faltando ' . $required);
            }
        }

        $ftp = @ftp_connect((string) $uploadsConfig['host'], (int) $uploadsConfig['port'], 30);
        if ($ftp === false) {
            throw new RuntimeException('Nao foi possivel conectar ao FTP configurado.');
        }

        try {
            if (!@ftp_login($ftp, (string) $uploadsConfig['username'], (string) $uploadsConfig['password'])) {
                throw new RuntimeException('Falha de login no FTP.');
            }
            ftp_pasv($ftp, (bool) ($uploadsConfig['passive'] ?? true));
            $root = rtrim((string) $uploadsConfig['root'], '/');

            foreach ($files as $file) {
                $relative = substr(str_replace('\\', '/', $file), strlen(str_replace('\\', '/', $tmpDir)) + 1);
                $relative = preg_replace('#^uploads/#', '', $relative) ?? $relative;
                $remotePath = $root . '/' . $relative;
                $this->ensureRemoteDirectory($ftp, dirname($remotePath));
                if (!@ftp_put($ftp, $remotePath, $file, FTP_BINARY)) {
                    throw new RuntimeException('Falha ao enviar o upload para a producao: ' . $relative);
                }
            }
        } finally {
            ftp_close($ftp);
        }

        return ['uploaded' => count($files), 'mode' => 'ftp'];
    }
    private function verifyPackageDirectory(string $packageDir, array $manifest): array
    {
        $dataResults = [];
        $allValid = true;
        $textIssues = [];

        foreach ((array) ($manifest['data_files'] ?? []) as $name => $fileInfo) {
            $dataResults[$name] = $this->verifyEntry((array) $fileInfo, $packageDir, true);
            $allValid = $allValid && (bool) ($dataResults[$name]['valid'] ?? false);

            if ((bool) ($dataResults[$name]['valid'] ?? false)) {
                $path = $packageDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) ($fileInfo['name'] ?? ''));
                $decoded = json_decode((string) file_get_contents($path), true);
                if (is_array($decoded)) {
                    $issues = $this->findPayloadTextIssues($decoded, (string) $name);
                    if ($issues !== []) {
                        $dataResults[$name]['valid'] = false;
                        $dataResults[$name]['message'] = 'Texto suspeito detectado no pacote.';
                        $dataResults[$name]['text_issues'] = array_slice($issues, 0, 20);
                        $allValid = false;
                        $textIssues = array_merge($textIssues, array_slice($issues, 0, 20));
                    }
                }
            }
        }

        $uploadsResult = $this->verifyEntry((array) ($manifest['uploads'] ?? []), $packageDir, false);
        $allValid = $allValid && (bool) ($uploadsResult['valid'] ?? false);

        return ['is_valid' => $allValid, 'data_files' => $dataResults, 'uploads' => $uploadsResult, 'text_issues' => $textIssues];
    }

    private function verifyEntry(array $entry, string $packageDir, bool $expectJson): array
    {
        $name = (string) ($entry['name'] ?? '');
        if ($name === '') {
            return ['valid' => false, 'message' => 'Sem nome de arquivo.'];
        }

        $path = $packageDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
        if (!is_file($path)) {
            return ['valid' => false, 'message' => 'Arquivo nao encontrado: ' . $name];
        }
        if ((int) ($entry['size_bytes'] ?? 0) > 0 && filesize($path) !== (int) $entry['size_bytes']) {
            return ['valid' => false, 'message' => 'Tamanho divergente em ' . $name];
        }
        if (($entry['sha1'] ?? null) !== null && (string) $entry['sha1'] !== '' && sha1_file($path) !== (string) $entry['sha1']) {
            return ['valid' => false, 'message' => 'Hash divergente em ' . $name];
        }
        if ($expectJson && !is_array(json_decode((string) file_get_contents($path), true))) {
            return ['valid' => false, 'message' => 'JSON invalido em ' . $name];
        }

        return ['valid' => true, 'message' => 'OK'];
    }

    private function findPayloadTextIssues(array $payload, string $sourceName): array
    {
        $issues = [];
        $this->inspectPayloadNode($payload, $sourceName, '$', $issues);
        return $issues;
    }

    private function inspectPayloadNode(mixed $value, string $sourceName, string $path, array &$issues): void
    {
        if (count($issues) >= 100) {
            return;
        }

        if (is_array($value)) {
            foreach ($value as $key => $child) {
                $nextPath = is_int($key)
                    ? $path . '[' . $key . ']'
                    : $path . "['" . (string) $key . "']";
                $this->inspectPayloadNode($child, $sourceName, $nextPath, $issues);
            }
            return;
        }

        if (!is_string($value)) {
            return;
        }

        $text = trim($value);
        if ($text === '') {
            return;
        }

        $reason = $this->detectBrokenTextReason($text);
        if ($reason === null) {
            return;
        }

        $snippet = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $snippet = mb_substr($snippet, 0, 140, 'UTF-8');
        $issues[] = $sourceName . ' ' . $path . ' => ' . $reason . ' :: ' . $snippet;
    }

    private function detectBrokenTextReason(string $text): ?string
    {
        if (preg_match('/\x{00C3}./u', $text) === 1) {
            return 'mojibake-utf8';
        }

        if (preg_match('/\x{00C2}./u', $text) === 1) {
            return 'mojibake-cp1252';
        }

        if (preg_match('/\x{FFFD}/u', $text) === 1) {
            return 'replacement-char';
        }

        $withoutUrls = preg_replace('~https?://\S+|/[^\s\'"]*\?[^\s\'"]*~u', '', $text) ?? $text;
        if (preg_match('/\p{L}\?\p{L}/u', $withoutUrls) === 1) {
            return 'question-inside-word';
        }

        return null;
    }

    private function readPayload(string $packageDir): array
    {
        $result = [];
        foreach (['categoria_post.json', 'posts.json', 'post_slug_history.json', 'links.json', 'configuracoes.json'] as $fileName) {
            $path = $packageDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . $fileName;
            if (!is_file($path)) {
                throw new RuntimeException('Arquivo de dados ausente no pacote: ' . $fileName);
            }
            $decoded = json_decode((string) file_get_contents($path), true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Arquivo JSON invalido no pacote: ' . $fileName);
            }
            $result[$fileName] = $decoded;
        }
        return $result;
    }

    private function connectPdo(array $databaseConfig): PDO
    {
        foreach (['host', 'port', 'database', 'username'] as $required) {
            if (trim((string) ($databaseConfig[$required] ?? '')) === '') {
                throw new RuntimeException('Configuracao de banco incompleta: faltando ' . $required);
            }
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', (string) $databaseConfig['host'], (string) $databaseConfig['port'], (string) $databaseConfig['database']);
        return new PDO($dsn, (string) $databaseConfig['username'], (string) ($databaseConfig['password'] ?? ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    }

    private function packageRoot(): string
    {
        $root = (string) ($this->config['package_root'] ?? '');
        if ($root === '') {
            throw new RuntimeException('CONTENT_SYNC_ROOT nao configurado.');
        }
        if (!is_dir($root) && !mkdir($root, 0777, true) && !is_dir($root)) {
            throw new RuntimeException('Nao foi possivel criar a pasta raiz dos pacotes: ' . $root);
        }
        return $root;
    }

    private function codePackageRoot(): string
    {
        $root = (string) ($this->config['code_package_root'] ?? '');
        if ($root === '') {
            throw new RuntimeException('CONTENT_SYNC_CODE_ROOT nao configurado.');
        }
        if (!is_dir($root) && !mkdir($root, 0777, true) && !is_dir($root)) {
            throw new RuntimeException('Nao foi possivel criar a pasta raiz dos pacotes de codigo: ' . $root);
        }
        return $root;
    }

    private function profile(string $profileName): array
    {
        $profiles = (array) ($this->config['profiles'] ?? []);
        if (!isset($profiles[$profileName]) || !is_array($profiles[$profileName])) {
            throw new RuntimeException('Perfil de conteudo nao encontrado: ' . $profileName);
        }
        return $profiles[$profileName];
    }

    private function codeDeployConfig(string $profileName): array
    {
        $profile = $this->profile($profileName);
        $config = (array) ($profile['code_deploy'] ?? []);
        if ($config === []) {
            $uploads = (array) ($profile['uploads'] ?? []);
            $config = [
                'mode' => (string) ($uploads['mode'] ?? 'ftp'),
                'host' => (string) ($uploads['host'] ?? ''),
                'port' => (int) ($uploads['port'] ?? 21),
                'username' => (string) ($uploads['username'] ?? ''),
                'password' => (string) ($uploads['password'] ?? ''),
                'root' => (string) preg_replace('~/uploads/?$~i', '', (string) ($uploads['root'] ?? '')),
                'passive' => (bool) ($uploads['passive'] ?? true),
            ];
        }

        $mode = strtolower(trim((string) ($config['mode'] ?? 'ftp')));
        if ($mode === 'local') {
            $root = trim((string) ($config['root'] ?? ''));
            if ($root === '') {
                throw new RuntimeException('Configuracao de deploy local incompleta: root.');
            }
            return ['mode' => 'local', 'root' => $root];
        }

        foreach (['host', 'port', 'username', 'password', 'root'] as $required) {
            if (trim((string) ($config[$required] ?? '')) === '') {
                throw new RuntimeException('Configuracao de deploy de codigo incompleta: faltando ' . $required);
            }
        }

        return [
            'mode' => 'ftp',
            'host' => (string) $config['host'],
            'port' => (int) $config['port'],
            'username' => (string) $config['username'],
            'password' => (string) $config['password'],
            'root' => rtrim((string) $config['root'], '/'),
            'passive' => (bool) ($config['passive'] ?? true),
        ];
    }

    private function writeManifest(string $packageDir, array $manifest): void
    {
        $this->writeJson($packageDir . DIRECTORY_SEPARATOR . 'manifest.json', $manifest);
    }

    private function writeJson(string $path, array $payload): void
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            throw new RuntimeException('Falha ao serializar JSON.');
        }
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException('Nao foi possivel criar a pasta do arquivo: ' . $path);
        }
        file_put_contents($path, $json . PHP_EOL);
    }

    private function fileDetails(string $path, string $name): array
    {
        return ['name' => $name, 'path' => $path, 'size_bytes' => is_file($path) ? (int) filesize($path) : 0, 'sha1' => is_file($path) ? sha1_file($path) : null];
    }

        private function compressDirectory(string $sourceDirectory, string $zipPath): void
    {
        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $files = $this->listFiles($sourceDirectory);
                $rootLength = strlen(rtrim($sourceDirectory, '\\/')) + 1;
                foreach ($files as $file) {
                    $relativePath = substr($file, $rootLength);
                    $zip->addFile($file, str_replace('\\', '/', $relativePath));
                }
                if ($zip->close()) {
                    return;
                }
            }
        }

        $sevenZip = $this->sevenZipBinary();
        $command = sprintf('%s a -tzip -mx=5 %s .', escapeshellarg($sevenZip), escapeshellarg($zipPath));
        $cwd = getcwd();
        chdir($sourceDirectory);
        exec($command . ' 2>&1', $output, $exitCode);
        if ($cwd !== false) {
            chdir($cwd);
        }

        if ($exitCode !== 0 || !is_file($zipPath)) {
            throw new RuntimeException('Nao foi possivel criar o zip do pacote. ' . trim(implode(PHP_EOL, $output)));
        }
    }

        private function extractArchive(string $zipPath): string
    {
        if (!is_file($zipPath)) {
            throw new RuntimeException('Arquivo zip nao encontrado: ' . $zipPath);
        }
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'en-content-restore-' . bin2hex(random_bytes(6));
        if (!mkdir($tmpDir, 0777, true) && !is_dir($tmpDir)) {
            throw new RuntimeException('Nao foi possivel criar a pasta temporaria do pacote.');
        }

        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) === true) {
                if ($zip->extractTo($tmpDir)) {
                    $zip->close();
                    return $tmpDir;
                }
                $zip->close();
            }
        }

        $sevenZip = $this->sevenZipBinary();
        $command = sprintf('%s x -y %s -o%s', escapeshellarg($sevenZip), escapeshellarg($zipPath), escapeshellarg($tmpDir));
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Nao foi possivel extrair o zip do pacote. ' . trim(implode(PHP_EOL, $output)));
        }

        return $tmpDir;
    }

    private function sevenZipBinary(): string
    {
        $configured = (string) ($this->config['seven_zip_binary'] ?? '');
        $candidates = array_filter([
            $configured,
            'C:\\Program Files\\7-Zip\\7z.exe',
            'C:\\Program Files (x86)\\7-Zip\\7z.exe',
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('7-Zip nao encontrado para compactar ou extrair o pacote.');
    }
    private function listFiles(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }
        $result = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $result[] = $item->getPathname();
            }
        }
        sort($result);
        return $result;
    }

    private function deployCode(array $deployConfig, string $sourceDir): array
    {
        $mode = (string) ($deployConfig['mode'] ?? 'ftp');
        if ($mode === 'local') {
            return $this->deployCodeLocal((string) ($deployConfig['root'] ?? ''), $sourceDir);
        }

        if ($mode !== 'ftp') {
            throw new RuntimeException('Modo de deploy de codigo nao suportado: ' . $mode);
        }

        return $this->deployCodeFtp($deployConfig, $sourceDir);
    }

    private function deployCodeLocal(string $targetRoot, string $sourceDir): array
    {
        $targetRoot = rtrim($targetRoot, '\\/');
        if ($targetRoot === '' || !is_dir($targetRoot)) {
            throw new RuntimeException('Raiz local de deploy de codigo nao encontrada: ' . $targetRoot);
        }

        $files = $this->listFiles($sourceDir);
        $copied = 0;
        foreach ($files as $file) {
            $relative = substr(str_replace('\\', '/', $file), strlen(str_replace('\\', '/', $sourceDir)) + 1);
            $relative = ltrim($relative, '/');
            if ($relative === '' || str_contains($relative, '..')) {
                continue;
            }

            $destination = $targetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $destinationDir = dirname($destination);
            if (!is_dir($destinationDir) && !mkdir($destinationDir, 0777, true) && !is_dir($destinationDir)) {
                throw new RuntimeException('Nao foi possivel criar pasta de destino para deploy local: ' . $relative);
            }
            if (!copy($file, $destination)) {
                throw new RuntimeException('Falha ao copiar arquivo do pacote de codigo: ' . $relative);
            }
            $copied++;
        }

        return ['mode' => 'local', 'files_applied' => $copied, 'target_root' => $targetRoot];
    }

    private function deployCodeFtp(array $deployConfig, string $sourceDir): array
    {
        $ftp = @ftp_connect((string) $deployConfig['host'], (int) $deployConfig['port'], 30);
        if ($ftp === false) {
            throw new RuntimeException('Nao foi possivel conectar ao FTP da producao para deploy de codigo.');
        }

        $uploaded = 0;
        try {
            if (!@ftp_login($ftp, (string) $deployConfig['username'], (string) $deployConfig['password'])) {
                throw new RuntimeException('Falha de login no FTP da producao para deploy de codigo.');
            }
            ftp_pasv($ftp, (bool) ($deployConfig['passive'] ?? true));
            $root = rtrim((string) $deployConfig['root'], '/');

            $files = $this->listFiles($sourceDir);
            foreach ($files as $file) {
                $relative = substr(str_replace('\\', '/', $file), strlen(str_replace('\\', '/', $sourceDir)) + 1);
                $relative = ltrim($relative, '/');
                if ($relative === '' || str_contains($relative, '..')) {
                    continue;
                }

                $remotePath = $root . '/' . $relative;
                $this->ensureRemoteDirectory($ftp, dirname($remotePath));
                if (!@ftp_put($ftp, $remotePath, $file, FTP_BINARY)) {
                    throw new RuntimeException('Falha ao enviar arquivo do pacote de codigo: ' . $relative);
                }
                $uploaded++;
            }
        } finally {
            ftp_close($ftp);
        }

        return ['mode' => 'ftp', 'files_applied' => $uploaded, 'target_root' => (string) ($deployConfig['root'] ?? '')];
    }

    private function ensureRemoteDirectory($ftp, string $remoteDirectory): void
    {
        $parts = array_values(array_filter(explode('/', trim(str_replace('\\', '/', $remoteDirectory), '/')), static fn(string $part): bool => $part !== ''));
        $current = '';
        foreach ($parts as $part) {
            $current .= '/' . $part;
            @ftp_mkdir($ftp, $current);
        }
    }

    private function fetchAll(PDO $pdo, string $sql, array $params = []): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function fetchOne(PDO $pdo, string $sql, array $params = []): ?array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function lockPath(string $root): string
    {
        return $root . DIRECTORY_SEPARATOR . '.content-sync-running.lock.json';
    }

    private function acquireRunLock(string $root, string $operation, string $profileName, string $profileLabel): array
    {
        $lockPath = $this->lockPath($root);
        $handle = fopen($lockPath, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Nao foi possivel criar o lock da rotina de conteudo.');
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            $raw = stream_get_contents($handle);
            $running = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            fclose($handle);
            $message = 'Ja existe uma rotina de conteudo em execucao.';
            if (is_array($running) && !empty($running['started_at'])) {
                $message .= ' Inicio: ' . (string) $running['started_at'];
            }
            throw new RuntimeException($message);
        }
        $payload = ['operation' => $operation, 'profile' => $profileName, 'profile_label' => $profileLabel, 'started_at' => date('c'), 'pid' => function_exists('getmypid') ? getmypid() : null];
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($handle);
        return ['path' => $lockPath, 'handle' => $handle];
    }

    private function releaseRunLock(array $lock): void
    {
        $handle = $lock['handle'] ?? null;
        $path = (string) ($lock['path'] ?? '');
        if (is_resource($handle)) {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    private function readRunLock(string $root): ?array
    {
        $lockPath = $this->lockPath($root);
        if (!is_file($lockPath)) {
            return null;
        }
        $payload = json_decode((string) file_get_contents($lockPath), true);
        if (!is_array($payload)) {
            return ['operation' => 'unknown', 'profile' => 'unknown', 'profile_label' => 'Rotina em execucao', 'started_at' => null];
        }
        return ['operation' => (string) ($payload['operation'] ?? 'unknown'), 'profile' => (string) ($payload['profile'] ?? 'unknown'), 'profile_label' => (string) ($payload['profile_label'] ?? 'Rotina em execucao'), 'started_at' => (string) ($payload['started_at'] ?? '')];
    }

    private function packageById(?string $packageId): ?array
    {
        $items = $this->allPackages();
        if ($packageId === null || trim($packageId) === '' || strtolower(trim($packageId)) === 'latest') {
            return $items[0] ?? null;
        }
        foreach ($items as $item) {
            if (($item['package_id'] ?? null) === $packageId) {
                return $item;
            }
        }
        return null;
    }

    private function codePackageById(?string $packageId): ?array
    {
        $items = $this->allCodePackages();
        if ($packageId === null || trim($packageId) === '' || strtolower(trim($packageId)) === 'latest') {
            return $items[0] ?? null;
        }
        foreach ($items as $item) {
            if (($item['package_id'] ?? null) === $packageId) {
                return $item;
            }
        }
        return null;
    }

    private function allPackages(): array
    {
        $root = $this->packageRoot();
        $dirs = array_filter(glob($root . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [], 'is_dir');
        rsort($dirs);
        $items = [];
        foreach ($dirs as $dir) {
            $manifestPath = $dir . DIRECTORY_SEPARATOR . 'manifest.json';
            if (!is_file($manifestPath)) {
                continue;
            }
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            if (!is_array($manifest)) {
                continue;
            }
            $manifest['_dir'] = $dir;
            $verification = $this->verifyPackageDirectory($dir, $manifest);
            $manifest['verification'] = $verification;
            $manifest['is_valid'] = (bool) ($verification['is_valid'] ?? false);
            $items[] = $manifest;
        }
        return $items;
    }

    private function allCodePackages(): array
    {
        $root = $this->codePackageRoot();
        $zipPaths = glob($root . DIRECTORY_SEPARATOR . 'code_*.zip') ?: [];
        usort($zipPaths, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));

        $items = [];
        foreach ($zipPaths as $zipPath) {
            $basename = basename($zipPath, '.zip');
            $packageDir = $root . DIRECTORY_SEPARATOR . $basename;
            $manifestPath = $packageDir . DIRECTORY_SEPARATOR . 'manifest.json';
            $manifest = $this->readJsonFile($manifestPath) ?? $this->readCodeManifestFromZip($zipPath) ?? [];
            $filesCount = (int) ($manifest['files_count'] ?? 0);
            if ($filesCount <= 0) {
                $filesCount = $this->countCodeFilesInZip($zipPath);
            }
            $commit = trim((string) ($manifest['commit'] ?? ''));
            if ($commit === '' && preg_match('/_([0-9a-f]{7,40})$/i', $basename, $matches) === 1) {
                $commit = (string) ($matches[1] ?? '');
            }

            $items[] = [
                'package_id' => (string) ($manifest['package_id'] ?? $basename),
                'commit' => $commit,
                'created_at' => (string) ($manifest['created_at'] ?? date('c', (int) filemtime($zipPath))),
                'files_count' => $filesCount,
                'notes' => (string) ($manifest['notes'] ?? ''),
                'zip_path' => $zipPath,
                'manifest_path' => is_file($manifestPath) ? $manifestPath : '',
                '_dir' => $packageDir,
            ];
        }

        return $items;
    }

    private function readJsonFile(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $raw = (string) file_get_contents($path);
        if ($raw === '') {
            return null;
        }
        $raw = str_replace("\xEF\xBB\xBF", '', $raw);
        $raw = str_replace("\u{FEFF}", '', $raw);

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function readCodeManifestFromZip(string $zipPath): ?array
    {
        if (!is_file($zipPath) || !class_exists(ZipArchive::class)) {
            return null;
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return null;
        }

        try {
            $index = $zip->locateName('manifest.json', ZipArchive::FL_NOCASE);
            if ($index === false) {
                return null;
            }

            $content = $zip->getFromIndex($index);
            if (!is_string($content) || trim($content) === '') {
                return null;
            }
            $content = str_replace("\xEF\xBB\xBF", '', $content);
            $content = str_replace("\u{FEFF}", '', $content);

            $decoded = json_decode($content, true);
            return is_array($decoded) ? $decoded : null;
        } finally {
            $zip->close();
        }
    }

    private function countCodeFilesInZip(string $zipPath): int
    {
        if (!is_file($zipPath) || !class_exists(ZipArchive::class)) {
            return 0;
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return 0;
        }

        try {
            $count = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
                if ($name === '' || str_ends_with($name, '/')) {
                    continue;
                }
                if (!str_starts_with($name, 'files/')) {
                    continue;
                }
                $count++;
            }
            return $count;
        } finally {
            $zip->close();
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($directory);
    }
}
