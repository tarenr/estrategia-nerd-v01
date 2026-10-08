<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/backup/EnvLoader.php';
Scripts\Backup\EnvLoader::load(dirname(__DIR__, 2) . '/.env');
$root = dirname(__DIR__, 2);
$config = require $root . '/config/content-sync.php';
// This entry point deliberately cannot select a production destination.
$config['profiles'] = array_intersect_key($config['profiles'], array_flip(['local','stage']));
function stagePdo(array $d): PDO {
    return new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',$d['host'],$d['port'],$d['database']),$d['username'],$d['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
}
$local = stagePdo($config['profiles']['local']['database']);
$stage = stagePdo($config['profiles']['stage']['database']);
$diablo = json_decode((string) file_get_contents($root . '/storage/previews/diablo-artes-20261008/blog-local-backup.json'),true,512,JSON_THROW_ON_ERROR);
$hardware = json_decode((string) file_get_contents($root . '/storage/previews/reels-review/hardware-samples-20261008/blog-backup.json'),true,512,JSON_THROW_ON_ERROR);
$temporal = json_decode((string) file_get_contents($root . '/storage/previews/reels-review/temporal-20261008/backup.json'),true,512,JSON_THROW_ON_ERROR);
$slugs = array_merge(array_column($diablo['rows'],'slug'),array_column(array_column($hardware,'row'),'slug'),array_column(array_column($temporal,'before'),'slug'));
if(count($slugs)!==10||count(array_unique($slugs))!==10){throw new RuntimeException('Escopo exige dez artigos');}
function selectedStageRows(PDO $p,array $slugs):array {
    $s=$p->prepare('SELECT * FROM posts WHERE slug IN ('.implode(',',array_fill(0,count($slugs),'?')).') ORDER BY slug');$s->execute($slugs);return $s->fetchAll();
}
$localRows=selectedStageRows($local,$slugs);$stageRows=selectedStageRows($stage,$slugs);
if(($argv[1]??'')==='--inspect'){
    $summary=[];
    foreach($localRows as $row){$matches=array_values(array_filter($stageRows,static fn(array $r):bool=>$r['slug']===$row['slug']));$s=$matches[0]??null;$summary[]=['slug'=>$row['slug'],'localId'=>$row['id'],'stageId'=>$s['id']??null,'localStatus'=>$row['status'],'stageStatus'=>$s['status']??null,'stageTitle'=>$s['titulo']??null,'contentEqual'=>$s!==null&&$s['conteudo']===$row['conteudo'],'stageCover'=>$s['imagem_capa']??null];}
    echo json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
    foreach($temporal as $item){$rows=array_values(array_filter($stageRows,static fn(array $r):bool=>$r['slug']===$item['before']['slug']));if($rows){$diff=[];foreach($item['after'] as $field=>$value){$desired=$field==='conteudo'?str_replace('\\n<p><b>Fontes oficiais:','<p><b>Fontes oficiais:',$value):$value;$diff[$field]=['equalsBefore'=>$rows[0][$field]===$item['before'][$field],'equalsAfter'=>$rows[0][$field]===$desired];}echo json_encode(['temporal'=>$item['before']['slug'],'comparison'=>$diff],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;}}
    foreach(['categoria_post','categorias','tags','post_tags'] as $table){try{$cols=$stage->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll();echo json_encode(['table'=>$table,'columns'=>array_column($cols,'Field')],JSON_THROW_ON_ERROR).PHP_EOL;}catch(PDOException){echo 'Tabela ausente: '.$table.PHP_EOL;}}
    exit;
}
$action=$argv[1]??'';
if(!in_array($action,['--prepare','--apply','--verify'],true)){throw new RuntimeException('Use --inspect|--prepare|--apply|--verify (somente stage)');}
$dir=$root.'/storage/stage-content/stage-20261008-v2';
if(!is_dir($dir)){mkdir($dir,0755,true);}
$stateFile=$dir.'/package.json';
function stageSave(string $file,array $data):void {
    $h=fopen($file,'x');if(!$h){throw new RuntimeException('Backup ou pacote existente');}fwrite($h,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));fclose($h);
}
function stagePath(string $path):string {
    if(!preg_match('~^uploads/posts/[a-z0-9-]+/(?:images/|video/)?[a-zA-Z0-9_.-]+\.(?:webp|png|jpg|jpeg|mp4)$~',$path)){throw new RuntimeException('Caminho fora de uploads/posts');}return $path;
}
function stageScope(array $row):array {
    unset($row['views'],$row['curtidas'],$row['comentarios_count'],$row['likes_count'],$row['data_atualizacao']);return $row;
}
function stageReferences(string $html):array {
    preg_match_all('~(?:src|data-src)=["\x27]([^"\x27]+)["\x27]~',$html,$m);return array_values(array_unique($m[1]));
}
function stageRelative(string $url):?string {
    if(preg_match('~(?:^|/)(uploads/posts/[a-z0-9-]+/(?:images/|video/)?[a-zA-Z0-9_.-]+\.(?:webp|png|jpg|jpeg|mp4))$~',$url,$m)){return stagePath($m[1]);}return null;
}
$uploads=$config['profiles']['stage']['uploads'];
$ftpRoot=rtrim($uploads['root'],'/');
if($uploads['mode']!=='ftp'||!str_ends_with($ftpRoot,'/stage/uploads')){throw new RuntimeException('Destino nao e stage/uploads');}
$ftp=@ftp_connect($uploads['host'],(int)$uploads['port'],30);
if(!$ftp||!@ftp_login($ftp,$uploads['username'],$uploads['password'])){throw new RuntimeException('FTP stage indisponivel');}
ftp_pasv($ftp,(bool)$uploads['passive']);
function remoteStagePath(string $root,string $relative):string {return $root.'/'.substr(stagePath($relative),8);}
function stageDownload(FTP\Connection $ftp,string $remote,string $file):void {
    if(!@ftp_get($ftp,$file,$remote,FTP_BINARY)||!is_file($file)||filesize($file)<1){throw new RuntimeException('Nao foi possivel conferir arquivo remoto');}
}
try {
    if($action==='--prepare'){
        if(is_file($stateFile)){throw new RuntimeException('Pacote existente; nao sobrescrever');}
        if(count($localRows)!==10){throw new RuntimeException('Dez origens locais obrigatorias');}
        $items=[];$files=[];$originals=[];
        $stageColumns=array_column($stage->query('SHOW COLUMNS FROM posts')->fetchAll(),'Field');
        $allBefore=$stage->query('SELECT * FROM posts ORDER BY id')->fetchAll();
        $categoriesBefore=$stage->query('SELECT * FROM categoria_post ORDER BY id')->fetchAll();
        foreach($localRows as $source){
            $matches=array_values(array_filter($stageRows,static fn(array $r):bool=>$r['slug']===$source['slug']));$before=$matches[0]??null;
            if($before===null){
                if(!str_starts_with($source['slug'],'kingston-nv3-')&&!str_starts_with($source['slug'],'the-witcher-3-')&&!str_starts_with($source['slug'],'windows-11-24h2-')){throw new RuntimeException('Artigo ausente fora do escopo');}
                $fields=['titulo','slug','resumo','conteudo','categoria','imagem_capa','imagem_thumb','data_publicacao','tempo_leitura','seo_title','seo_description','seo_keywords','tags','status','destaque','tipo_post'];
                $desired=array_intersect_key($source,array_flip(array_intersect($fields,$stageColumns)));
                $category=$local->prepare('SELECT slug FROM categoria_post WHERE id=?');$category->execute([$source['categoria_post_id']]);$categorySlug=$category->fetchColumn();
                $category=$stage->prepare('SELECT id FROM categoria_post WHERE slug=?');$category->execute([$categorySlug]);$categoryId=$category->fetchColumn();
                if(!$categoryId){throw new RuntimeException('Categoria necessaria ausente');}$desired['categoria_post_id']=(int)$categoryId;
                $author=$local->prepare('SELECT nome FROM usuarios WHERE id=?');$author->execute([$source['autor_id']]);$name=$author->fetchColumn();
                $author=$stage->prepare('SELECT id FROM usuarios WHERE nome=?');$author->execute([$name]);$ids=$author->fetchAll();if(count($ids)!==1){throw new RuntimeException('Autoria sem correspondencia unica');}$desired['autor_id']=(int)$ids[0]['id'];
                $desired['proximo_post_id']=null;
                if($source['proximo_post_id']!==null){$next=$local->prepare('SELECT slug FROM posts WHERE id=?');$next->execute([$source['proximo_post_id']]);$nextSlug=$next->fetchColumn();$next=$stage->prepare('SELECT id FROM posts WHERE slug=?');$next->execute([$nextSlug]);$target=$next->fetchColumn();$desired['proximo_post_id']=$target===false?null:(int)$target;}
                foreach(stageReferences($desired['conteudo']) as $ref){$relative=stageRelative($ref);if($relative!==null){$desired['conteudo']=str_replace($ref,$relative,$desired['conteudo']);}}
            }else{
                $desired=['conteudo'=>$before['conteudo'],'imagem_capa'=>$source['imagem_capa'],'imagem_thumb'=>$source['imagem_thumb']];
                $mapping=[];
                foreach($diablo['mapping'] as $old=>$map){if($map['slug']!==$source['slug']){continue;}$mapping[$old]=$map['new'];$mapping['uploads/posts/'.$source['slug'].'/'.basename($old)]=$map['new'];}
                foreach(stageReferences($desired['conteudo']) as $ref){$relative=stageRelative($ref);if($relative!==null&&isset($mapping[$relative])){$desired['conteudo']=str_replace($ref,$mapping[$relative],$desired['conteudo']);}}
                if(str_starts_with($source['slug'],'msi-mag-b650-')){
                    foreach(stageReferences($desired['conteudo']) as $ref){if(stageRelative($ref)===$before['imagem_capa']){$desired['conteudo']=str_replace($ref,$source['imagem_capa'],$desired['conteudo']);}}
                }
                // Existing temporal articles require the exact pre-review content.
                foreach($temporal as $t){if($t['before']['slug']!==$source['slug']){continue;}foreach($t['after'] as $field=>$value){$value=$source[$field];if($before[$field]!==$t['before'][$field]&&$before[$field]!==$value){throw new RuntimeException('Conflito editorial temporal');}$desired[$field]=$value;}}
                // Use existing stage media for legacy absolute references, without replacing its artwork.
                foreach(stageReferences($desired['conteudo']) as $ref){
                    $relative=stageRelative($ref);
                    if($relative!==null&&$ref!==$relative&&!str_contains($relative,'-mesa-v1-')&&!str_contains($relative,'capa-cenario-')){
                        if(!str_starts_with($relative,'uploads/posts/'.$source['slug'].'/')||@ftp_size($ftp,remoteStagePath($ftpRoot,$relative))<0){throw new RuntimeException('Referencia legada sem arquivo stage');}
                        $desired['conteudo']=str_replace($ref,$relative,$desired['conteudo']);
                    }
                }
            }
            $paths=[$desired['imagem_capa'],$desired['imagem_thumb']];
            foreach(stageReferences($desired['conteudo']) as $ref){$r=stageRelative($ref);if($r!==null&&($before===null||str_contains($r,'-mesa-v1-')||str_contains($r,'capa-cenario-'))){$paths[]=$r;}}
            foreach(array_unique($paths) as $path){stagePath($path);$file=$root.'/public/'.$path;if(!is_file($file)){throw new RuntimeException('Origem local ausente: '.$path);}$files[$path]=['path'=>$path,'sha256'=>hash_file('sha256',$file),'size'=>filesize($file)];}
            $items[]=['slug'=>$source['slug'],'before'=>$before,'desired'=>$desired,'sourceId'=>$source['id'],'sourceStatus'=>$source['status'],'fields'=>array_keys($desired)];
            if($before!==null){
                $oldPaths=[$before['imagem_capa'],$before['imagem_thumb']];
                foreach(stageReferences($before['conteudo']) as $ref){$r=stageRelative($ref);if($r!==null&&str_contains($desired['conteudo'],basename($r))===false){$oldPaths[]=$r;}}
                foreach(array_unique($oldPaths) as $path){if(!$path){continue;}stagePath($path);if(isset($originals[$path])){continue;}$remote=remoteStagePath($ftpRoot,$path);$exists=@ftp_size($ftp,$remote)>=0;$backup=$dir.'/original-'.substr(hash('sha256',$path),0,16).'.bin';if($exists){stageDownload($ftp,$remote,$backup);}$originals[$path]=['path'=>$path,'existed'=>$exists,'backup'=>$exists?$backup:null,'sha256'=>$exists?hash_file('sha256',$backup):null];}
            }
        }
        stageSave($stateFile,['environment'=>'stage','preparedAt'=>date(DATE_ATOM),'items'=>$items,'files'=>array_values($files),'originals'=>array_values($originals),'allBefore'=>$allBefore,'categoriesBefore'=>$categoriesBefore,'unsupportedSourceFields'=>array_values(array_diff(['tipo_post'],$stageColumns))]);
        echo 'PREPARED: '.count($items).' artigos, '.count($files).' arquivos; '.count($stageRows).' existentes e '.(10-count($stageRows)).' novos'.PHP_EOL;
        foreach($items as $i){echo $i['slug'].': '.($i['before']===null?'INSERT '.$i['sourceStatus']:'UPDATE referencias; texto existente preservado').PHP_EOL;}exit;
    }
    $state=json_decode((string)file_get_contents($stateFile),true,512,JSON_THROW_ON_ERROR);
    if($state['environment']!=='stage'||array_column($state['items'],'slug')!==array_column($localRows,'slug')){throw new RuntimeException('Pacote divergente');}
    foreach($state['files'] as $file){if(hash_file('sha256',$root.'/public/'.$file['path'])!==$file['sha256']){throw new RuntimeException('Origem mudou desde preparacao');}}
    if($action==='--apply'){
        foreach($state['files'] as $file){
            $remote=remoteStagePath($ftpRoot,$file['path']);$exists=@ftp_size($ftp,$remote)>=0;
            if(!$exists){$relative=substr(dirname($file['path']),8);$current=$ftpRoot;foreach(explode('/',$relative) as $part){$current.='/'.$part;@ftp_mkdir($ftp,$current);}if(!@ftp_put($ftp,$remote,$root.'/public/'.$file['path'],FTP_BINARY)){throw new RuntimeException('Falha de envio de asset');}}
            $proof=$dir.'/uploaded-'.substr($file['sha256'],0,16).'.bin';stageDownload($ftp,$remote,$proof);if(hash_file('sha256',$proof)!==$file['sha256']){throw new RuntimeException('Arquivo remoto existente divergente; nao sobrescrito');}
        }
        $stage->beginTransaction();$mapping=[];
        foreach($state['items'] as $item){
            $s=$stage->prepare('SELECT * FROM posts WHERE slug=? FOR UPDATE');$s->execute([$item['slug']]);$current=$s->fetch();$desired=$item['desired'];
            if($item['before']===null&&$current===false){$columns=array_keys($desired);$s=$stage->prepare('INSERT INTO posts (`'.implode('`,`',$columns).'`) VALUES ('.implode(',',array_fill(0,count($columns),'?')).')');$s->execute(array_values($desired));$id=(int)$stage->lastInsertId();}
            elseif($current!==false){
                $same=true;foreach($desired as $field=>$value){if($current[$field]!=$value){$same=false;}}
                if(!$same){if($item['before']===null||stageScope($current)!==stageScope($item['before'])){throw new RuntimeException('Artigo stage mudou; nao sobrescrever');}$sets=array_map(static fn(string $f):string=>'`'.$f.'`=?',array_keys($desired));$s=$stage->prepare('UPDATE posts SET '.implode(',',$sets).' WHERE id=? AND slug=?');$s->execute([...array_values($desired),$current['id'],$item['slug']]);}
                $id=(int)$current['id'];
            }else{throw new RuntimeException('Artigo existente desapareceu');}
            $mapping[]=['slug'=>$item['slug'],'stageId'=>$id,'inserted'=>$item['before']===null];
        }
        $stage->commit();file_put_contents($dir.'/application.json',json_encode(['mapping'=>$mapping,'appliedAt'=>date(DATE_ATOM)],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    }
    $after=selectedStageRows($stage,$slugs);if(count($after)!==10){throw new RuntimeException('Dez artigos stage obrigatorios');}
    foreach($state['items'] as $item){$current=array_values(array_filter($after,static fn(array $r):bool=>$r['slug']===$item['slug']))[0];foreach($item['desired'] as $field=>$value){if($current[$field]!=$value){throw new RuntimeException('Campo final divergente: '.$field);}}if($item['before']!==null){$old=$item['before'];$now=$current;foreach(array_keys($item['desired']) as $f){unset($old[$f],$now[$f]);}if(stageScope($old)!==stageScope($now)){throw new RuntimeException('Campo fora do escopo mudou');}}echo 'PASS stage #'.$current['id'].' '.$current['slug'].' ['.$current['status'].']'.PHP_EOL;}
    $all=$stage->query('SELECT * FROM posts ORDER BY id')->fetchAll();foreach($state['allBefore'] as $old){if(in_array($old['slug'],$slugs,true)){continue;}$matches=array_values(array_filter($all,static fn(array $r):bool=>$r['id']===$old['id']));if(count($matches)!==1||stageScope($matches[0])!==stageScope($old)){throw new RuntimeException('Outro artigo foi alterado');}}
    if($stage->query('SELECT * FROM categoria_post ORDER BY id')->fetchAll()!==$state['categoriesBefore']){throw new RuntimeException('Categorias stage alteradas');}
    foreach($state['files'] as $file){$proof=$dir.'/verified-'.substr($file['sha256'],0,16).'.bin';stageDownload($ftp,remoteStagePath($ftpRoot,$file['path']),$proof);if(hash_file('sha256',$proof)!==$file['sha256']){throw new RuntimeException('SHA remoto divergente');}}
    foreach($state['originals'] as $file){if(!$file['existed']){continue;}$proof=$dir.'/original-checked-'.substr(hash('sha256',$file['path']),0,16).'.bin';stageDownload($ftp,remoteStagePath($ftpRoot,$file['path']),$proof);if(hash_file('sha256',$proof)!==$file['sha256']){throw new RuntimeException('Original remoto alterado');}}
    file_put_contents($dir.'/verified.json',json_encode(['environment'=>'stage','verifiedAt'=>date(DATE_ATOM),'articles'=>10,'files'=>count($state['files']),'originalsPreserved'=>true,'otherArticlesPreserved'=>true,'categoriesPreserved'=>true],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo 'PASS: uploads integros, originais e dados fora do escopo preservados'.PHP_EOL;
}catch(Throwable $e){if($stage->inTransaction()){$stage->rollBack();}throw $e;}
finally{ftp_close($ftp);}
