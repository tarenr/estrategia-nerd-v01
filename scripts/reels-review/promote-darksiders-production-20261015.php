<?php
declare(strict_types=1);

// Promoção dos 6 posts da saga Darksiders de stage para produção.
// Origem: stage (IDs 50 a 55, validados com status 'publicado').
// Destino: produção (39 artigos existentes preservados; passa a 45 artigos).
// Uso: php scripts/reels-review/promote-darksiders-production-20261015.php --inspect|--prepare|--apply|--verify

if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Repositories\PostRepository;
use App\Support\TargetEnvironmentDatabase;
use App\Support\TargetEnvironmentUploads;

$action = $argv[1] ?? '--inspect';
if (!in_array($action, ['--inspect', '--prepare', '--apply', '--verify'], true)) {
    throw new RuntimeException('Ação inválida. Use --inspect, --prepare, --apply ou --verify.');
}

$promotedSlugs = [
    'o-mundo-de-darksiders-ceu-inferno-e-o-equilibrio',
    'os-nephilim-a-raca-proibida-de-darksiders',
    'os-quatro-cavaleiros-de-darksiders',
    'o-apocalipse-antecipado-de-darksiders',
    'a-jornada-de-morte-em-darksiders',
    'a-furia-do-equilibrio-em-darksiders',
];

$fields = [
    'titulo', 'slug', 'resumo', 'conteudo', 'categoria', 'categoria_post_id',
    'imagem_capa', 'imagem_thumb', 'autor_id', 'data_publicacao', 'tempo_leitura',
    'seo_title', 'seo_description', 'seo_keywords', 'tags', 'status', 'destaque',
    'tipo_post', 'proximo_post_id'
];

$dir = base_path('storage/production-content/darksiders-20261015');
$packagePath = $dir . '/package.json';

function dkProdRows(PDO $pdo): array {
    return $pdo->query('SELECT * FROM posts ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
}

function dkProdFreshDb(string $env): PDO {
    $db = TargetEnvironmentDatabase::profile($env)['database'];
    return new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']),
        $db['username'],
        $db['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function dkProdPath(string $reference): ?string {
    if (preg_match('~(?:^|/)(uploads/posts/[a-z0-9-]+/[a-zA-Z0-9_./-]+\.(?:webp|png|jpg|jpeg|gif|mp3))(?:\?.*)?$~', $reference, $m) !== 1) {
        return null;
    }
    if (str_contains($m[1], '..')) {
        throw new RuntimeException('Caminho inseguro detectado: ' . $m[1]);
    }
    return $m[1];
}

function dkProdReferences(array $post): array {
    preg_match_all('~(?:src|data-src|poster|data-audio-narracao)=["\x27]([^"\x27]+)["\x27]~i', (string) $post['conteudo'], $m);
    return array_values(array_unique(array_filter([$post['imagem_capa'], $post['imagem_thumb'], ...$m[1]])));
}

function dkProdJson(string $path, array $value): void {
    $bytes = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $handle = fopen($path, 'x');
    if ($handle === false) {
        throw new RuntimeException('Arquivo de evidência já existe: ' . basename($path));
    }
    try {
        if (fwrite($handle, $bytes) !== strlen($bytes) || !fflush($handle)) {
            throw new RuntimeException('Falha ao escrever evidência: ' . basename($path));
        }
    } finally {
        fclose($handle);
    }
}

function dkProdFtp(): array {
    $profile = TargetEnvironmentDatabase::profile('production')['uploads'];
    $root = '/' . trim((string) $profile['root'], '/');
    if (($profile['mode'] ?? '') !== 'ftp' || str_contains($root, '..') || !str_ends_with($root, '/uploads') || str_contains($root, '/stage/')) {
        throw new RuntimeException('Destino FTP inválido para produção: ' . $root);
    }
    $ftp = ftp_connect($profile['host'], (int) $profile['port'], 30);
    if ($ftp === false || !ftp_login($ftp, $profile['username'], $profile['password'])) {
        throw new RuntimeException('FTP de produção indisponível.');
    }
    ftp_pasv($ftp, (bool) ($profile['passive'] ?? true));
    return [$ftp, $root];
}

function dkProdBytes(FTP\Connection $ftp, string $root, string $path): ?string {
    if (dkProdPath($path) !== $path) {
        throw new RuntimeException('Asset fora de uploads/posts: ' . $path);
    }
    $remote = $root . '/' . substr($path, 8);
    if (@ftp_size($ftp, $remote) < 0) {
        return null;
    }
    $stream = fopen('php://temp', 'w+');
    if ($stream === false || !ftp_fget($ftp, $stream, $remote, FTP_BINARY)) {
        throw new RuntimeException('Não foi possível ler asset remoto em produção: ' . $remote);
    }
    rewind($stream);
    $bytes = stream_get_contents($stream);
    fclose($stream);
    if ($bytes === false || $bytes === '') {
        throw new RuntimeException('Asset remoto vazio em produção: ' . $remote);
    }
    return $bytes;
}

function dkProdSame(array $row, array $desired): bool {
    foreach ($desired as $field => $value) {
        if (($row[$field] ?? null) != $value) {
            return false;
        }
    }
    return true;
}

$stage = TargetEnvironmentDatabase::pdo('stage');
$production = TargetEnvironmentDatabase::pdo('production');

$prodRows = dkProdRows($production);
$stageRows = dkProdRows($stage);

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

if ($action === '--prepare' && count($prodRows) !== 39) {
    throw new RuntimeException('Produção esperada com exatamente 39 artigos para preparação; encontrados ' . count($prodRows));
}
if ($action === '--verify' && count($prodRows) !== 45) {
    throw new RuntimeException('Produção esperada com exatamente 45 artigos para verificação; encontrados ' . count($prodRows));
}
if (count($stageRows) !== 44) {
    throw new RuntimeException('Stage esperado com exatamente 44 artigos; encontrados ' . count($stageRows));
}

// Fingerprints dos 6 artigos a partir de stage validado
$sourceFingerprint = [];
foreach ($promotedSlugs as $slug) {
    if (!isset($stageBySlug[$slug])) {
        throw new RuntimeException('Artigo ausente em stage: ' . $slug);
    }
    $sourceFingerprint[$slug] = hash('sha256', json_encode(array_intersect_key($stageBySlug[$slug], array_flip($fields)), JSON_THROW_ON_ERROR));
}

[$ftp, $ftpRoot] = dkProdFtp();

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

        // Mapeamentos de categorias e autores
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

            // Próximo post é nulo para os 6 artigos
            $desired['proximo_post_id'] = null;

            // Mídias referenciadas
            $references = dkProdReferences($stageRow);
            foreach ($references as $ref) {
                $path = dkProdPath($ref);
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
                    $assetFileName = 'asset-' . $sha256 . '.bin';
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

        if (count($assets) !== 36) {
            throw new RuntimeException('Esperados exatamente 36 assets para os 6 artigos; encontrados ' . count($assets));
        }

        dkProdJson($packagePath, [
            'environment' => 'production',
            'sourceFingerprint' => $sourceFingerprint,
            'before' => $before,
            'items' => $items,
            'assets' => array_values($assets),
            'preparedAt' => date('c'),
        ]);

        echo 'PASS: pacote de promoção para produção preparado com 6 artigos e ' . count($assets) . " assets. Backup imutável salvo.\n";
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

        // 1. Upload seguro dos 36 assets para FTP de produção
        echo 'Iniciando upload de ' . count($package['assets']) . " assets via FTP para produção...\n";
        $uploaded = 0;
        foreach ($package['assets'] as $asset) {
            $file = $dir . '/' . $asset['file'];
            if (hash_file('sha256', $file) !== $asset['sha256']) {
                throw new RuntimeException('Asset local corrompido: ' . $asset['path']);
            }
            $current = dkProdBytes($ftp, $ftpRoot, $asset['path']);
            if ($current === null) {
                TargetEnvironmentUploads::putFile('production', $file, $asset['path']);
                $current = dkProdBytes($ftp, $ftpRoot, $asset['path']);
                $uploaded++;
            }
            if ($current === null || hash('sha256', $current) !== $asset['sha256']) {
                throw new RuntimeException('Asset remoto em produção divergente ou não enviado: ' . $asset['path']);
            }
        }
        echo "PASS: $uploaded assets novos enviados; total de 36 assets conferidos com sucesso no FTP.\n";

        // 2. Reconectar banco de produção para evitar idle timeout após uploads FTP
        $freshFingerprint = [];
        $freshStage = dkProdFreshDb('stage');
        $freshStageRows = array_column(dkProdRows($freshStage), null, 'slug');
        foreach ($promotedSlugs as $slug) {
            $freshFingerprint[$slug] = hash('sha256', json_encode(array_intersect_key($freshStageRows[$slug], array_flip($fields)), JSON_THROW_ON_ERROR));
        }
        if ($freshFingerprint !== $package['sourceFingerprint']) {
            throw new RuntimeException('Origem em stage sofreu alteração concorrente durante os uploads.');
        }

        $prod = dkProdFreshDb('production');
        $repo = new PostRepository($prod);
        $prod->beginTransaction();

        try {
            // Travar tabela posts para atualização segura
            $prod->query('SELECT id FROM posts ORDER BY id FOR UPDATE')->fetchAll();
            $currentRows = dkProdRows($prod);
            $currentBySlug = array_column($currentRows, null, 'slug');

            if (count($currentRows) !== 39) {
                throw new RuntimeException('Contagem concorrente de artigos em produção divergente dos 39 esperados.');
            }

            $ids = array_column($currentRows, 'id', 'slug');

            // Inserir os 6 novos posts
            foreach ($package['items'] as $slug => $item) {
                if (isset($currentBySlug[$slug])) {
                    throw new RuntimeException('Artigo a promover já existe em produção: ' . $slug);
                }
                $data = $item['desired'];
                $newId = $repo->insertAdmin($data);
                $ids[$slug] = $newId;
            }

            // Validar que os 39 artigos originais permanecem estritamente intactos em todos os campos operacionais
            $afterRows = dkProdRows($prod);
            $afterBySlug = array_column($afterRows, null, 'slug');

            if (count($afterRows) !== 45) {
                throw new RuntimeException('Contagem final pós-inserção diverge de 45 artigos.');
            }

            foreach ($currentRows as $old) {
                $now = $afterBySlug[$old['slug']] ?? null;
                if ($now === null) {
                    throw new RuntimeException('Artigo preexistente sumiu de produção: ' . $old['slug']);
                }
                foreach ($old as $k => $v) {
                    if ($now[$k] !== $v) {
                        throw new RuntimeException("Campo operacional '$k' alterado no post preexistente '{$old['slug']}'.");
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

            echo "PASS: 6 novos artigos da saga Darksiders inseridos com sucesso em produção. 39 artigos preexistentes 100% preservados.\n";
        } catch (Throwable $e) {
            if ($prod->inTransaction()) {
                $prod->rollBack();
            }
            throw $e;
        }
    }

    // Fase de verificação
    $finalRows = dkProdRows(dkProdFreshDb('production'));
    $finalBySlug = array_column($finalRows, null, 'slug');

    if (count($finalRows) !== 45) {
        throw new RuntimeException('Produção não possui os 45 artigos esperados (encontrados: ' . count($finalRows) . ').');
    }

    foreach ($package['items'] as $slug => $item) {
        if (!isset($finalBySlug[$slug])) {
            throw new RuntimeException('Artigo ausente em produção: ' . $slug);
        }
        if (!dkProdSame($finalBySlug[$slug], $item['desired'])) {
            throw new RuntimeException('Dados do artigo em produção divergem do desejado: ' . $slug);
        }
    }

    // Conferir hashes dos 36 assets remotos via FTP
    foreach ($package['assets'] as $asset) {
        $bytes = dkProdBytes($ftp, $ftpRoot, $asset['path']);
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
        if ($html === false || !str_contains($html, 'uploads/posts/' . $slug . '/images/capa.')) {
            throw new RuntimeException("Capa não referenciada na página pública em produção: $url");
        }
    }

    foreach ($package['assets'] as $asset) {
        $url = 'https://estrategianerd.com.br/' . $asset['path'];
        $bin = @file_get_contents($url, false, $httpContext);
        $statusLine = $http_response_header[0] ?? '';
        if (!str_contains($statusLine, '200 OK')) {
            throw new RuntimeException("Asset público falhou em produção ($statusLine): $url");
        }
        if ($bin === false || strlen($bin) !== $asset['bytes']) {
            throw new RuntimeException("Tamanho do asset público diverge em produção: $url");
        }
    }

    file_put_contents($dir . '/verified.json', json_encode([
        'totalPosts' => count($finalRows),
        'promotedPosts' => count($promotedSlugs),
        'assetsVerified' => count($package['assets']),
        'httpStatus' => 'all 200 OK',
        'verifiedAt' => date('c'),
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    echo "PASS: 45 artigos finais conferidos em produção; 36 assets remotos e requisições HTTP 200 validadas com sucesso.\n";
} finally {
    ftp_close($ftp);
}
