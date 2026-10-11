<?php
declare(strict_types=1);

// Stage-only writes; production is the editorial reference, local supplies four new posts.
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Repositories\PostRepository;
use App\Support\TargetEnvironmentDatabase;
use App\Support\TargetEnvironmentUploads;

$action=$argv[1] ?? '--inspect';
if (!in_array($action,['--inspect','--prepare','--apply','--verify'],true)) { throw new RuntimeException('Ação inválida.'); }
$stage=TargetEnvironmentDatabase::pdo('stage');
$production=TargetEnvironmentDatabase::pdo('production');
$local=$GLOBALS['pdo'];
$dir=base_path('storage/stage-content/alignment-20261011');
$packagePath=$dir.'/package.json';
$localSlugs=[
    'muito-alem-dos-jumpscares-3-jogos-que-transformam-a-atmosfera-em-verdadeiro-terror',
    'como-o-som-cria-medo-nos-jogos-a-ciencia-por-tras-da-atmosfera-de-terror',
    'jogos-para-quem-so-tem-30-minutos-por-dia',
    'como-voltar-a-um-jogo-depois-de-meses-sem-jogar',
];
$fields=['titulo','slug','resumo','conteudo','categoria','categoria_post_id','imagem_capa','imagem_thumb','autor_id','data_publicacao','tempo_leitura','seo_title','seo_description','seo_keywords','tags','status','destaque','tipo_post','proximo_post_id'];
function alignmentRows(PDO $pdo): array { return $pdo->query('SELECT * FROM posts ORDER BY id')->fetchAll(PDO::FETCH_ASSOC); }
function alignmentFreshDatabase(string $environment): PDO {
    $database=TargetEnvironmentDatabase::profile($environment)['database'];
    return new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',$database['host'],$database['port'],$database['database']),
        $database['username'],$database['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
}
function alignmentPath(string $reference): ?string {
    if (preg_match('~(?:^|/)(uploads/posts/[a-z0-9-]+/[a-zA-Z0-9_./-]+\.(?:webp|png|jpg|jpeg|gif|mp4|svg))(?:\?.*)?$~',$reference,$matches)!==1) { return null; }
    if (str_contains($matches[1],'..')) { throw new RuntimeException('Caminho inseguro.'); }
    return $matches[1];
}
function alignmentReferences(array $post): array {
    preg_match_all('~(?:src|data-src|poster)=["\x27]([^"\x27]+)["\x27]~i',(string)$post['conteudo'],$matches);
    return array_values(array_unique(array_filter([$post['imagem_capa'],$post['imagem_thumb'],...$matches[1]])));
}
function alignmentJson(string $path,array $value): void {
    $bytes=json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $handle=fopen($path,'x'); if ($handle===false) { throw new RuntimeException('Arquivo de evidência já existe.'); }
    try { if (fwrite($handle,$bytes)!==strlen($bytes) || !fflush($handle)) { throw new RuntimeException('Evidência incompleta.'); } } finally { fclose($handle); }
}
function alignmentFtp(string $environment): array {
    $profile=TargetEnvironmentDatabase::profile($environment)['uploads'];
    $root='/'.trim((string)$profile['root'],'/');
    if (($profile['mode']??'')!=='ftp' || str_contains($root,'..') || !str_ends_with($root,'/uploads')
        || ($environment==='stage' && !str_ends_with($root,'/stage/uploads'))) { throw new RuntimeException('Destino FTP inválido.'); }
    $ftp=ftp_connect($profile['host'],(int)$profile['port'],30);
    if ($ftp===false || !ftp_login($ftp,$profile['username'],$profile['password'])) { throw new RuntimeException('FTP indisponível.'); }
    ftp_pasv($ftp,(bool)$profile['passive']);
    return [$ftp,$root];
}
function alignmentBytes(FTP\Connection $ftp,string $root,string $path): ?string {
    if (alignmentPath($path)!==$path) { throw new RuntimeException('Asset fora de uploads/posts.'); }
    $remote=$root.'/'.substr($path,8);
    if (@ftp_size($ftp,$remote)<0) { return null; }
    $stream=fopen('php://temp','w+');
    if ($stream===false || !ftp_fget($ftp,$stream,$remote,FTP_BINARY)) { throw new RuntimeException('Não foi possível ler asset remoto.'); }
    rewind($stream);$bytes=stream_get_contents($stream);fclose($stream);
    if ($bytes===false || $bytes==='') { throw new RuntimeException('Asset remoto vazio.'); }
    return $bytes;
}
function alignmentSame(array $row,array $desired): bool {
    foreach ($desired as $field=>$value) { if (($row[$field]??null)!=$value) { return false; } }
    return true;
}
$sourceRows=alignmentRows($production);$localRows=alignmentRows($local);$stageRows=alignmentRows($stage);
$sources=[];
foreach ($sourceRows as $row) { $sources[$row['slug']]=['environment'=>'production','row'=>$row]; }
foreach ($localRows as $row) { if (in_array($row['slug'],$localSlugs,true)) {
    if (isset($sources[$row['slug']])) { throw new RuntimeException('Novo artigo já existe em produção; revisar pacote.'); }
    $sources[$row['slug']]=['environment'=>'local','row'=>$row];
} }
if (count($sourceRows)!==33 || count($sources)!==37) { throw new RuntimeException('Inventário mudou; revisar antes de aplicar.'); }
if ($action==='--inspect') {
    echo json_encode(['production'=>count($sourceRows),'stage'=>count($stageRows),'newLocal'=>4,'stageHasType'=>in_array('tipo_post',array_column($stage->query('SHOW COLUMNS FROM posts')->fetchAll(PDO::FETCH_ASSOC),'Field'),true)],JSON_THROW_ON_ERROR).PHP_EOL;exit;
}
$sourceFingerprint=[];
foreach ($sources as $slug=>$source) { $sourceFingerprint[$slug]=hash('sha256',json_encode(array_intersect_key($source['row'],array_flip($fields)),JSON_THROW_ON_ERROR)); }
[$ftp,$ftpRoot]=alignmentFtp('stage');
try {
    if ($action==='--prepare') {
        if (is_file($packagePath)) { throw new RuntimeException('Pacote já preparado; preservar backup.'); }
        if (!is_dir($dir) && !mkdir($dir,0700,true)) { throw new RuntimeException('Pasta de backup indisponível.'); }
        $before=['posts'=>$stageRows,'categories'=>$stage->query('SELECT * FROM categoria_post ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
            'schema'=>$stage->query('SHOW CREATE TABLE posts')->fetch(PDO::FETCH_NUM)[1]];
        $categoryMaps=[];$authorMaps=[];
        foreach (['production'=>$production,'local'=>$local,'stage'=>$stage] as $env=>$db) {
            $categoryMaps[$env]=array_column($db->query('SELECT id,slug FROM categoria_post')->fetchAll(PDO::FETCH_ASSOC),'slug','id');
            $authorMaps[$env]=array_column($db->query('SELECT id,nome FROM autores')->fetchAll(PDO::FETCH_ASSOC),'nome','id');
        }
        $stageCategories=array_flip($categoryMaps['stage']);$stageAuthors=array_flip($authorMaps['stage']);
        $stageBySlug=array_column($stageRows,null,'slug');$sourceById=[];
        foreach (['production'=>$sourceRows,'local'=>$localRows] as $env=>$rows) { $sourceById[$env]=array_column($rows,'slug','id'); }
        $assets=[];$items=[];$originals=[];
        [$prodFtp,$prodRoot]=alignmentFtp('production');
        try {
            foreach ($sources as $slug=>$source) {
                $env=$source['environment'];$row=$source['row'];
                $desired=array_intersect_key($row,array_flip($fields));
                $categorySlug=$categoryMaps[$env][(int)$row['categoria_post_id']]??null;
                $authorName=$authorMaps[$env][(int)$row['autor_id']]??null;
                if ($categorySlug===null || !isset($stageCategories[$categorySlug]) || $authorName===null || !isset($stageAuthors[$authorName])) { throw new RuntimeException('Categoria/autor sem correspondente.'); }
                $desired['categoria_post_id']=(int)$stageCategories[$categorySlug];$desired['autor_id']=(int)$stageAuthors[$authorName];
                $nextId=(int)($row['proximo_post_id']??0);$nextSlug=$nextId>0?($sourceById[$env][$nextId]??null):null;
                if ($nextId>0 && ($nextSlug===null || !isset($sources[$nextSlug]))) { throw new RuntimeException('Próximo post sem origem válida.'); }
                $desired['proximo_post_id']=null;
                foreach (alignmentReferences($row) as $reference) {
                    $path=alignmentPath($reference);if ($path===null) { continue; }
                    $key=$env.':'.$path;
                    if (!isset($assets[$key])) {
                        $bytes=$env==='production'?alignmentBytes($prodFtp,$prodRoot,$path):(is_file(base_path('public/'.$path))?file_get_contents(base_path('public/'.$path)):null);
                        if (!is_string($bytes) || $bytes==='') { throw new RuntimeException('Imagem de origem ausente: '.$path); }
                        $hash=hash('sha256',$bytes);$old=alignmentBytes($ftp,$ftpRoot,$path);$destination=$path;
                        if ($old!==null && hash('sha256',$old)!==$hash) {
                            $info=pathinfo($path);$destination=$info['dirname'].'/'.$info['filename'].'-stage-'.substr($hash,0,12).'.'.$info['extension'];
                            $backup=$dir.'/original-'.hash('sha256',$path).'.bin';
                            if (file_put_contents($backup,$old)!==strlen($old)) { throw new RuntimeException('Backup de imagem incompleto.'); }
                            $originals[$path]=['sha256'=>hash('sha256',$old),'backup'=>basename($backup)];
                        }
                        $current=alignmentBytes($ftp,$ftpRoot,$destination);
                        if ($current!==null && hash('sha256',$current)!==$hash) { throw new RuntimeException('Colisão de asset versionado.'); }
                        $localFile=$dir.'/asset-'.$hash.'.bin';
                        if (!is_file($localFile) && file_put_contents($localFile,$bytes)!==strlen($bytes)) { throw new RuntimeException('Pacote de mídia incompleto.'); }
                        $assets[$key]=['path'=>$destination,'sha256'=>$hash,'file'=>basename($localFile),'exists'=>$current!==null];
                        if (count($assets)%20===0) { echo 'Preparados '.count($assets)." assets.\n"; }
                    }
                    $target=$assets[$key]['path'];
                    foreach (['imagem_capa','imagem_thumb'] as $field) { if ($desired[$field]===$reference) { $desired[$field]=$target; } }
                    $desired['conteudo']=str_replace($reference,$target,$desired['conteudo']);
                }
                $items[$slug]=['source'=>$env,'desired'=>$desired,'nextSlug'=>$nextSlug,'before'=>$stageBySlug[$slug]??null];
            }
        } finally { ftp_close($prodFtp); }
        alignmentJson($packagePath,['environment'=>'stage','sourceFingerprint'=>$sourceFingerprint,'before'=>$before,'items'=>$items,'assets'=>array_values($assets),'originals'=>$originals,'preparedAt'=>date('c')]);
        echo 'PASS: pacote com 37 artigos, '.count($assets)." assets; backup anterior preservado.\n";exit;
    }
    $package=json_decode((string)file_get_contents($packagePath),true,512,JSON_THROW_ON_ERROR);
    if ($package['environment']!=='stage' || $package['sourceFingerprint']!==$sourceFingerprint) { throw new RuntimeException('Origem mudou desde preparação.'); }
    if ($action==='--apply') {
        if (!in_array('tipo_post',array_column($stage->query('SHOW COLUMNS FROM posts')->fetchAll(PDO::FETCH_ASSOC),'Field'),true)) { throw new RuntimeException('Aplicar somente a migration 0006 em stage antes do conteúdo.'); }
        foreach ($package['assets'] as $asset) {
            $file=$dir.'/'.$asset['file'];if (hash_file('sha256',$file)!==$asset['sha256']) { throw new RuntimeException('Pacote de asset divergente.'); }
            $current=alignmentBytes($ftp,$ftpRoot,$asset['path']);
            if ($current===null) { TargetEnvironmentUploads::putFile('stage',$file,$asset['path']);$current=alignmentBytes($ftp,$ftpRoot,$asset['path']); }
            if ($current===null || hash('sha256',$current)!==$asset['sha256']) { throw new RuntimeException('Asset remoto divergente; não sobrescrito.'); }
        }
        // FTP may exceed the remote idle timeout. Reconnect before the transaction,
        // and recheck origins so an edit during upload cannot be silently replaced.
        $freshFingerprint=[];
        foreach (['production'=>alignmentFreshDatabase('production'),'local'=>$local] as $env=>$db) {
            foreach (alignmentRows($db) as $row) {
                if ($env==='local' && !in_array($row['slug'],$localSlugs,true)) { continue; }
                $freshFingerprint[$row['slug']]=hash('sha256',json_encode(array_intersect_key($row,array_flip($fields)),JSON_THROW_ON_ERROR));
            }
        }
        if ($freshFingerprint!==$package['sourceFingerprint']) { throw new RuntimeException('Origem mudou durante envio de imagens.'); }
        $stage=alignmentFreshDatabase('stage');
        $repo=new PostRepository($stage);$stage->beginTransaction();
        try {
            $stage->query('SELECT id FROM posts ORDER BY id FOR UPDATE')->fetchAll();
            $currentRows=alignmentRows($stage);$currentBySlug=array_column($currentRows,null,'slug');$ids=array_column($currentRows,'id','slug');
            foreach ($package['items'] as $slug=>$item) {
                $current=$currentBySlug[$slug]??null;
                $desired=$item['desired'];unset($desired['proximo_post_id']);
                if ($current!==null && !alignmentSame($current,$desired)) {
                    $before=$item['before'];if ($before===null) { throw new RuntimeException('Artigo novo já existe com outra versão.'); }
                    $compare=$current;unset($compare['tipo_post']);$old=$before;unset($old['tipo_post']);
                    foreach (['views','curtidas','likes_count','comentarios_count','data_atualizacao'] as $field) { unset($compare[$field],$old[$field]); }
                    if ($compare!==$old) { throw new RuntimeException('Edição concorrente em stage.'); }
                }
                if ($current===null) { $data=$item['desired'];$ids[$slug]=$repo->insertAdmin($data); }
            }
            foreach ($package['items'] as $slug=>$item) {
                $desired=$item['desired'];$desired['proximo_post_id']=$item['nextSlug']===null?null:(int)$ids[$item['nextSlug']];
                $current=$repo->findAdminById((int)$ids[$slug]);if ($current===null) { throw new RuntimeException('Post desapareceu.'); }
                if (!alignmentSame($current,$desired)) { $repo->updateAdmin((int)$ids[$slug],$desired); }
            }
            $after=alignmentRows($stage);$afterBySlug=array_column($after,null,'slug');
            foreach ($currentRows as $old) {
                $now=$afterBySlug[$old['slug']];$allowed=isset($package['items'][$old['slug']])?[...$fields,'data_atualizacao']:[];
                foreach ($old as $field=>$value) { if (!in_array($field,$allowed,true) && $now[$field]!==$value) { throw new RuntimeException('Campo operacional alterado.'); } }
            }
            if ($stage->query('SELECT * FROM categoria_post ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)!==$package['before']['categories']) { throw new RuntimeException('Categorias foram alteradas.'); }
            $stage->commit();
            file_put_contents($dir.'/applied.json',json_encode(['ids'=>$ids,'appliedAt'=>date('c')],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
            echo "PASS: conteúdo aplicado somente em stage, métricas e categorias preservadas.\n";
        } catch (Throwable $e) { if ($stage->inTransaction()) { $stage->rollBack(); } throw $e; }
    }
    $rows=alignmentRows($stage);$bySlug=array_column($rows,null,'slug');$ids=array_column($rows,'id','slug');
    foreach ($package['items'] as $slug=>$item) {
        $desired=$item['desired'];$desired['proximo_post_id']=$item['nextSlug']===null?null:(int)$ids[$item['nextSlug']];
        if (!isset($bySlug[$slug]) || !alignmentSame($bySlug[$slug],$desired)) { throw new RuntimeException('Conteúdo final divergente: '.$slug); }
    }
    foreach ($package['assets'] as $asset) { $bytes=alignmentBytes($ftp,$ftpRoot,$asset['path']);if ($bytes===null || hash('sha256',$bytes)!==$asset['sha256']) { throw new RuntimeException('Hash remoto divergente.'); } }
    foreach ($package['originals'] as $path=>$asset) { $bytes=alignmentBytes($ftp,$ftpRoot,$path);if ($bytes===null || hash('sha256',$bytes)!==$asset['sha256']) { throw new RuntimeException('Imagem antiga alterada.'); } }
    file_put_contents($dir.'/verified.json',json_encode(['articles'=>count($package['items']),'assets'=>count($package['assets']),'sourceFingerprintPreserved'=>true,'verifiedAt'=>date('c')],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo 'PASS: 37 artigos alinhados; '.count($package['assets'])." hashes remotos e originais conferidos.\n";
} finally { ftp_close($ftp); }
