<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/backup/EnvLoader.php';
use Scripts\Backup\EnvLoader;
$root = dirname(__DIR__, 2);
EnvLoader::load($root . '/.env');
$config = require $root . '/config/content-sync.php';
$d = $config['profiles']['local']['database'];
$pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $d['host'], $d['port'], $d['database']), $d['username'], $d['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$assets = $root . '/resources/reels-review/assets/diablo';
$manifest = json_decode((string)file_get_contents($assets . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$dir = $root . '/storage/previews/diablo-artes-20261008';
$backup = $dir . '/blog-local-backup.json';
$mapping = [];
foreach ($manifest['entries'] as $entry) {
    $file = $assets . '/' . $entry['file'];
    if (hash_file('sha256', $file) !== $entry['sha256']) { throw new RuntimeException('Asset divergente'); }
    foreach ($entry['uses'] as $use) {
        $old = $use['path'];
        if (!preg_match('~^uploads/posts/([a-z0-9-]+)/images/[a-z0-9.-]+\.webp$~', $old, $match)) { throw new RuntimeException('Referencia insegura'); }
        $mapping[$old] = ['slug'=>$match[1], 'new'=>dirname($old) . '/' . $entry['key'] . '-mesa-v1-' . substr($entry['sha256'],0,12) . '.webp', 'file'=>$file, 'sha256'=>$entry['sha256']];
    }
}
$slugs = array_values(array_unique(array_column($mapping, 'slug')));
if (count($slugs) !== 6) { throw new RuntimeException('Escopo exige seis artigos'); }
$query = $pdo->prepare('SELECT * FROM posts WHERE slug IN (' . implode(',', array_fill(0,6,'?')) . ') ORDER BY slug');
$query->execute($slugs); $rows = $query->fetchAll();
if (count($rows) !== 6) { throw new RuntimeException('Os seis artigos devem existir localmente; nenhum sera criado'); }
$action = $argv[1] ?? '--inspect';
if (!in_array($action, ['--inspect','--apply','--verify','--rollback'], true)) { throw new RuntimeException('Acao invalida'); }
$save = static function(string $path, array $data): void { if (file_exists($path)) { throw new RuntimeException('Evidencia existente'); } file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)); };
$transform = static function(array $row) use($mapping): array {
    $oldCover=$row['imagem_capa'];
    foreach ($mapping as $old=>$map) {
        if ($row['slug'] !== $map['slug']) { continue; }
        $row['conteudo'] = str_replace(['https://estrategianerd.com.br/' . $old, 'http://estrategianerd.com.br/' . $old, $old], $map['new'], $row['conteudo']);
        foreach (['imagem_capa','imagem_thumb'] as $field) { if ($row[$field] === $old) { $row[$field] = $map['new']; } }
    }
    // These two older covers were not used as Reel scenes. Use the approved
    // opening illustration of each article, rather than generating another asset.
    $opening=[
        'o-mundo-de-diablo-entenda-santuario-ceu-e-inferno'=>'img-005.webp',
        'a-lore-completa-de-diablo-entenda-toda-a-historia-do-universo'=>'img-001.webp',
    ];
    if(isset($opening[$row['slug']])) {
        $path='uploads/posts/' . $row['slug'] . '/images/' . $opening[$row['slug']];
        $row['imagem_capa']=$mapping[$path]['new'];
    }
    if($row['imagem_capa']!==$oldCover) { $row['imagem_thumb']=$row['imagem_capa']; }
    return $row;
};
if ($action === '--inspect') {
    $summary=[];
    foreach($rows as $row) {
        $new=$transform($row);
        $summary[]=['id'=>$row['id'],'slug'=>$row['slug'],'contentChanged'=>$new['conteudo']!==$row['conteudo'],'coverChanged'=>$new['imagem_capa']!==$row['imagem_capa'],'thumbChanged'=>$new['imagem_thumb']!==$row['imagem_thumb']];
    }
    $save($backup, ['rows'=>$rows,'mapping'=>$mapping,'allReferences'=>$pdo->query('SELECT id,slug,conteudo,imagem_capa,imagem_thumb FROM posts ORDER BY id')->fetchAll()]);
    echo json_encode($summary, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit;
}
$state=json_decode((string)file_get_contents($backup),true,512,JSON_THROW_ON_ERROR);
if ($mapping!==$state['mapping']) { throw new RuntimeException('Mapeamento mudou desde backup'); }
try {
    $pdo->beginTransaction();
    $lock=$pdo->prepare('SELECT * FROM posts WHERE slug IN (' . implode(',',array_fill(0,6,'?')) . ') ORDER BY slug FOR UPDATE');
    $lock->execute($slugs);$current=$lock->fetchAll();
    if(count($current)!==6)throw new RuntimeException('Conjunto de artigos mudou');
    foreach($state['rows'] as $i=>$before) {
        $after=$transform($before);$row=$current[$i];
        // Protect only the fields this operation writes. Normal page views may
        // change counters after validation and must not prevent safe rollback.
        $scope=static fn(array $r): array=>array_intersect_key($r,array_flip(['id','slug','conteudo','imagem_capa','imagem_thumb']));
        $a=$scope($row);$b=$scope($before);$c=$scope($after);
        if($a!==$b && $a!==$c) { throw new RuntimeException('Artigo mudou; nao sobrescrever'); }
        if($action==='--verify' && $a!==$c) { throw new RuntimeException('Referencias nao aplicadas'); }
        if($action==='--apply' || $action==='--rollback') {
            $target=$action==='--apply'?$after:$before;
            if($action==='--apply')foreach($mapping as $old=>$map) {
                if($map['slug']!==$before['slug'])continue;
                $dest=$root . '/public/' . $map['new'];
                if(!file_exists($dest) && !copy($map['file'],$dest))throw new RuntimeException('Falha no asset');
                if(hash_file('sha256',$dest)!==$map['sha256'])throw new RuntimeException('Asset copiado divergente');
            }
            $update=$pdo->prepare('UPDATE posts SET conteudo=?,imagem_capa=?,imagem_thumb=? WHERE id=? AND slug=?');
            $update->execute([$target['conteudo'],$target['imagem_capa'],$target['imagem_thumb'],$before['id'],$before['slug']]);
            $check=$pdo->prepare('SELECT * FROM posts WHERE id=?');$check->execute([$before['id']]);$saved=$check->fetch();
            $expected=array_replace($row,['conteudo'=>$target['conteudo'],'imagem_capa'=>$target['imagem_capa'],'imagem_thumb'=>$target['imagem_thumb']]);
            unset($saved['data_atualizacao'],$expected['data_atualizacao']);
            if($saved!==$expected)throw new RuntimeException('Campo fora do escopo foi alterado');
        }
    }
    $selected=array_column($state['rows'],'id');
    foreach($state['allReferences'] as $before) {
        if(in_array($before['id'],$selected,true))continue;
        $q=$pdo->prepare('SELECT id,slug,conteudo,imagem_capa,imagem_thumb FROM posts WHERE id=?');$q->execute([$before['id']]);
        if($q->fetch()!==$before)throw new RuntimeException('Referencia de outro artigo mudou');
    }
    $pdo->commit();
    if($action==='--verify') {
        $proof=['articles'=>array_column($rows,'id'),'references'=>count($mapping),'covers'=>6,'otherArticlesUnchanged'=>true,'newImageUrls'=>'relative-local'];
        $proofPath=$dir . '/blog-local-verified-v3.json';
        if(file_exists($proofPath)) { if(json_decode((string)file_get_contents($proofPath),true,512,JSON_THROW_ON_ERROR)!==$proof)throw new RuntimeException('Evidencia divergente'); }
        else { $save($proofPath,$proof); }
    }
    echo strtoupper($action) . ': seis artigos locais; demais campos e artigos preservados' . PHP_EOL;
} catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
