<?php
declare(strict_types=1);

// Escreve SOMENTE na stage: publica os 6 artigos da saga Darksiders (origem: banco e uploads locais).
// Uso: php scripts/reels-review/stage-darksiders-20261015.php --inspect|--prepare|--apply|--verify|--publish|--unpublish
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Repositories\PostRepository;
use App\Support\TargetEnvironmentDatabase;
use App\Support\TargetEnvironmentUploads;

$action = $argv[1] ?? '--inspect';
if (!in_array($action, ['--inspect', '--prepare', '--apply', '--verify', '--publish', '--unpublish'], true)) { throw new RuntimeException('Ação inválida.'); }

$slugs = [
    'o-mundo-de-darksiders-ceu-inferno-e-o-equilibrio',
    'os-nephilim-a-raca-proibida-de-darksiders',
    'os-quatro-cavaleiros-de-darksiders',
    'o-apocalipse-antecipado-de-darksiders',
    'a-jornada-de-morte-em-darksiders',
    'a-furia-do-equilibrio-em-darksiders',
];
// Decisão do usuário (15/10/2026): o #87 está com categoria 0 no local; na cópia da stage entra em "games".
$categoryOverride = ['a-jornada-de-morte-em-darksiders' => 'games'];
$fields = ['titulo', 'slug', 'resumo', 'conteudo', 'categoria', 'categoria_post_id', 'imagem_capa', 'imagem_thumb', 'autor_id',
    'data_publicacao', 'tempo_leitura', 'seo_title', 'seo_description', 'seo_keywords', 'tags', 'status', 'destaque', 'tipo_post'];

if (count($slugs) !== 6) { throw new RuntimeException('Lista de slugs inválida.'); }
foreach ($slugs as $slug) { if (!str_contains($slug, 'darksiders') && $slug !== 'a-jornada-de-morte-em-darksiders' && $slug !== 'a-furia-do-equilibrio-em-darksiders') { throw new RuntimeException('Slug fora da saga: ' . $slug); } }

$local = $GLOBALS['pdo'];
$stage = TargetEnvironmentDatabase::pdo('stage');
$dir = base_path('storage/stage-content/darksiders-20261015');
$packagePath = $dir . '/package.json';

function dkRows(PDO $pdo, array $slugs): array {
    $in = implode(',', array_fill(0, count($slugs), '?'));
    $st = $pdo->prepare("SELECT * FROM posts WHERE slug IN ($in) ORDER BY id");
    $st->execute($slugs);
    return array_column($st->fetchAll(PDO::FETCH_ASSOC), null, 'slug');
}
function dkFreshStage(): PDO {
    $database = TargetEnvironmentDatabase::profile('stage')['database'];
    return new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $database['host'], $database['port'], $database['database']),
        $database['username'], $database['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
}
function dkPath(string $reference): ?string {
    if (preg_match('~(?:^|/)(uploads/posts/[a-z0-9-]+/[a-zA-Z0-9_./-]+\.(?:webp|png|jpg|jpeg|gif|mp3))(?:\?.*)?$~', $reference, $m) !== 1) { return null; }
    if (str_contains($m[1], '..')) { throw new RuntimeException('Caminho inseguro.'); }
    return $m[1];
}
function dkReferences(array $post): array {
    preg_match_all('~(?:src|data-src|poster|data-audio-narracao)=["\x27]([^"\x27]+)["\x27]~i', (string) $post['conteudo'], $m);
    return array_values(array_unique(array_filter([$post['imagem_capa'], $post['imagem_thumb'], ...$m[1]])));
}
function dkJson(string $path, array $value): void {
    $bytes = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $handle = fopen($path, 'x');
    if ($handle === false) { throw new RuntimeException('Arquivo de evidência já existe.'); }
    try { if (fwrite($handle, $bytes) !== strlen($bytes) || !fflush($handle)) { throw new RuntimeException('Evidência incompleta.'); } } finally { fclose($handle); }
}
function dkFtp(): array {
    $profile = TargetEnvironmentDatabase::profile('stage')['uploads'];
    $root = '/' . trim((string) $profile['root'], '/');
    if (($profile['mode'] ?? '') !== 'ftp' || str_contains($root, '..') || !str_ends_with($root, '/stage/uploads')) { throw new RuntimeException('Destino FTP inválido.'); }
    $ftp = ftp_connect($profile['host'], (int) $profile['port'], 30);
    if ($ftp === false || !ftp_login($ftp, $profile['username'], $profile['password'])) { throw new RuntimeException('FTP indisponível.'); }
    ftp_pasv($ftp, (bool) $profile['passive']);
    return [$ftp, $root];
}
function dkRemote(FTP\Connection $ftp, string $root, string $path): ?string {
    if (dkPath($path) !== $path) { throw new RuntimeException('Asset fora de uploads/posts.'); }
    $remote = $root . '/' . substr($path, 8);
    if (@ftp_size($ftp, $remote) < 0) { return null; }
    $stream = fopen('php://temp', 'w+');
    if ($stream === false || !ftp_fget($ftp, $stream, $remote, FTP_BINARY)) { throw new RuntimeException('Não foi possível ler asset remoto.'); }
    rewind($stream); $bytes = stream_get_contents($stream); fclose($stream);
    if ($bytes === false || $bytes === '') { throw new RuntimeException('Asset remoto vazio.'); }
    return $bytes;
}
function dkSame(array $row, array $desired): bool {
    foreach ($desired as $field => $value) { if (($row[$field] ?? null) != $value) { return false; } }
    return true;
}
function dkFingerprint(array $rows, array $fields): array {
    $out = [];
    foreach ($rows as $slug => $row) { $out[$slug] = hash('sha256', json_encode(array_intersect_key($row, array_flip($fields)), JSON_THROW_ON_ERROR)); }
    return $out;
}

$localRows = dkRows($local, $slugs);
if (count($localRows) !== 6) { throw new RuntimeException('Esperados 6 artigos no local; encontrados ' . count($localRows)); }
$stageRows = dkRows($stage, $slugs);
$stageHasType = in_array('tipo_post', array_column($stage->query('SHOW COLUMNS FROM posts')->fetchAll(PDO::FETCH_ASSOC), 'Field'), true);

if ($action === '--inspect') {
    echo json_encode(['local' => array_keys($localRows), 'stageJaTem' => array_keys($stageRows), 'stageTotal' => (int) $stage->query('SELECT COUNT(*) FROM posts')->fetchColumn(), 'stageTemTipoPost' => $stageHasType], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
    exit;
}
if ($action === '--publish' || $action === '--unpublish') {
    // Troca somente o status dos 6 artigos (IDs de applied.json): rascunho <-> publicado, em transação.
    $appliedPath = $dir . '/applied.json';
    if (!is_file($appliedPath)) { throw new RuntimeException('Aplicar o conteúdo antes de publicar.'); }
    $applied = json_decode((string) file_get_contents($appliedPath), true, 512, JSON_THROW_ON_ERROR)['ids'];
    if (array_keys($applied) !== $slugs && array_diff($slugs, array_keys($applied)) !== []) { throw new RuntimeException('IDs aplicados não batem com os slugs.'); }
    $from = $action === '--publish' ? 'rascunho' : 'publicado';
    $to = $action === '--publish' ? 'publicado' : 'rascunho';
    $db = dkFreshStage();
    $db->beginTransaction();
    try {
        $in = implode(',', array_fill(0, 6, '?'));
        $st = $db->prepare("SELECT id,slug,status,views,data_publicacao FROM posts WHERE id IN ($in) FOR UPDATE");
        $st->execute(array_values($applied));
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 6) { throw new RuntimeException('Esperadas 6 linhas na stage; encontradas ' . count($rows)); }
        foreach ($rows as $r) {
            if ((int) $applied[$r['slug']] !== (int) $r['id'] || $r['status'] !== $from) { throw new RuntimeException('Estado inesperado em ' . $r['slug'] . ': ' . $r['status']); }
        }
        $beforePath = $dir . '/before-' . $action . '.json';
        dkJson($beforePath, ['rows' => $rows, 'at' => date('c')]);
        $up = $db->prepare('UPDATE posts SET status = :to WHERE id = :id AND slug = :slug AND status = :from');
        foreach ($rows as $r) {
            $up->execute(['to' => $to, 'id' => $r['id'], 'slug' => $r['slug'], 'from' => $from]);
            if ($up->rowCount() !== 1) { throw new RuntimeException('UPDATE não afetou exatamente 1 linha: ' . $r['slug']); }
        }
        $db->commit();
        echo "PASS: 6 artigos agora '$to' na stage.
";
    } catch (Throwable $e) { if ($db->inTransaction()) { $db->rollBack(); } throw $e; }
    exit;
}
if (!$stageHasType) { throw new RuntimeException('Stage sem a coluna tipo_post; fora do escopo deste script.'); }

$fingerprint = dkFingerprint($localRows, $fields);
[$ftp, $ftpRoot] = dkFtp();
try {
    if ($action === '--prepare') {
        if (is_file($packagePath)) { throw new RuntimeException('Pacote já preparado; preservar backup.'); }
        if ($stageRows !== []) { throw new RuntimeException('A stage já tem artigo da saga; revisar antes de preparar.'); }
        if (!is_dir($dir) && !mkdir($dir, 0700, true)) { throw new RuntimeException('Pasta de backup indisponível.'); }
        $before = ['count' => (int) $stage->query('SELECT COUNT(*) FROM posts')->fetchColumn(), 'maxId' => (int) $stage->query('SELECT MAX(id) FROM posts')->fetchColumn(),
            'categories' => $stage->query('SELECT * FROM categoria_post ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)];
        $localCategories = array_column($local->query('SELECT id,slug FROM categoria_post')->fetchAll(PDO::FETCH_ASSOC), 'slug', 'id');
        $localAuthors = array_column($local->query('SELECT id,nome FROM autores')->fetchAll(PDO::FETCH_ASSOC), 'nome', 'id');
        $stageCategories = array_column($stage->query('SELECT id,slug FROM categoria_post')->fetchAll(PDO::FETCH_ASSOC), 'id', 'slug');
        $stageAuthors = array_column($stage->query('SELECT id,nome FROM autores')->fetchAll(PDO::FETCH_ASSOC), 'id', 'nome');
        $assets = []; $items = [];
        foreach ($slugs as $slug) {
            $row = $localRows[$slug];
            $desired = array_intersect_key($row, array_flip($fields));
            $categorySlug = $categoryOverride[$slug] ?? ($localCategories[(int) $row['categoria_post_id']] ?? null);
            $authorName = $localAuthors[(int) $row['autor_id']] ?? null;
            if ($categorySlug === null || !isset($stageCategories[$categorySlug]) || $authorName === null || !isset($stageAuthors[$authorName])) { throw new RuntimeException('Categoria/autor sem correspondente na stage: ' . $slug); }
            $desired['categoria_post_id'] = (int) $stageCategories[$categorySlug];
            $desired['categoria'] = $categorySlug;
            $desired['autor_id'] = (int) $stageAuthors[$authorName];
            foreach (dkReferences($row) as $reference) {
                $path = dkPath($reference);
                if ($path === null) { continue; }
                if (!isset($assets[$path])) {
                    $file = base_path('public/' . $path);
                    if (!is_file($file) || filesize($file) === 0) { throw new RuntimeException('Arquivo local ausente: ' . $path); }
                    $bytes = (string) file_get_contents($file);
                    $hash = hash('sha256', $bytes);
                    $remote = dkRemote($ftp, $ftpRoot, $path);
                    if ($remote !== null && hash('sha256', $remote) !== $hash) { throw new RuntimeException('Colisão: já existe na stage com conteúdo diferente: ' . $path); }
                    $localFile = $dir . '/asset-' . $hash . '.bin';
                    if (!is_file($localFile) && file_put_contents($localFile, $bytes) !== strlen($bytes)) { throw new RuntimeException('Pacote de mídia incompleto.'); }
                    $assets[$path] = ['path' => $path, 'sha256' => $hash, 'file' => basename($localFile), 'jaExisteIgual' => $remote !== null];
                }
            }
            $items[$slug] = ['desired' => $desired];
        }
        dkJson($packagePath, ['environment' => 'stage', 'sourceFingerprint' => $fingerprint, 'before' => $before, 'items' => $items, 'assets' => array_values($assets), 'preparedAt' => date('c')]);
        echo 'PASS: pacote com 6 artigos e ' . count($assets) . " arquivos; estado anterior da stage preservado.\n";
        exit;
    }
    $package = json_decode((string) file_get_contents($packagePath), true, 512, JSON_THROW_ON_ERROR);
    if ($package['environment'] !== 'stage' || $package['sourceFingerprint'] !== $fingerprint) { throw new RuntimeException('Origem mudou desde a preparação.'); }
    if ($action === '--apply') {
        if (is_file($dir . '/applied.json')) { throw new RuntimeException('Já aplicado; usar --verify.'); }
        foreach ($package['assets'] as $asset) {
            $file = $dir . '/' . $asset['file'];
            if (hash_file('sha256', $file) !== $asset['sha256']) { throw new RuntimeException('Pacote de asset divergente.'); }
            $current = dkRemote($ftp, $ftpRoot, $asset['path']);
            if ($current === null) { TargetEnvironmentUploads::putFile('stage', $file, $asset['path']); $current = dkRemote($ftp, $ftpRoot, $asset['path']); }
            if ($current === null || hash('sha256', $current) !== $asset['sha256']) { throw new RuntimeException('Asset remoto divergente; não sobrescrito.'); }
        }
        // O FTP pode estourar o tempo ocioso: reconecta e reconfere a origem antes da transação.
        if (dkFingerprint(dkRows($local, $slugs), $fields) !== $package['sourceFingerprint']) { throw new RuntimeException('Origem mudou durante o envio dos arquivos.'); }
        $stage = dkFreshStage();
        $repo = new PostRepository($stage);
        $stage->beginTransaction();
        try {
            $stage->query('SELECT id FROM posts ORDER BY id FOR UPDATE')->fetchAll();
            if (dkRows($stage, $slugs) !== []) { throw new RuntimeException('Um slug da saga apareceu na stage durante a operação.'); }
            $countBefore = (int) $stage->query('SELECT COUNT(*) FROM posts')->fetchColumn();
            $ids = [];
            foreach ($package['items'] as $slug => $item) { $ids[$slug] = $repo->insertAdmin($item['desired']); }
            foreach ($package['items'] as $slug => $item) {
                $now = $repo->findAdminById((int) $ids[$slug]);
                if ($now === null || !dkSame($now, $item['desired'])) { throw new RuntimeException('Artigo inserido diverge do pacote: ' . $slug); }
            }
            if ((int) $stage->query('SELECT COUNT(*) FROM posts')->fetchColumn() !== $countBefore + 6) { throw new RuntimeException('Contagem final inesperada.'); }
            if ($stage->query('SELECT * FROM categoria_post ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) !== $package['before']['categories']) { throw new RuntimeException('Categorias foram alteradas.'); }
            $stage->commit();
            file_put_contents($dir . '/applied.json', json_encode(['ids' => $ids, 'appliedAt' => date('c')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            echo "PASS: 6 artigos inseridos somente na stage.\n";
        } catch (Throwable $e) { if ($stage->inTransaction()) { $stage->rollBack(); } throw $e; }
    }
    $stageRows = dkRows(dkFreshStage(), $slugs);
    foreach ($package['items'] as $slug => $item) {
        if (!isset($stageRows[$slug]) || !dkSame($stageRows[$slug], $item['desired'])) { throw new RuntimeException('Conteúdo final divergente: ' . $slug); }
    }
    foreach ($package['assets'] as $asset) {
        $bytes = dkRemote($ftp, $ftpRoot, $asset['path']);
        if ($bytes === null || hash('sha256', $bytes) !== $asset['sha256']) { throw new RuntimeException('Hash remoto divergente: ' . $asset['path']); }
    }
    file_put_contents($dir . '/verified.json', json_encode(['articles' => count($package['items']), 'assets' => count($package['assets']), 'verifiedAt' => date('c')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo 'PASS: 6 artigos e ' . count($package['assets']) . " hashes remotos conferidos.\n";
} finally { ftp_close($ftp); }
