<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/backup/EnvLoader.php';
use Scripts\Backup\EnvLoader;
$root = dirname(__DIR__, 2);
EnvLoader::load($root . '/.env');
$config = require $root . '/config/content-sync.php';
$dir = $root . '/storage/previews/reels-review/blog-covers-20261008';
$covers = json_decode((string) file_get_contents($dir . '/covers.json'), true, 512, JSON_THROW_ON_ERROR);
$slugs = [
    'msi-mag-b650-tomahawk-wifi-a-base-que-sustenta-um-setup-de-verdade',
    'kingston-nv3-1tb-nvme-pcie-4-0-velocidade-real-para-o-dia-a-dia',
];
function coverPdo(array $profile): PDO {
    $d = $profile['database'];
    return new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $d['host'], $d['port'], $d['database']), $d['username'], $d['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
}
function coverFtp(array $uploads): ?FTP\Connection {
    if ($uploads['mode'] === 'local') { return null; }
    if ($uploads['mode'] !== 'ftp' || !str_ends_with(rtrim($uploads['root'], '/'), '/uploads')) { throw new RuntimeException('Destino de uploads inválido'); }
    $ftp = @ftp_connect($uploads['host'], (int) $uploads['port'], 30);
    if (!$ftp || !@ftp_login($ftp, $uploads['username'], $uploads['password'])) { throw new RuntimeException('Conexão FTP indisponível'); }
    ftp_pasv($ftp, (bool) $uploads['passive']);
    return $ftp;
}
function coverPath(array $uploads, string $relative): string {
    if (!preg_match('~^uploads/posts/[a-z0-9-]+/images/[a-z0-9.-]+\.(?:webp|png|jpg)$~', $relative)) { throw new RuntimeException('Referência de capa fora do escopo'); }
    $base = $uploads['mode'] === 'local' ? $uploads['path'] : $uploads['root'];
    return rtrim($base, '\\/') . '/' . substr($relative, strlen('uploads/'));
}
function fetchCover(array $uploads, ?FTP\Connection $ftp, string $relative, string $destination): void {
    if (file_exists($destination)) { throw new RuntimeException('Destino de evidência existente'); }
    $source = coverPath($uploads, $relative);
    $ok = $ftp ? @ftp_get($ftp, $destination, $source, FTP_BINARY) : copy($source, $destination);
    if (!$ok || !is_file($destination) || filesize($destination) < 100) { throw new RuntimeException('Não foi possível verificar a imagem'); }
}
function saveCoverJson(string $path, array $data): void {
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}
function coverRows(PDO $pdo, array $slugs, bool $lock = false): array {
    $stmt = $pdo->prepare('SELECT * FROM posts WHERE slug IN (?,?) ORDER BY slug' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute($slugs); $rows = $stmt->fetchAll();
    if (count($rows) !== 2) { throw new RuntimeException('Os dois artigos devem existir exatamente uma vez'); }
    return $rows;
}
function assertOtherFields(array $before, array $after): void {
    // posts.data_atualizacao is maintained by MySQL ON UPDATE CURRENT_TIMESTAMP.
    unset($before['imagem_capa'], $after['imagem_capa'], $before['data_atualizacao'], $after['data_atualizacao']);
    if ($before !== $after) { throw new RuntimeException('Outro campo do artigo mudou; interrompido'); }
}
$ftp = null; $pdo = null;
try {
    $action = $argv[1] ?? ''; $target = $argv[2] ?? '';
    if (!in_array($action, ['--inspect','--apply','--verify','--rollback'], true) || !in_array($target, ['local','stage','production'], true)) { throw new RuntimeException('Uso: --inspect|--apply|--verify|--rollback local|stage|production'); }
    if (count($covers) !== 2 || array_column($covers, 'slug') !== $slugs) { throw new RuntimeException('Manifesto não corresponde aos dois artigos aprovados'); }
    foreach ($covers as $cover) {
        if (!preg_match('/^capa-corrigida-[a-f0-9]{12}\.webp$/', $cover['name']) || hash_file('sha256', $cover['file']) !== $cover['sha256']) { throw new RuntimeException('Integridade de capa inválida'); }
    }
    $profile = $config['profiles'][$target]; $uploads = $profile['uploads'];
    $pdo = coverPdo($profile); $ftp = coverFtp($uploads);
    $stateFile = $dir . '/references-' . $target . '.json';
    if ($action === '--inspect') {
        if (file_exists($stateFile)) { throw new RuntimeException('Backup de referências já existe; não sobrescrever'); }
        $rows = coverRows($pdo, $slugs); $entries = [];
        foreach ($rows as $row) {
            $cover = array_values(array_filter($covers, static fn(array $c): bool => $c['slug'] === $row['slug']))[0];
            $backup = $dir . '/original-' . $target . '-' . $row['id'] . '.webp';
            fetchCover($uploads, $ftp, $row['imagem_capa'], $backup);
            $entries[] = ['row'=>$row,'originalFile'=>$backup,'originalSha256'=>hash_file('sha256', $backup),'newPath'=>dirname($row['imagem_capa']) . '/' . $cover['name'],'newSha256'=>$cover['sha256']];
        }
        saveCoverJson($stateFile, ['environment'=>$target,'capturedAt'=>date(DATE_ATOM),'items'=>$entries,'allReferences'=>$pdo->query('SELECT id,slug,imagem_capa FROM posts ORDER BY id')->fetchAll()]);
        echo "BACKUP $target: duas referências, campos e capas originais preservados\n";
    } else {
        $state = json_decode((string) file_get_contents($stateFile), true, 512, JSON_THROW_ON_ERROR);
        $rows = coverRows($pdo, $slugs);
        foreach ($state['items'] as $item) {
            $row = array_values(array_filter($rows, static fn(array $r): bool => $r['slug'] === $item['row']['slug']))[0];
            assertOtherFields($item['row'], $row);
            if (!in_array($row['imagem_capa'], [$item['row']['imagem_capa'], $item['newPath']], true)) { throw new RuntimeException('Referência mudou desde o backup'); }
            if (hash_file('sha256', $item['originalFile']) !== $item['originalSha256']) { throw new RuntimeException('Backup original divergente'); }
        }
        if ($action === '--apply') {
            if ($target === 'production') {
                $policy = $config['deployment_policy'];
                if ($policy['current_source'] !== 'stage' || $policy['approved_source'] !== 'stage' || !is_file($dir . '/verified-stage.json')) { throw new RuntimeException('Produção exige origem stage e validação prévia'); }
            }
            if ($target === 'stage' && !is_file($dir . '/verified-local.json')) { throw new RuntimeException('Stage exige validação local prévia'); }
            foreach ($state['items'] as $item) {
                $cover = array_values(array_filter($covers, static fn(array $c): bool => $c['slug'] === $item['row']['slug']))[0];
                $source = $target === 'production' ? $dir . '/verified-stage-' . $item['row']['slug'] . '.webp' : $cover['file'];
                if (hash_file('sha256', $source) !== $cover['sha256']) { throw new RuntimeException('Arte validada na origem divergente'); }
                $destination = coverPath($uploads, $item['newPath']);
                $exists = $ftp ? @ftp_size($ftp, $destination) >= 0 : is_file($destination);
                if (!$exists) {
                    $ok = $ftp ? @ftp_put($ftp, $destination, $source, FTP_BINARY) : copy($source, $destination);
                    if (!$ok) { throw new RuntimeException('Falha de envio da nova capa'); }
                }
                $proof = $dir . '/uploaded-' . $target . '-' . bin2hex(random_bytes(4)) . '.webp';
                fetchCover($uploads, $ftp, $item['newPath'], $proof);
                if (hash_file('sha256', $proof) !== $item['newSha256']) { throw new RuntimeException('Destino existente ou upload divergente; não sobrescrito'); }
            }
        }
        if (in_array($action, ['--apply','--rollback'], true)) {
            $pdo->beginTransaction(); $locked = coverRows($pdo, $slugs, true);
            foreach ($state['items'] as $item) {
                $row = array_values(array_filter($locked, static fn(array $r): bool => $r['slug'] === $item['row']['slug']))[0]; assertOtherFields($item['row'], $row);
                $from = $action === '--apply' ? $item['row']['imagem_capa'] : $item['newPath'];
                $to = $action === '--apply' ? $item['newPath'] : $item['row']['imagem_capa'];
                if ($row['imagem_capa'] === $to) { continue; }
                if ($row['imagem_capa'] !== $from) { throw new RuntimeException('Concorrência na referência de capa'); }
                $stmt = $pdo->prepare('UPDATE posts SET imagem_capa=? WHERE id=? AND slug=? AND imagem_capa=?');
                $stmt->execute([$to,$row['id'],$row['slug'],$from]);
                if ($stmt->rowCount() !== 1) { throw new RuntimeException('Atualização de capa não afetou exatamente um artigo'); }
            }
            $pdo->commit(); echo "UPDATED $target: apenas imagem_capa dos dois artigos\n";
        }
        if ($action !== '--rollback') {
            $after = coverRows($pdo, $slugs); $verified = [];
            foreach ($state['items'] as $item) {
                $row = array_values(array_filter($after, static fn(array $r): bool => $r['slug'] === $item['row']['slug']))[0]; assertOtherFields($item['row'], $row);
                if ($row['imagem_capa'] !== $item['newPath']) { throw new RuntimeException('Referência final não aponta para capa corrigida'); }
                $proof = $dir . '/current-' . $target . '-' . bin2hex(random_bytes(4)) . '.webp';
                fetchCover($uploads, $ftp, $item['newPath'], $proof);
                if (hash_file('sha256', $proof) !== $item['newSha256']) { throw new RuntimeException('Arte final divergente'); }
                $verifiedFile = $dir . '/verified-' . $target . '-' . $row['slug'] . '.webp';
                if (!is_file($verifiedFile) && !copy($proof, $verifiedFile)) { throw new RuntimeException('Falha ao guardar arte verificada'); }
                if (hash_file('sha256', $verifiedFile) !== $item['newSha256']) { throw new RuntimeException('Evidência da origem divergente'); }
                $originalProof = $dir . '/original-check-' . $target . '-' . bin2hex(random_bytes(4)) . '.webp';
                fetchCover($uploads, $ftp, $item['row']['imagem_capa'], $originalProof);
                if (hash_file('sha256', $originalProof) !== $item['originalSha256']) { throw new RuntimeException('Imagem original alterada'); }
                $verified[] = ['id'=>$row['id'],'slug'=>$row['slug'],'path'=>$row['imagem_capa'],'sha256'=>$item['newSha256'],'otherContentFieldsUnchanged'=>true,'automaticUpdateTimestamp'=>$row['data_atualizacao'],'originalPreserved'=>true];
            }
            $all = $pdo->query('SELECT id,slug,imagem_capa FROM posts ORDER BY id')->fetchAll();
            foreach ($state['allReferences'] as $old) {
                if (in_array($old['slug'], $slugs, true)) { continue; }
                $current = array_values(array_filter($all, static fn(array $r): bool => $r['id'] === $old['id']));
                if (count($current) !== 1 || $current[0] !== $old) { throw new RuntimeException('Outro artigo mudou durante a operação'); }
            }
            saveCoverJson($dir . '/verified-' . $target . '.json', ['environment'=>$target,'verifiedAt'=>date(DATE_ATOM),'items'=>$verified,'unrelatedReferencesUnchanged'=>true]);
            echo "PASS $target: arquivos íntegros, originais preservados e demais referências intactas\n";
        }
    }
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, $e instanceof PDOException ? 'Falha de banco; credenciais e diagnóstico sensível omitidos' . PHP_EOL : $e->getMessage() . PHP_EOL); exit(1);
} finally {
    if ($ftp instanceof FTP\Connection) { ftp_close($ftp); }
}
