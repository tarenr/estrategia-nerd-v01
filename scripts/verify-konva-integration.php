<?php
declare(strict_types=1);
use App\Services\Instagram\{AudioReelGeneratorService,BlogCrosspostService,EditorialReelService,KonvaReelRenderer,KonvaReelBatchService,MotionReelBatchService,EditorialMotionReelRenderer};
require_once dirname(__DIR__).'/app/Support/Helpers.php';
$root=dirname(__DIR__);
spl_autoload_register(static function(string $class)use($root):void{if(str_starts_with($class,'App\\'))require_once $root.'/app/'.str_replace('\\','/',substr($class,4)).'.php';});
date_default_timezone_set('America/Sao_Paulo');$passed=0;$failed=0;
$check=static function(string $name,bool $ok)use(&$passed,&$failed):void{$ok?$passed++:$failed++;echo ($ok?'PASS ':'FAIL ').$name."\n";};
$reject=static function(callable $call):bool{try{$call();return false;}catch(RuntimeException){return true;}};
$post=['status'=>'agendado','tipo'=>'reels','origin'=>'local','agendado_para'=>date('Y-m-d H:i:s',time()+86400),'publish_phase'=>'idle','creation_id'=>null,'ig_media_id'=>null,'post_blog_id'=>17,'idempotency_key'=>BlogCrosspostService::idempotencyKey('production',17)];
$check('Reconhece somente vínculo editorial de produção',EditorialReelService::isLinked($post));
$wrong=$post;$wrong['idempotency_key']=BlogCrosspostService::idempotencyKey('local',17);$check('Não usa artigo local como fallback',!EditorialReelService::isLinked($wrong));
foreach(['publicado','publicando','erro','rascunho'] as $status){$wrong=$post;$wrong['status']=$status;$check('Preserva status '.$status,$reject(static fn()=>KonvaReelBatchService::eligible($wrong)));}
foreach(['preparing','awaiting_confirmation','published_id_pending','confirmed'] as $phase){$wrong=$post;$wrong['publish_phase']=$phase;$check('Preserva fase '.$phase,$reject(static fn()=>KonvaReelBatchService::eligible($wrong)));}
foreach(['creation_id','ig_media_id'] as $field){$wrong=$post;$wrong[$field]='remote-id';$check('Preserva '.$field,$reject(static fn()=>KonvaReelBatchService::eligible($wrong)));}
$wrong=$post;$wrong['agendado_para']=date('Y-m-d H:i:s');$check('Preserva agendamento vencido',$reject(static fn()=>KonvaReelBatchService::eligible($wrong)));
$renderer=new KonvaReelRenderer($root);
$cover='uploads/posts/diablo-criacao-santuario/images/img004.png';
$check('Rejeita fonte externa/ausente',$reject(static fn()=>$renderer->asset('https://example.test/photo.png')));
$check('Rejeita saída fora de Reels antes de chamar Node',$reject(static fn()=>$renderer->renderSpec([],'../overwrite.mp4')));
$check('Texto limpa HTML e marcações sem inventar fatos',KonvaReelRenderer::clean('<b>[[Santuário]]</b> &amp; Anu')==='Santuário & Anu');
$check('Resumo sem roteiro individual não gera vídeo',$reject(static fn()=>$renderer->spec($cover,'uploads/audio/test.mp3',['id'=>1,'titulo'=>'Título','resumo'=>'Um resumo não é um roteiro.'],0)));
$check('Duração conserva limites de leitura',KonvaReelRenderer::duration(['titulo'=>str_repeat('Palavra ',50)])===30);
$check('Duração individual admite cinco cenas',KonvaReelRenderer::duration(['editorial_spec'=>['duration'=>35]])===35);
$check('Duração individual inválida é recusada',$reject(static fn()=>KonvaReelRenderer::duration(['editorial_spec'=>['duration'=>90]])));
$check('Inventário permite exatamente os 26 IDs aprovados',count(KonvaReelBatchService::IDS)===26 && !in_array(214,KonvaReelBatchService::IDS,true));
$cacheDb=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$cacheDb->exec("CREATE TABLE instagram_posts(id INTEGER,status TEXT,publish_phase TEXT,creation_id TEXT,ig_media_id TEXT,video_rendered_path TEXT,render_status TEXT,audio_duration_seconds INTEGER)");
$cacheDb->exec("CREATE TABLE instagram_post_media(post_id INTEGER,tipo_arquivo TEXT,caminho TEXT,url_publica TEXT,largura INTEGER,altura INTEGER,duracao_s INTEGER)");
$cacheDb->exec("INSERT INTO instagram_posts VALUES(1,'publicando','preparing',NULL,NULL,'old.mp4','idle',16)");
$cacheDb->exec("INSERT INTO instagram_post_media VALUES(1,'video','old.mp4','https://example.test/old',1080,1920,16)");
$cacheRepo=new \App\Repositories\InstagramPostRepository($cacheDb);$cacheRepo->markRenderedReady(1,'new.mp4',24);
$cache=$cacheDb->query('SELECT * FROM instagram_posts')->fetch(PDO::FETCH_ASSOC);$cacheMedia=$cacheDb->query('SELECT * FROM instagram_post_media')->fetch(PDO::FETCH_ASSOC);
$check('Regeneração atualiza cache e mídia juntos',$cache['video_rendered_path']==='new.mp4' && $cacheMedia['caminho']==='new.mp4' && $cacheMedia['duracao_s']===24);
$cacheDb->exec("UPDATE instagram_posts SET publish_phase='awaiting_confirmation'");
$check('Regeneração não sobrescreve tentativa pendente',$reject(static fn()=>$cacheRepo->markRenderedReady(1,'overwrite.mp4',24)));
$check('Recusa mantém referências anteriores',$cacheDb->query('SELECT caminho FROM instagram_post_media')->fetchColumn()==='new.mp4');
$cacheDb->exec("UPDATE instagram_posts SET publish_phase='preparing'");$cacheDb->exec("INSERT INTO instagram_post_media VALUES(1,'video','second.mp4',NULL,1080,1920,16)");
$check('Composição concorrente recusa regeneração',$reject(static fn()=>$cacheRepo->markRenderedReady(1,'third.mp4',25)));
$check('Falha na mídia reverte atualização do cache',$cacheDb->query('SELECT video_rendered_path FROM instagram_posts')->fetchColumn()==='new.mp4');
$options=getopt('',['manifest:','render-default']);
if(isset($options['manifest'])){
    $manifest=json_decode((string) file_get_contents((string) $options['manifest']),true,512,JSON_THROW_ON_ERROR);
    if(isset($options['render-default'])){
        $GLOBALS['config']=['instagram'=>require $root.'/config/instagram.php'];
        $source=$manifest['items'][4];
        $generator=new AudioReelGeneratorService('ffmpeg','ffprobe',$root.'/public');
        $check('Geração sem roteiro individual recusa o resumo mesmo no fluxo automático',$reject(static fn()=>$generator->generateEditorialReel($source['article']['imagem_capa'],$source['track']['arquivo_path'],$source['article'],(int) $source['audio_start'])));
    }
    // Real files, ephemeral SQLite records: no MySQL or publication APIs.
    $local=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $origin=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $insert=static function(PDO $db,string $table,array $rows):void{
        $columns=array_keys($rows[0]);$definitions=[];
        foreach($columns as $column){$numeric=false;foreach($rows as $row){if(is_int($row[$column])){$numeric=true;break;}}$definitions[]='"'.$column.'" '.($numeric?'INTEGER':'TEXT');}
        $db->exec('CREATE TABLE '.$table.'('.implode(',',$definitions).')');
        $statement=$db->prepare('INSERT INTO '.$table.' VALUES('.implode(',',array_fill(0,count($columns),'?')).')');
        foreach($rows as $row)$statement->execute(array_values($row));
    };
    $insert($local,'instagram_posts',array_column(array_column($manifest['items'],'before'),'post'));
    $insert($local,'instagram_post_media',array_map(static fn(array $i):array=>$i['before']['media'][0],$manifest['items']));
    $insert($local,'instagram_audio_tracks',array_column($manifest['items'],'track'));
    $insert($origin,'posts',array_column($manifest['items'],'article'));
    $media=new EditorialMotionReelRenderer();$guard=new MotionReelBatchService($local,$media,$root);
    $batch=new KonvaReelBatchService($local,$guard,$renderer,$media,$root);
    $work=$root.'/storage/previews/reels-konva/atomic-tests-'.date('Ymd-His').'-'.bin2hex(random_bytes(4));mkdir($work,0755,true);
    $allBefore=static function()use($local):array{return ['posts'=>$local->query('SELECT * FROM instagram_posts ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),'media'=>$local->query('SELECT * FROM instagram_post_media ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)];};
    $original=$allBefore();
    $local->exec("UPDATE instagram_posts SET legenda='Edição concorrente' WHERE id=219");$changed=$allBefore();
    $check('Edição concorrente recusa o lote inteiro',$reject(static fn()=>$batch->apply($origin,$manifest,$work.'/concurrent.json')));
    $check('Recusa não altera outros 25 nem suas mídias',$allBefore()===$changed);
    $local->prepare('UPDATE instagram_posts SET legenda=? WHERE id=219')->execute([$manifest['items'][25]['before']['post']['legenda']]);
    $local->exec("CREATE TRIGGER fail_last BEFORE UPDATE ON instagram_posts WHEN NEW.id=219 BEGIN SELECT RAISE(ABORT,'simulated failure'); END");
    try{$batch->apply($origin,$manifest,$work.'/failure.json');$rejected=false;}catch(Throwable){$rejected=true;}
    $check('Falha no último UPDATE reverte os 26 posts e mídias',$rejected && $allBefore()===$original);
    $local->exec('DROP TRIGGER fail_last');
    $journal=$batch->apply($origin,$manifest,$work.'/success.json');
    $check('Commit atômico completo após validação',$journal['state']==='applied' && count($journal['items'])===26);
    $preserved=true;
    foreach($journal['items'] as $entry){foreach(['legenda','agendado_para','audio_track_id','audio_start_seconds','post_blog_id','idempotency_key','status'] as $field){if($entry['before']['post'][$field]!==$entry['after']['post'][$field])$preserved=false;}}
    $check('Música, início, legenda, calendário e vínculo preservados',$preserved);
    $check('Reaplicar snapshot antigo é recusado',$reject(static fn()=>$batch->apply($origin,$manifest,$work.'/repeat.json')));
    $local->exec("UPDATE instagram_posts SET publish_phase='awaiting_confirmation' WHERE id=219");
    $check('Rollback protege tentativa de publicação iniciada',$reject(static fn()=>$batch->rollback($journal)));
    $local->exec("UPDATE instagram_posts SET publish_phase='idle' WHERE id=219");$batch->rollback($journal);
    $check('Rollback seguro restaura os 26 estados completos',$allBefore()===$original);
}
echo "$passed OK / $failed falhas\n";exit($failed>0?1:0);
