<?php
declare(strict_types=1);
use App\Services\Instagram\{EditorialMotionReelRenderer,KonvaReelRenderer,KonvaReelBatchService,MotionReelBatchService};
use App\Support\TargetEnvironmentDatabase;
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
date_default_timezone_set('America/Sao_Paulo');$root=dirname(__DIR__);
require_once $root.'/app/Support/Helpers.php';
spl_autoload_register(static function(string $class)use($root):void{if(str_starts_with($class,'App\\')){$path=$root.'/app/'.str_replace('\\','/',substr($class,4)).'.php';if(is_file($path))require_once $path;}});
$options=getopt('',['inventory','prepare','validate','apply','rollback','verify','manifest:','approved-manifest:','publisher-paused']);
$lock=null;$local=null;$production=null;
try{
    $modes=array_values(array_intersect(['inventory','prepare','validate','apply','rollback','verify'],array_keys($options)));
    if(count($modes)!==1)throw new RuntimeException('Escolha um modo: inventory, prepare, validate, apply, rollback ou verify.');
    $mode=$modes[0];
    foreach(file($root.'/.env',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) ?: [] as $line){if(preg_match('/^((?:DB_|CONTENT_SYNC_PRODUCTION_DB_|BACKUP_PRODUCTION_DB_)[A-Z_]+)=(.*)$/',trim($line),$m))$_ENV[$m[1]]=trim($m[2]," \t\"'");}
    $config=require $root.'/config/content-sync.php';$db=$config['profiles']['local']['database'];
    $GLOBALS['config']=['app'=>['env'=>'local'],'content_sync'=>$config];
    if(!in_array($db['host'],['localhost','127.0.0.1','::1'],true))throw new RuntimeException('Instagram exige banco local.');
    $local=new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',$db['host'],$db['port'],$db['database']),$db['username'],$db['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $GLOBALS['pdo']=$local;
    $media=new EditorialMotionReelRenderer();$guard=new MotionReelBatchService($local,$media,$root);
    $batch=new KonvaReelBatchService($local,$guard,new KonvaReelRenderer($root),$media,$root);
    if(in_array($mode,['inventory','prepare','validate','apply'],true)){
        $production=TargetEnvironmentDatabase::pdo('production');$production->exec('SET SESSION TRANSACTION READ ONLY');
        echo 'Limite de sessão da origem: '.$production->query('SELECT @@wait_timeout')->fetchColumn()."s\n";
    }
    if($mode==='inventory'){
        $items=$batch->inventory($production);$run='batch-'.date('Ymd-His').'-'.bin2hex(random_bytes(4));
        $dir=$root.'/storage/backups/reels-konva/'.$run;
        if(!mkdir($dir,0755,true))throw new RuntimeException('Pasta de backup não criada.');
        $manifest=['run'=>$run,'version'=>$batch->version(),'items'=>$items];
        MotionReelBatchService::save($dir.'/manifest.json',$manifest);
        MotionReelBatchService::save($dir.'/all-records-before.json',['posts'=>$local->query('SELECT * FROM instagram_posts ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),'media'=>$local->query('SELECT * FROM instagram_post_media ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)]);
        echo 'MANIFEST: '.$dir.'/manifest.json'."\n";
        foreach($items as $item)echo '#'.$item['id'].' '.$item['article']['categoria'].' '.$item['duration'].'s início '.$item['audio_start']."\n";
        exit;
    }
    $path=realpath((string) ($options['manifest'] ?? ''));$base=realpath($root.'/storage/backups/reels-konva');
    if($path===false || $base===false || basename($path)!=='manifest.json' || !str_starts_with(str_replace('\\','/',$path),str_replace('\\','/',$base).'/'))throw new RuntimeException('Manifesto fora dos backups Konva.');
    $lock=fopen(dirname($path).'/batch.lock','c');if($lock===false || !flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Lote já está em execução.');
    $manifest=json_decode((string) file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    if(!is_array($manifest) || $manifest['version']!==$batch->version())throw new RuntimeException('Código ou fontes mudaram desde o inventário.');
    if($mode==='prepare'){
        foreach($manifest['items'] as &$item){$item=$batch->prepare($production,$item,(string) $manifest['run']);MotionReelBatchService::save($path,$manifest);echo 'PRONTO #'.$item['id'].' '.$item['video']."\n";flush();}unset($item);
    }elseif($mode==='validate'){
        foreach($manifest['items'] as $item){$batch->validate($production,$item);echo 'VALIDADO #'.$item['id']."\n";}
        echo 'HASH: '.hash_file('sha256',$path)."\n";
    }elseif($mode==='verify'){
        $journal=json_decode((string) file_get_contents(dirname($path).'/application.json'),true,512,JSON_THROW_ON_ERROR);
        foreach($journal['items'] as $entry){if($guard->snapshot((int) $entry['id'])!==$entry['after'])throw new RuntimeException('Post diverge do resultado #'.$entry['id']);}
        $before=json_decode((string) file_get_contents(dirname($path).'/all-records-before.json'),true,512,JSON_THROW_ON_ERROR);
        $protected=array_values(array_filter($before['posts'],static fn(array $p):bool=>!in_array((int) $p['id'],KonvaReelBatchService::IDS,true)));
        $current=$local->query('SELECT * FROM instagram_posts ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        if(count($current)!==count($before['posts']) || array_values(array_filter($current,static fn(array $p):bool=>!in_array((int) $p['id'],KonvaReelBatchService::IDS,true)))!==$protected)throw new RuntimeException('Registros externos ao lote mudaram.');
        $currentMedia=$local->query('SELECT * FROM instagram_post_media ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $external=static fn(array $m):bool=>!in_array((int) $m['post_id'],KonvaReelBatchService::IDS,true);
        if(count($currentMedia)!==count($before['media']) || array_values(array_filter($currentMedia,$external))!==array_values(array_filter($before['media'],$external)))throw new RuntimeException('Mídias externas ao lote mudaram.');
        echo "26 Reels conferidos; registros externos e calendário preservados.\n";
    }else{
        if(!isset($options['publisher-paused']) || !hash_equals((string) hash_file('sha256',$path),(string) ($options['approved-manifest'] ?? '')))throw new RuntimeException('Exige publicador pausado/sem processo ativo e hash do manifesto validado.');
        $journalPath=dirname($path).'/application.json';
        if($mode==='apply'){
            if(is_file($journalPath))throw new RuntimeException('Journal existente: use verify para conferir o commit ou rollback; não reaplique.');
            $batch->apply($production,$manifest,$journalPath);echo "26 Reels aplicados atomicamente.\n";
        }else{
            $journal=json_decode((string) file_get_contents($journalPath),true,512,JSON_THROW_ON_ERROR);$batch->rollback($journal);$journal['state']='rolled_back';MotionReelBatchService::save($journalPath,$journal);echo "Referências anteriores recuperadas.\n";
        }
    }
}catch(Throwable $e){if($local instanceof PDO && $local->inTransaction())$local->rollBack();fwrite(STDERR,$e instanceof PDOException?'Falha de banco (SQLSTATE '.$e->getCode().', código '.($e->errorInfo[1] ?? 'conexão')."); credenciais omitidas.\n":$e->getMessage()."\n");exit(1);}
finally{if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}}
