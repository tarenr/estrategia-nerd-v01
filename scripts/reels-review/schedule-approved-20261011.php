<?php
declare(strict_types=1);

// One approved local schedule revision. No Meta requests or content/media edits.
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Repositories\InstagramPostRepository;
use App\Services\Instagram\InstagramApiService;

$action=$argv[1] ?? '--check';
if (!in_array($action,['--check','--apply','--verify'],true)) { throw new RuntimeException('Ação inválida.'); }
$plan=[
    244=>['before'=>null,'after'=>'2026-10-24 19:30:00'],
    215=>['before'=>null,'after'=>'2026-10-25 12:00:00'],
    243=>['before'=>null,'after'=>'2026-10-25 19:30:00'],
    233=>['before'=>'2026-11-04 19:30:00','after'=>'2026-10-26 12:00:00'],
    230=>['before'=>'2026-10-28 19:30:00','after'=>'2026-10-27 12:00:00'],
    231=>['before'=>'2026-10-31 19:30:00','after'=>'2026-10-27 19:30:00'],
    232=>['before'=>'2026-11-02 19:30:00','after'=>'2026-10-28 12:00:00'],
    234=>['before'=>'2026-11-07 19:30:00','after'=>'2026-10-28 19:30:00'],
];
$diablo=[224=>'2026-10-21 19:30:00',225=>'2026-10-22 12:00:00',226=>'2026-10-22 19:30:00',195=>'2026-10-23 12:00:00',227=>'2026-10-23 19:30:00',197=>'2026-10-24 12:00:00'];
$pdo=$GLOBALS['pdo']; $repo=new InstagramPostRepository($pdo);
$dir=base_path('storage/backups/instagram-publish/schedule-20261011');
$snapshot=static function() use($pdo):array { return ['posts'=>$pdo->query('SELECT * FROM instagram_posts ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),'media'=>$pdo->query('SELECT * FROM instagram_post_media ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)]; };
$validate=static function(array $rows,bool $applied) use($plan,$diablo):void {
    $indexed=[]; $slots=[];
    foreach($rows as $row){$indexed[(int)$row['id']]=$row;}
    foreach($plan as $id=>$dates){
        $row=$indexed[$id] ?? null;
        if($row===null || $row['tipo']!=='reels' || $row['render_status']!=='ready' || $row['ig_media_id']!==null || $row['creation_id']!==null || $row['publish_phase']!=='idle') { throw new RuntimeException('Registro não apto: '.$id); }
        $already=$row['status']==='agendado' && $row['agendado_para']===$dates['after'];
        $original=$row['status']===($dates['before']===null?'rascunho':'agendado') && $row['agendado_para']===$dates['before'];
        if(!$already && ($applied || !$original)){throw new RuntimeException('Agenda mudou fora do plano: '.$id);}
        $video=(string)$row['video_rendered_path'];
        if(!str_starts_with($video,'uploads/reels/') || str_contains($video,'..') || !is_file(base_path('public/'.$video))){throw new RuntimeException('Vídeo indisponível.');}
        InstagramApiService::reelCoverParams($video,base_path('public'));
        $indexed[$id]['status']='agendado'; $indexed[$id]['agendado_para']=$dates['after'];
    }
    foreach($diablo as $id=>$date){if(($indexed[$id]['status']??null)!=='agendado' || ($indexed[$id]['agendado_para']??null)!==$date){throw new RuntimeException('Sequência Diablo alterada.');}}
    foreach($indexed as $row){
        if(!in_array($row['status'],['agendado','publicando'],true)){continue;}
        $date=(string)$row['agendado_para'];
        if(isset($slots[$date])){throw new RuntimeException('Horário duplicado: '.$date);}
        $slots[$date]=(int)$row['id'];
    }
    for($day=12;$day<=28;$day++){foreach(['12:00:00','19:30:00'] as $time){$slot=sprintf('2026-10-%02d %s',$day,$time);if(!isset($slots[$slot])){throw new RuntimeException('Lacuna na grade: '.$slot);}}}
    if(($indexed[216]['agendado_para']??null)!=='2026-10-11 19:30:00'){throw new RuntimeException('Counter-Strike de hoje alterado.');}
};
if($action==='--check'){$validate($snapshot()['posts'],false);echo "PASS: plano de 8 registros válido; 34 horários de 12 a 28/10 preenchidos, Diablo sequencial.\n";exit;}
if($action==='--apply'){
    $lock=fopen(sys_get_temp_dir().'/en-instagram-publish.lock','c');
    if($lock===false || !flock($lock,LOCK_EX|LOCK_NB)){throw new RuntimeException('Publicador ativo; nenhuma alteração.');}
    try{
        $pdo->beginTransaction(); $pdo->query('SELECT id FROM instagram_posts ORDER BY id FOR UPDATE')->fetchAll();
        $before=$snapshot();$validate($before['posts'],false);$changed=[];
        foreach($before['posts'] as $row){$id=(int)$row['id'];if(isset($plan[$id]) && ($row['status']!=='agendado' || $row['agendado_para']!==$plan[$id]['after'])){$changed[]=$id;}}
        if($changed===[]){$pdo->rollBack();echo "PASS: agenda já aplicada; nenhuma escrita no banco.\n";exit;}
        if(!is_dir($dir) && !mkdir($dir,0700,true)){throw new RuntimeException('Backup indisponível.');}
        $path=$dir.'/before.json';if(is_file($path)){throw new RuntimeException('Backup anterior existente; verificar antes de alterar.');}
        $bytes=json_encode(['snapshot'=>$before,'plan'=>$plan],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        if(file_put_contents($path,$bytes,LOCK_EX)!==strlen($bytes)){throw new RuntimeException('Backup incompleto.');}
        foreach($changed as $id){$post=$repo->findById($id);if($post===null || !$repo->update($id,array_replace($post,['status'=>'agendado','agendado_para'=>$plan[$id]['after']]))){throw new RuntimeException('Atualização não concluída.');}}
        $after=$snapshot();$validate($after['posts'],true);
        foreach($after['posts'] as $i=>$row){$old=$before['posts'][$i];foreach($row as $field=>$value){if($value!==$old[$field] && !(in_array((int)$row['id'],$changed,true) && in_array($field,['status','agendado_para','atualizado_em'],true))){throw new RuntimeException('Campo fora do escopo alterado.');}}}
        if($after['media']!==$before['media']){throw new RuntimeException('Mídias alteradas; rollback.');}
        $pdo->commit();echo "PASS: 5 reagendamentos e 3 rascunhos agendados; demais campos e registros preservados.\n";
    }catch(Throwable $e){if($pdo->inTransaction()){$pdo->rollBack();}throw $e;}
    finally{flock($lock,LOCK_UN);fclose($lock);}
}
$current=$snapshot();$validate($current['posts'],true);
if(!is_file($dir.'/before.json')){throw new RuntimeException('Backup obrigatório ausente.');}
file_put_contents($dir.'/verified.json',json_encode(['plan'=>$plan,'diablo'=>$diablo,'dailySlots'=>34,'verified_at'=>date('c')],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo "PASS: 2 por dia, 12h/19h30, de 12 a 28/10; sequência Diablo preservada.\n";
