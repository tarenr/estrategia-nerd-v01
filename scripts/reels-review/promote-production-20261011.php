<?php
declare(strict_types=1);

// Production promotion: stage is the validated editorial source for the 4 new posts.
// Production is preserved as the operational reference for the 33 existing posts.
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Repositories\PostRepository;
use App\Support\TargetEnvironmentDatabase;
use App\Support\TargetEnvironmentUploads;

$action = $argv[1] ?? '--inspect';
if (!in_array($action, ['--inspect', '--prepare', '--apply', '--verify'], true)) {
    throw new RuntimeException('Ação inválida. Use --inspect, --prepare, --apply ou --verify.');
}

$production = TargetEnvironmentDatabase::pdo('production');
$stage = TargetEnvironmentDatabase::pdo('stage');
$local = $GLOBALS['pdo'];

$dir = base_path('storage/production-content/promotion-20261011');
$packagePath = $dir . '/package.json';

$promotedSlugs = [
    'muito-alem-dos-jumpscares-3-jogos-que-transformam-a-atmosfera-em-verdadeiro-terror',
    'como-o-som-cria-medo-nos-jogos-a-ciencia-por-tras-da-atmosfera-de-terror',
    'jogos-para-quem-so-tem-30-minutos-por-dia',
    'como-voltar-a-um-jogo-depois-de-meses-sem-jogar',
];

$fields = [
    'titulo', 'slug', 'resumo', 'conteudo', 'categoria', 'categoria_post_id',
    'imagem_capa', 'imagem_thumb', 'autor_id', 'data_publicacao', 'tempo_leitura',
    'seo_title', 'seo_description', 'seo_keywords', 'tags', 'status', 'destaque',
    'tipo_post', 'proximo_post_id'
];

function promotionRows(PDO $pdo): array {
    return $pdo->query('SELECT * FROM posts ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
}

function promotionFreshDatabase(string $environment): PDO {
    $database = TargetEnvironmentDatabase::profile($environment)['database'];
    return new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $database['host'], $database['port'], $database['database']),
        $database['username'],
        $database['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function promotionPath(string $reference): ?string {
    if (preg_match('~(?:^|/)(uploads/posts/[a-z0-9-]+/[a-zA-Z0-9_./-]+\.(?:webp|png|jpg|jpeg|gif|mp4|svg))(?:\?.*)?$~', $reference, $matches) !== 1) {
        return null;
    }
    if (str_contains($matches[1], '..')) {
        throw new RuntimeException('Caminho inseguro.');
    }
    return $matches[1];
}

function promotionReferences(array $post): array {
    preg_match_all('~(?:src|data-src|poster)=["\x27]([^"\x27]+)["\x27]~i', (string) $post['conteudo'], $matches);
    return array_values(array_unique(array_filter([$post['imagem_capa'], $post['imagem_thumb'], ...$matches[1]])));
}

function promotionJson(string $path, array $value): void {
    $bytes = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $handle = fopen($path, 'x');
    if ($handle === false) {
        throw new RuntimeException('Arquivo de evidência já existe: ' . basename($path));
    }
    try {
        if (fwrite($handle, $bytes) !== strlen($bytes) || !fflush($handle)) {
            throw new RuntimeException('Evidência incompleta.');
        }
    } finally {
        fclose($handle);
    }
}

function promotionFtp(string $environment): array {
    $profile = TargetEnvironmentDatabase::profile($environment)['uploads'];
    $root = '/' . trim((string) $profile['root'], '/');
    if (($profile['mode'] ?? '') !== 'ftp' || str_contains($root, '..') || !str_ends_with($root, '/uploads')
        || ($environment === 'stage' && !str_ends_with($root, '/stage/uploads'))
        || ($environment === 'production' && str_contains($root, '/stage/'))) {
        throw new RuntimeException('Destino FTP inválido para ' . $environment);
    }
    $ftp = ftp_connect($profile['host'], (int) $profile['port'], 30);
    if ($ftp === false || !ftp_login($ftp, $profile['username'], $profile['password'])) {
        throw new RuntimeException('FTP indisponível.');
    }
    ftp_pasv($ftp, (bool) $profile['passive']);
    return [$ftp, $root];
}

function promotionBytes(FTP\Connection $ftp, string $root, string $path): ?string {
    if (promotionPath($path) !== $path) {
        throw new RuntimeException('Asset fora de uploads/posts: ' . $path);
    }
    $remote = $root . '/' . substr($path, 8);
    if (@ftp_size($ftp, $remote) < 0) {
        return null;
    }
    $stream = fopen('php://temp', 'w+');
    if ($stream === false || !ftp_fget($ftp, $stream, $remote, FTP_BINARY)) {
        throw new RuntimeException('Não foi possível ler asset remoto: ' . $remote);
    }
    rewind($stream);
    $bytes = stream_get_contents($stream);
    fclose($stream);
    if ($bytes === false || $bytes === '') {
        throw new RuntimeException('Asset remoto vazio: ' . $remote);
    }
    return $bytes;
}

function promotionSame(array $row, array $desired): bool {
    foreach ($desired as $field => $value) {
        if (($row[$field] ?? null) != $value) {
            return false;
        }
    }
    return true;
}

$prodRows = promotionRows($production);
$stageRows = promotionRows($stage);

$prodBySlug = array_column($prodRows, null, 'slug');
$stageBySlug = array_column($stageRows, null, 'slug');

if ($action === '--inspect') {
    $existingSlugsInProd = array_intersect(array_keys($prodBySlug), $promotedSlugs);
    $missingInStage = array_diff($promotedSlugs, array_keys($stageBySlug));
    $hasTipoPost = in_array('tipo_post', array_column($production->query('SHOW COLUMNS FROM posts')->fetchAll(PDO::FETCH_ASSOC), 'Field'), true);

    echo json_encode([
        'production_posts_count' => count($prodRows),
        'stage_posts_count' => count($stageRows),
        'promoted_slugs_count' => count($promotedSlugs),
        'existing_in_prod' => array_values($existingSlugsInProd),
        'missing_in_stage' => array_values($missingInStage),
        'production_has_tipo_post' => $hasTipoPost,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
}

if ($action === '--prepare' && count($prodRows) !== 33) {
    throw new RuntimeException('Produção esperada com exatamente 33 artigos para preparação; encontrados ' . count($prodRows));
}
if ($action === '--verify' && count($prodRows) !== 37) {
    throw new RuntimeException('Produção esperada com exatamente 37 artigos para verificação; encontrados ' . count($prodRows));
}
if (count($stageRows) !== 37) {
    throw new RuntimeException('Stage esperado com exatamente 37 artigos; encontrados ' . count($stageRows));
}

// Fingerprints dos 4 artigos a partir do stage validado
$sourceFingerprint = [];
foreach ($promotedSlugs as $slug) {
    if (!isset($stageBySlug[$slug])) {
        throw new RuntimeException('Artigo ausente em stage: ' . $slug);
    }
    $sourceFingerprint[$slug] = hash('sha256', json_encode(array_intersect_key($stageBySlug[$slug], array_flip($fields)), JSON_THROW_ON_ERROR));
}

[$ftp, $ftpRoot] = promotionFtp('production');

try {
    if ($action === '--prepare') {
        if (is_file($packagePath)) {
            throw new RuntimeException('Pacote de promoção já preparado; preservar backup imutável.');
        }
        if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
            throw new RuntimeException('Pasta de backup indisponível: ' . $dir);
        }

        $before = [
            'posts' => $prodRows,
            'categories' => $production->query('SELECT * FROM categoria_post ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
            'schema' => $production->query('SHOW CREATE TABLE posts')->fetch(PDO::FETCH_NUM)[1],
        ];

        // Mapeamentos de categorias e autores de produção
        $prodCategories = array_column($production->query('SELECT id, slug FROM categoria_post')->fetchAll(PDO::FETCH_ASSOC), 'id', 'slug');
        $prodAuthors = array_column($production->query('SELECT id, nome FROM autores')->fetchAll(PDO::FETCH_ASSOC), 'id', 'nome');

        $stageCategories = array_column($stage->query('SELECT id, slug FROM categoria_post')->fetchAll(PDO::FETCH_ASSOC), 'slug', 'id');
        $stageAuthors = array_column($stage->query('SELECT id, nome FROM autores')->fetchAll(PDO::FETCH_ASSOC), 'nome', 'id');

        $assets = [];
        $items = [];

        foreach ($promotedSlugs as $slug) {
            $stageRow = $stageBySlug[$slug];
            $desired = array_intersect_key($stageRow, array_flip($fields));

            // Remapear categoria para produção por slug
            $catSlug = $stageCategories[$stageRow['categoria_post_id']] ?? $stageRow['categoria'];
            if (!isset($prodCategories[$catSlug])) {
                throw new RuntimeException("Categoria '$catSlug' não existe em produção para o artigo $slug");
            }
            $desired['categoria_post_id'] = (int) $prodCategories[$catSlug];
            $desired['categoria'] = $catSlug;

            // Remapear autor para produção por nome
            $authorName = $stageAuthors[$stageRow['autor_id']] ?? 'Estratégia Nerd';
            if (!isset($prodAuthors[$authorName])) {
                throw new RuntimeException("Autor '$authorName' não existe em produção para o artigo $slug");
            }
            $desired['autor_id'] = (int) $prodAuthors[$authorName];

            // Próximo post é nulo para estes 4 artigos
            $desired['proximo_post_id'] = null;

            // Mídias referenciadas
            $references = promotionReferences($stageRow);
            foreach ($references as $ref) {
                $path = promotionPath($ref);
                if ($path === null) {
                    continue;
                }
                if (!isset($assets[$path])) {
                    $localAssetPath = base_path('public/' . $path);
                    if (!is_file($localAssetPath)) {
                        throw new RuntimeException('Arquivo local do asset não encontrado: ' . $localAssetPath);
                    }
                    $bytes = (string) file_get_contents($localAssetPath);
                    $sha256 = hash('sha256', $bytes);
                    $assetFileName = 'asset-' . substr($sha256, 0, 16) . '.bin';
                    $assetStoragePath = $dir . '/' . $assetFileName;
                    if (!is_file($assetStoragePath)) {
                        file_put_contents($assetStoragePath, $bytes);
                    }
                    $assets[$path] = [
                        'path' => $path,
                        'file' => $assetFileName,
                        'sha256' => $sha256,
                        'bytes' => strlen($bytes),
                    ];
                }
            }

            $items[$slug] = [
                'desired' => $desired,
                'before' => $prodBySlug[$slug] ?? null,
            ];
        }

        if (count($assets) !== 16) {
            throw new RuntimeException('Esperados exatamente 16 assets para os 4 artigos; encontrados ' . count($assets));
        }

        promotionJson($packagePath, [
            'environment' => 'production',
            'sourceFingerprint' => $sourceFingerprint,
            'before' => $before,
            'items' => $items,
            'assets' => array_values($assets),
            'preparedAt' => date('c'),
        ]);

        echo 'PASS: pacote de promoção para produção preparado com 4 artigos e ' . count($assets) . " assets. Backup imutável salvo.\n";
        exit(0);
    }

    $package = json_decode((string) file_get_contents($packagePath), true, 512, JSON_THROW_ON_ERROR);
    if ($package['environment'] !== 'production' || $package['sourceFingerprint'] !== $sourceFingerprint) {
        throw new RuntimeException('Origem em stage divergiu desde a preparação do pacote.');
    }

    if ($action === '--apply') {
        if (!in_array('tipo_post', array_column($production->query('SHOW COLUMNS FROM posts')->fetchAll(PDO::FETCH_ASSOC), 'Field'), true)) {
            throw new RuntimeException('posts.tipo_post ausente em produção.');
        }

        // 1. Upload seguro dos 16 assets para FTP de produção
        foreach ($package['assets'] as $asset) {
            $file = $dir . '/' . $asset['file'];
            if (hash_file('sha256', $file) !== $asset['sha256']) {
                throw new RuntimeException('Asset local corrompido: ' . $asset['path']);
            }
            $current = promotionBytes($ftp, $ftpRoot, $asset['path']);
            if ($current === null) {
                TargetEnvironmentUploads::putFile('production', $file, $asset['path']);
                $current = promotionBytes($ftp, $ftpRoot, $asset['path']);
            }
            if ($current === null || hash('sha256', $current) !== $asset['sha256']) {
                throw new RuntimeException('Asset remoto em produção divergente ou não enviado: ' . $asset['path']);
            }
        }

        // 2. Reconectar banco de produção para evitar idle timeout após uploads FTP
        $freshFingerprint = [];
        $freshStage = promotionFreshDatabase('stage');
        $freshStageRows = array_column(promotionRows($freshStage), null, 'slug');
        foreach ($promotedSlugs as $slug) {
            $freshFingerprint[$slug] = hash('sha256', json_encode(array_intersect_key($freshStageRows[$slug], array_flip($fields)), JSON_THROW_ON_ERROR));
        }
        if ($freshFingerprint !== $package['sourceFingerprint']) {
            throw new RuntimeException('Origem em stage sofreu alteração concorrente durante os uploads.');
        }

        $prod = promotionFreshDatabase('production');
        $repo = new PostRepository($prod);
        $prod->beginTransaction();

        try {
            // Travar tabela posts para atualização segura
            $prod->query('SELECT id FROM posts ORDER BY id FOR UPDATE')->fetchAll();
            $currentRows = promotionRows($prod);
            $currentBySlug = array_column($currentRows, null, 'slug');

            if (count($currentRows) !== 33) {
                throw new RuntimeException('Contagem concorrente de artigos em produção divergente dos 33 esperados.');
            }

            $ids = array_column($currentRows, 'id', 'slug');

            // Inserir os 4 novos posts
            foreach ($package['items'] as $slug => $item) {
                if (isset($currentBySlug[$slug])) {
                    throw new RuntimeException('Artigo a promover já existe em produção: ' . $slug);
                }
                $data = $item['desired'];
                $newId = $repo->insertAdmin($data);
                $ids[$slug] = $newId;
            }

            // Validar que os 33 artigos originais permanecem estritamente intactos em todos os campos operacionais
            $afterRows = promotionRows($prod);
            $afterBySlug = array_column($afterRows, null, 'slug');

            if (count($afterRows) !== 37) {
                throw new RuntimeException('Contagem final pós-inserção diverge de 37 artigos.');
            }

            foreach ($currentRows as $old) {
                $now = $afterBySlug[$old['slug']] ?? null;
                if ($now === null) {
                    throw new RuntimeException('Artigo preexistente sumiu de produção: ' . $old['slug']);
                }
                // Nenhum campo operacional pode ter mudado
                foreach ($old as $k => $v) {
                    if ($now[$k] !== $v) {
                        throw new RuntimeException("Campo operacional '$k' alterado no post '{$old['slug']}'.");
                    }
                }
            }

            // Validar que as categorias em produção permaneceram idênticas
            if ($prod->query('SELECT * FROM categoria_post ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) !== $package['before']['categories']) {
                throw new RuntimeException('Categorias em produção foram indevidamente alteradas.');
            }

            $prod->commit();

            file_put_contents($dir . '/applied.json', json_encode([
                'promotedSlugs' => $promotedSlugs,
                'ids' => $ids,
                'appliedAt' => date('c'),
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            echo "PASS: 4 novos artigos inseridos com sucesso em produção. 33 artigos e categorias existentes 100% preservados.\n";
        } catch (Throwable $e) {
            if ($prod->inTransaction()) {
                $prod->rollBack();
            }
            throw $e;
        }
    }

    // Fase de verificação
    $finalRows = promotionRows($production);
    $finalBySlug = array_column($finalRows, null, 'slug');

    if (count($finalRows) !== 37) {
        throw new RuntimeException('Produção não possui os 37 artigos esperados.');
    }

    foreach ($package['items'] as $slug => $item) {
        if (!isset($finalBySlug[$slug])) {
            throw new RuntimeException('Artigo ausente em produção: ' . $slug);
        }
        if (!promotionSame($finalBySlug[$slug], $item['desired'])) {
            throw new RuntimeException('Dados do artigo em produção divergem do desejado: ' . $slug);
        }
    }

    // Conferir hashes dos 16 assets remotos via FTP
    foreach ($package['assets'] as $asset) {
        $bytes = promotionBytes($ftp, $ftpRoot, $asset['path']);
        if ($bytes === null || hash('sha256', $bytes) !== $asset['sha256']) {
            throw new RuntimeException('Hash remoto do asset diverge em produção: ' . $asset['path']);
        }
    }

    // Validação HTTP das páginas públicas e imagens em produção
    $httpContext = stream_context_create([
        'http' => [
            'timeout' => 15,
            'ignore_errors' => true,
            'user_agent' => 'EstrategiaNerdPromotionVerifier/1.0',
        ],
    ]);

    foreach ($promotedSlugs as $slug) {
        $url = 'https://estrategianerd.com.br/post/' . $slug;
        $html = @file_get_contents($url, false, $httpContext);
        $statusLine = $http_response_header[0] ?? '';
        if (!str_contains($statusLine, '200 OK')) {
            throw new RuntimeException("URL pública falhou em produção ($statusLine): $url");
        }
        if ($html === false || !str_contains($html, 'uploads/posts/' . $slug . '/images/capa.webp')) {
            throw new RuntimeException("Capa não referenciada na página pública em produção: $url");
        }
    }

    foreach ($package['assets'] as $asset) {
        $url = 'https://estrategianerd.com.br/' . $asset['path'];
        $bin = @file_get_contents($url, false, $httpContext);
        $statusLine = $http_response_header[0] ?? '';
        if (!str_contains($statusLine, '200 OK')) {
            throw new RuntimeException("Imagem pública falhou em produção ($statusLine): $url");
        }
        if ($bin === false || strlen($bin) !== $asset['bytes']) {
            throw new RuntimeException("Tamanho da imagem pública diverge em produção: $url");
        }
    }

    file_put_contents($dir . '/verified.json', json_encode([
        'totalPosts' => count($finalRows),
        'promotedPosts' => count($promotedSlugs),
        'assetsVerified' => count($package['assets']),
        'httpStatus' => 'all 200 OK',
        'verifiedAt' => date('c'),
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    echo "PASS: 37 artigos finais conferidos em produção; 16 assets remotos e requisições HTTP 200 validadas com sucesso.\n";
} finally {
    ftp_close($ftp);
}
