<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/backup/EnvLoader.php';
$root = dirname(__DIR__, 2);
Scripts\Backup\EnvLoader::load($root . '/.env');
$config = require $root . '/config/content-sync.php';
$config['profiles'] = array_intersect_key($config['profiles'], array_flip(['stage', 'production']));
$action = $argv[1] ?? '';
if (!in_array($action, ['--prepare', '--stage-paths', '--retain-existing-covers', '--apply-stage', '--verify-stage', '--apply-production', '--verify-production'], true)) {
    throw new RuntimeException('Use --prepare|--apply-stage|--verify-stage|--apply-production|--verify-production');
}
$dir = $root . '/storage/content-production/merged-20261008';
$stateFile = $dir . '/package.json';
if (is_file($dir . '/package-v2.json')) { $stateFile = $dir . '/package-v2.json'; }
if (is_file($dir . '/package-v3.json')) { $stateFile = $dir . '/package-v3.json'; }
$oldPackage = json_decode((string) file_get_contents($root . '/storage/stage-content/stage-20261008-v2/package.json'), true, 512, JSON_THROW_ON_ERROR);
$art = json_decode((string) file_get_contents($root . '/storage/previews/diablo-artes-20261008/blog-local-backup.json'), true, 512, JSON_THROW_ON_ERROR);
$temporal = json_decode((string) file_get_contents($root . '/storage/previews/reels-review/temporal-20261008/backup.json'), true, 512, JSON_THROW_ON_ERROR);
$slugs = array_column($oldPackage['items'], 'slug');
if (count($slugs) !== 10 || count(array_unique($slugs)) !== 10) { throw new RuntimeException('Escopo exige dez slugs'); }
function mergedScope(array $r): array {
    unset($r['views'], $r['curtidas'], $r['comentarios_count'], $r['likes_count'], $r['data_atualizacao']); return $r;
}
function mergedSave(string $path, array $data): void {
    $h = fopen($path, 'x'); if (!$h) { throw new RuntimeException('Evidencia existente; nao sobrescrever'); }
    fwrite($h, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)); fclose($h);
}
function mergedPath(string $path): string {
    if (!preg_match('~^uploads/posts/[a-z0-9-]+/(?:images/|video/|audio/)?[a-zA-Z0-9_.-]+\.(?:png|jpg|jpeg|webp|mp4|mp3|wav)$~', $path)) { throw new RuntimeException('Caminho fora do escopo'); } return $path;
}
function mergedRelative(string $url): ?string {
    if (preg_match('~(?:^|/)(uploads/posts/[a-z0-9-]+/(?:images/|video/|audio/)?[a-zA-Z0-9_.-]+\.(?:png|jpg|jpeg|webp|mp4|mp3|wav))$~', $url, $m)) { return mergedPath($m[1]); } return null;
}
function mergedRefs(string $html): array {
    preg_match_all('~(?:src|data-src)=["\x27]([^"\x27]+)["\x27]~', $html, $m); return array_values(array_unique($m[1]));
}
function mergedRemote(string $root, string $path): string { return $root . '/' . substr(mergedPath($path), 8); }
function mergedGet(FTP\Connection $ftp, string $remote, string $local): void {
    if (!@ftp_get($ftp, $local, $remote, FTP_BINARY) || !is_file($local) || filesize($local) < 1) { throw new RuntimeException('Arquivo remoto nao conferido'); }
}
function mergedReplace(string $html, string $old, string $new, int $expected = 1): string {
    if (substr_count($html, $old) !== $expected) { throw new RuntimeException('Trecho editorial divergiu: ' . substr($old, 0, 65)); } return str_replace($old, $new, $html);
}
$db = []; $ftp = []; $ftpRoots = [];
foreach ($config['profiles'] as $env => $profile) {
    $d = $profile['database'];
    $db[$env] = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $d['host'], $d['port'], $d['database']), $d['username'], $d['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $u = $profile['uploads']; $remoteRoot = rtrim($u['root'], '/');
    if ($u['mode'] !== 'ftp' || ($env === 'stage' ? !str_ends_with($remoteRoot, '/stage/uploads') : !str_ends_with($remoteRoot, '/public_html/uploads'))) { throw new RuntimeException('Raiz remota invalida'); }
    $f = @ftp_connect($u['host'], (int) $u['port'], 30);
    if (!$f || !@ftp_login($f, $u['username'], $u['password'])) { throw new RuntimeException('FTP indisponivel'); }
    ftp_pasv($f, (bool) $u['passive']); $ftp[$env] = $f; $ftpRoots[$env] = $remoteRoot;
}
try {
    if ($action === '--prepare') {
        if (is_file($stateFile)) { throw new RuntimeException('Pacote existente; nao sobrescrever'); }
        if (!is_dir($dir)) { mkdir($dir, 0700, true); }
        $all = []; $categories = []; $authors = [];
        foreach ($db as $env => $pdo) {
            $all[$env] = $pdo->query('SELECT * FROM posts ORDER BY id')->fetchAll();
            $categories[$env] = $pdo->query('SELECT * FROM categoria_post ORDER BY id')->fetchAll();
            $authors[$env] = $pdo->query('SELECT id,nome FROM usuarios ORDER BY id')->fetchAll();
        }
        // Persist complete before-state before any remote mutation or final package is created.
        mergedSave($dir . '/before.json', ['posts' => $all, 'categories' => $categories]);
        $items = [];
        foreach ($oldPackage['items'] as $oldItem) {
            $slug = $oldItem['slug']; $before = [];
            foreach ($all as $env => $rows) {
                $matches = array_values(array_filter($rows, static fn(array $r): bool => $r['slug'] === $slug));
                if (count($matches) !== 1) { throw new RuntimeException('Dez artigos existentes obrigatorios'); } $before[$env] = $matches[0];
            }
            $base = $before['production'];
            $final = array_intersect_key($base, array_flip(['titulo','resumo','conteudo','imagem_capa','imagem_thumb','seo_title','seo_description']));
            $final['imagem_capa'] = $oldItem['desired']['imagem_capa']; $final['imagem_thumb'] = $oldItem['desired']['imagem_thumb'];
            $mapping = [];
            foreach ($art['mapping'] as $old => $m) { if ($m['slug'] === $slug) { $mapping[$old] = $m['new']; $mapping['uploads/posts/' . $slug . '/' . basename($old)] = $m['new']; } }
            foreach (mergedRefs($final['conteudo']) as $ref) {
                $r = mergedRelative($ref); if ($r === null) { continue; }
                if (!str_starts_with($r, 'uploads/posts/' . $slug . '/')) { throw new RuntimeException('Midia de outro artigo exige revisao'); }
                $final['conteudo'] = str_replace($ref, $mapping[$r] ?? $r, $final['conteudo']);
            }
            if (str_starts_with($slug, 'the-witcher-3-')) {
                $t = array_values(array_filter($temporal, static fn(array $r): bool => $r['before']['slug'] === $slug))[0];
                preg_match_all('~<(?:p|h2)\b[^>]*>.*?</(?:p|h2)>~s', $t['before']['conteudo'], $a);
                preg_match_all('~<(?:p|h2)\b[^>]*>.*?</(?:p|h2)>~s', $t['after']['conteudo'], $b);
                if (count($a[0]) !== 25 || count($b[0]) !== 26) { throw new RuntimeException('Revisao Witcher divergente'); }
                foreach ($a[0] as $n => $old) {
                    $new = str_replace(['melhorias no combate e movimentação melhorados', 'vai trazer, os detalhes'], ['melhorias no combate e na movimentação', 'vai trazer. Os detalhes'], $b[0][$n]);
                    if ($old !== $new) { $final['conteudo'] = mergedReplace($final['conteudo'], $old, $new); }
                }
                $final['conteudo'] .= "\r\n" . $b[0][25];
                foreach (['titulo','resumo','seo_title','seo_description'] as $field) { $final[$field] = $t['after'][$field]; }
            }
            if (str_starts_with($slug, 'windows-11-24h2-')) {
                $replacements = [
                    ['a atualização para a versão 25H2', 'uma atualização para uma versão com suporte', 2],
                    ['O caminho mais simples é atualizar para a versão seguinte do Windows 11 (25H2), que já está disponível e recebe suporte por mais tempo.', 'O caminho mais simples é instalar uma versão com suporte oferecida ao seu dispositivo pelo Windows Update. A versão 26H2 está disponível desde 29 de setembro de 2026, com distribuição gradual para dispositivos elegíveis; a 25H2 também permanece com suporte. A oferta depende da compatibilidade do PC, e não é necessário forçar uma atualização que ainda não foi disponibilizada.', 1],
                    ['Se a 25H2 aparecer disponível, instale', 'Se uma atualização de versão com suporte aparecer disponível, confira os requisitos e instale', 1],
                    ['Se o Windows Update não mostrar a 25H2', 'Se o Windows Update não oferecer uma atualização de versão', 1],
                    ['Atualização automática para a versão 25H2', 'Atualização automática e compatibilidade', 1],
                    ['A atualização para a versão 25H2 é gratuita', 'A atualização de versão oferecida pelo Windows Update é gratuita', 1],
                    ['A 25H2 não apareceu no Windows Update. O que fazer?', 'A atualização de versão não apareceu no Windows Update. O que fazer?', 1],
                    ['Microsoft Learn - status da versão 25H2', 'Microsoft Learn - distribuição e compatibilidade das atualizações', 1],
                ];
                foreach ($replacements as [$old, $new, $count]) { $final['conteudo'] = mergedReplace($final['conteudo'], $old, $new, $count); }
                $final['seo_description'] = 'O suporte ao Windows 11 24H2 Home e Pro termina em 13/10/2026. Confira sua edição e veja como instalar uma versão com suporte pelo Windows Update.';
            }
            if (str_contains($final['conteudo'], '2EjghdY') || str_contains($final['conteudo'], 'Teste Audrio') || str_contains($final['conteudo'], 'teste video')) { throw new RuntimeException('Conteudo incidental ou oferta antiga detectada'); }
            $desired = ['production' => $final];
            $stage = array_intersect_key($base, array_flip(['categoria','data_publicacao','tempo_leitura','seo_keywords','tags','destaque','status']));
            $stage = array_merge($stage, $final); if (str_starts_with($slug, 'windows-11-24h2-')) { $stage['status'] = 'rascunho'; }
            foreach (['categoria_post_id' => [$categories, 'slug'], 'autor_id' => [$authors, 'nome']] as $field => [$catalog, $identity]) {
                $source = array_values(array_filter($catalog['production'], static fn(array $r): bool => $r['id'] === $base[$field]));
                if (count($source) !== 1) { throw new RuntimeException('Identidade de origem ausente'); }
                $target = array_values(array_filter($catalog['stage'], static fn(array $r): bool => $r[$identity] === $source[0][$identity]));
                if (count($target) !== 1) { throw new RuntimeException('Identidade stage sem correspondencia unica'); } $stage[$field] = $target[0]['id'];
            }
            $stage['proximo_post_id'] = null;
            if ($base['proximo_post_id'] !== null) {
                $next = array_values(array_filter($all['production'], static fn(array $r): bool => $r['id'] === $base['proximo_post_id']));
                if (count($next) !== 1) { throw new RuntimeException('Vinculo de producao invalido'); }
                $target = array_values(array_filter($all['stage'], static fn(array $r): bool => $r['slug'] === $next[0]['slug']));
                if ($target) { $stage['proximo_post_id'] = $target[0]['id']; }
            }
            $desired['stage'] = $stage;
            $items[] = ['slug' => $slug, 'before' => $before, 'desired' => $desired];
        }
        $files = []; $originals = [];
        foreach ($items as $item) {
            $final = $item['desired']['production']; $paths = [$final['imagem_capa'], $final['imagem_thumb']];
            foreach (mergedRefs($final['conteudo']) as $ref) { $r = mergedRelative($ref); if ($r !== null && str_contains($r, '-mesa-v1-')) { $paths[] = $r; } }
            foreach (array_unique($paths) as $path) {
                // Temporal revisions change text only; each environment retains its existing cover bytes.
                if (!str_contains($path, '-mesa-v1-') && !str_contains($path, 'capa-cenario-')) { continue; }
                mergedPath($path); if (isset($files[$path])) { continue; }
                $file = $root . '/public/' . $path; if (!is_file($file)) { throw new RuntimeException('Arte local ausente'); }
                $sha = hash_file('sha256', $file); $approved = array_values(array_filter($oldPackage['files'], static fn(array $r): bool => $r['path'] === $path));
                if ($approved && $approved[0]['sha256'] !== $sha) { throw new RuntimeException('Asset homologado mudou'); }
                if (!$approved && !in_array($path, array_column($art['mapping'], 'new'), true)) { throw new RuntimeException('Arte fora da lista aprovada'); }
                $files[$path] = ['path' => $path, 'sha256' => $sha, 'size' => filesize($file)];
            }
            foreach (['stage','production'] as $env) {
                $before = $item['before'][$env]; $paths = [$before['imagem_capa'], $before['imagem_thumb']];
                foreach (mergedRefs($before['conteudo']) as $ref) { $r = mergedRelative($ref); if ($r !== null) { $paths[] = $r; } }
                foreach (array_unique($paths) as $path) {
                    if (!$path || isset($originals[$env . ':' . $path])) { continue; } mergedPath($path);
                    $remote = mergedRemote($ftpRoots[$env], $path); $exists = @ftp_size($ftp[$env], $remote) >= 0;
                    $backup = $dir . '/before-' . $env . '-' . substr(hash('sha256', $path), 0, 16) . '.bin';
                    if ($exists) { mergedGet($ftp[$env], $remote, $backup); }
                    $originals[$env . ':' . $path] = ['environment' => $env, 'path' => $path, 'existed' => $exists, 'backup' => $exists ? $backup : null, 'sha256' => $exists ? hash_file('sha256', $backup) : null];
                }
            }
        }
        mergedSave($stateFile, ['source' => 'production-editorial+approved-art', 'preparedAt' => date(DATE_ATOM), 'items' => $items, 'files' => array_values($files), 'originals' => array_values($originals), 'allBefore' => $all, 'categoriesBefore' => $categories]);
        echo 'PREPARED: 10 artigos, ' . count($files) . ' arquivos; producao como base' . PHP_EOL; exit;
    }
    $state = json_decode((string) file_get_contents($stateFile), true, 512, JSON_THROW_ON_ERROR);
    if (array_column($state['items'], 'slug') !== $slugs) { throw new RuntimeException('Pacote fora do escopo'); }
    if ($action === '--retain-existing-covers') {
        if ($stateFile !== $dir . '/package-v2.json') { throw new RuntimeException('Revisao de capas exige pacote v2'); }
        $retained = [];
        foreach ($state['items'] as $item) {
            if (!str_starts_with($item['slug'], 'windows-11-24h2-') && !str_starts_with($item['slug'], 'the-witcher-3-')) { continue; }
            foreach (['stage','production'] as $env) {
                foreach (['imagem_capa','imagem_thumb'] as $f) {
                    if ($item['desired'][$env][$f] !== $item['before'][$env][$f]) { throw new RuntimeException('Capa temporal teve alteracao nao prevista'); }
                }
            }
            $retained[] = $item['desired']['production']['imagem_capa'];
        }
        $state['files'] = array_values(array_filter($state['files'], static fn(array $f): bool => !in_array($f['path'], $retained, true)));
        if (count($retained) !== 2 || count($state['files']) !== 21) { throw new RuntimeException('Inventario de artes inesperado'); }
        $state['coversRetainedPerEnvironment'] = $retained;
        mergedSave($dir . '/package-v3.json', $state);
        echo 'PREPARED: 21 artes; capas existentes de Windows e Witcher preservadas por ambiente' . PHP_EOL; exit;
    }
    if ($action === '--stage-paths') {
        if ($stateFile !== $dir . '/package.json') { throw new RuntimeException('Revisao de caminhos ja preparada'); }
        foreach ($state['items'] as &$item) {
            $previous = $item['desired']['stage']; $html = $previous['conteudo'];
            foreach (mergedRefs($html) as $ref) {
                $r = mergedRelative($ref); if ($r === null || str_contains($r, '-mesa-v1-')) { continue; }
                if (@ftp_size($ftp['stage'], mergedRemote($ftpRoots['stage'], $r)) >= 0) { continue; }
                $candidates = array_values(array_filter($state['originals'], static fn(array $f): bool => $f['environment'] === 'stage' && $f['existed'] && basename($f['path']) === basename($r) && str_starts_with($f['path'], 'uploads/posts/' . $item['slug'] . '/')));
                if (count($candidates) !== 1) { throw new RuntimeException('Original stage sem correspondencia unica: ' . $r); }
                $html = str_replace($ref, $candidates[0]['path'], $html);
            }
            if ($html !== $previous['conteudo']) { $item['previousStageDesired'] = $previous; $item['desired']['stage']['conteudo'] = $html; }
        }
        unset($item); mergedSave($dir . '/package-v2.json', $state);
        echo 'PREPARED: referencias de stage resolvidas com originais existentes; producao preservada' . PHP_EOL; exit;
    }
    $env = str_ends_with($action, '-stage') ? 'stage' : 'production'; $pdo = $db[$env];
    $apply = str_starts_with($action, '--apply-');
    if ($env === 'production') {
        if (!is_file($dir . '/verified-stage.json')) { throw new RuntimeException('Stage ainda nao homologada'); }
        foreach ($state['items'] as $item) {
            $s = $db['stage']->prepare('SELECT * FROM posts WHERE slug=?'); $s->execute([$item['slug']]); $r = $s->fetch();
            foreach ($item['desired']['stage'] as $field => $value) { if ($r === false || $r[$field] !== $value) { throw new RuntimeException('Stage mudou apos homologacao'); } }
        }
    }
    if ($apply) {
        foreach ($state['files'] as $file) {
            $source = $root . '/public/' . $file['path'];
            if ($env === 'production') { $source = $dir . '/stage-source-' . substr(hash('sha256', $file['path']), 0, 16) . '.bin'; mergedGet($ftp['stage'], mergedRemote($ftpRoots['stage'], $file['path']), $source); }
            if (hash_file('sha256', $source) !== $file['sha256']) { throw new RuntimeException('Origem da arte divergiu'); }
            $remote = mergedRemote($ftpRoots[$env], $file['path']);
            if (@ftp_size($ftp[$env], $remote) < 0) {
                $current = $ftpRoots[$env]; foreach (explode('/', substr(dirname($file['path']), 8)) as $part) { $current .= '/' . $part; @ftp_mkdir($ftp[$env], $current); }
                if (!@ftp_put($ftp[$env], $remote, $source, FTP_BINARY)) { throw new RuntimeException('Envio de arte falhou'); }
            }
            $proof = $dir . '/sent-' . $env . '-' . substr(hash('sha256', $file['path']), 0, 16) . '.bin'; mergedGet($ftp[$env], $remote, $proof);
            if (hash_file('sha256', $proof) !== $file['sha256']) { throw new RuntimeException('Arquivo existente divergente; nao sobrescrito'); }
        }
        $pdo->beginTransaction();
        foreach ($state['items'] as $item) {
            $s = $pdo->prepare('SELECT * FROM posts WHERE slug=? FOR UPDATE'); $s->execute([$item['slug']]); $current = $s->fetch();
            if ($current === false) { throw new RuntimeException('Artigo desapareceu'); }
            $changes = []; foreach ($item['desired'][$env] as $f => $v) { if ($current[$f] !== $v) { $changes[$f] = $v; } }
            if ($changes) {
                $expected = $item['before'][$env];
                if ($env === 'stage' && isset($item['previousStageDesired'])) { $expected = array_replace($expected, $item['previousStageDesired']); }
                if (mergedScope($current) !== mergedScope($expected)) { throw new RuntimeException('Conflito editorial; nao sobrescrever'); }
                $sets = array_map(static fn(string $f): string => '`' . $f . '`=?', array_keys($changes));
                $s = $pdo->prepare('UPDATE posts SET ' . implode(',', $sets) . ' WHERE id=? AND slug=?'); $s->execute([...array_values($changes), $current['id'], $item['slug']]);
            }
        }
        $pdo->commit();
    }
    foreach ($state['items'] as $item) {
        $s = $pdo->prepare('SELECT * FROM posts WHERE slug=?'); $s->execute([$item['slug']]); $now = $s->fetch(); $old = $item['before'][$env];
        foreach ($item['desired'][$env] as $f => $v) { if ($now === false || $now[$f] !== $v) { throw new RuntimeException('Campo final divergente: ' . $f); } unset($now[$f], $old[$f]); }
        if (mergedScope($now) !== mergedScope($old)) { throw new RuntimeException('Campo fora do escopo mudou'); }
        echo 'PASS ' . $env . ' ' . $item['slug'] . PHP_EOL;
    }
    $allAfter = $pdo->query('SELECT * FROM posts ORDER BY id')->fetchAll();
    if (count($allAfter) !== count($state['allBefore'][$env])) { throw new RuntimeException('Quantidade de artigos mudou'); }
    foreach ($state['allBefore'][$env] as $old) {
        if (in_array($old['slug'], $slugs, true)) { continue; }
        $now = array_values(array_filter($allAfter, static fn(array $r): bool => $r['id'] === $old['id']));
        if (count($now) !== 1 || mergedScope($now[0]) !== mergedScope($old)) { throw new RuntimeException('Outro artigo alterado'); }
    }
    if ($pdo->query('SELECT * FROM categoria_post ORDER BY id')->fetchAll() !== $state['categoriesBefore'][$env]) { throw new RuntimeException('Categorias alteradas'); }
    foreach ($state['files'] as $file) {
        $proof = $dir . '/check-' . $env . '-' . substr(hash('sha256', $file['path']), 0, 16) . '.bin'; mergedGet($ftp[$env], mergedRemote($ftpRoots[$env], $file['path']), $proof);
        if (hash_file('sha256', $proof) !== $file['sha256']) { throw new RuntimeException('SHA final divergente'); }
    }
    foreach ($state['originals'] as $file) {
        if ($file['environment'] !== $env || !$file['existed']) { continue; }
        $proof = $dir . '/old-check-' . $env . '-' . substr(hash('sha256', $file['path']), 0, 16) . '.bin'; mergedGet($ftp[$env], mergedRemote($ftpRoots[$env], $file['path']), $proof);
        if (hash_file('sha256', $proof) !== $file['sha256']) { throw new RuntimeException('Original alterado'); }
    }
    file_put_contents($dir . '/verified-' . $env . '.json', json_encode(['environment' => $env, 'verifiedAt' => date(DATE_ATOM), 'articles' => 10, 'assets' => count($state['files']), 'outsideScopePreserved' => true, 'originalsPreserved' => true], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo 'PASS: dez artigos, arquivos integros, originais e dados restantes preservados' . PHP_EOL;
} catch (Throwable $e) {
    foreach ($db as $pdo) { if ($pdo->inTransaction()) { $pdo->rollBack(); } } throw $e;
} finally { foreach ($ftp as $f) { ftp_close($f); } }
