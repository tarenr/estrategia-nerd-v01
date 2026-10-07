<?php
declare(strict_types=1);

use App\Repositories\InstagramPostRepository;
use App\Services\Instagram\InstagramApiService;

if (PHP_SAPI !== 'cli') { exit(1); }
$root=dirname(__DIR__);
require_once $root.'/app/Support/Helpers.php';
require_once $root.'/app/Repositories/InstagramPostRepository.php';
require_once $root.'/app/Services/Instagram/InstagramApiService.php';
$GLOBALS['config']=['instagram'=>['public_media_url'=>'https://media.example.test']];
$passed=0;$failed=0;
$check=static function(string $name,bool $ok)use(&$passed,&$failed):void{$ok?$passed++:$failed++;echo ($ok?'PASS ':'FAIL ').$name."\n";};
$work=$root.'/storage/previews/reels-konva/cover-tests-'.date('Ymd-His').'-'.bin2hex(random_bytes(4));
mkdir($work.'/public/uploads',0755,true);
$image=imagecreatetruecolor(100,160);
if(!$image instanceof GdImage)throw new RuntimeException('GD indisponível.');
imagefill($image,0,0,(int) imagecolorallocate($image,18,24,45));
imagejpeg($image,$work.'/public/uploads/valid.jpg',92);
imagepng($image,$work.'/public/uploads/wrong-type.jpg');
imagedestroy($image);
file_put_contents($work.'/public/uploads/empty.jpg','');
file_put_contents($work.'/public/uploads/corrupt.jpg','not a JPEG');
$jpeg=(string) file_get_contents($work.'/public/uploads/valid.jpg');
$scan=strpos($jpeg,"\xFF\xDA");
if($scan===false)throw new RuntimeException('Fixture JPEG sem scan.');
file_put_contents($work.'/public/uploads/truncated.jpg',substr($jpeg,0,$scan));
copy($work.'/public/uploads/valid.jpg',$work.'/outside.jpg');
$posts=0;$sent=[];
$transport=static function(string $method,string $url,array $payload,int $timeout,int $connect)use(&$posts,&$sent):array{
    if($method==='POST'){$posts++;$sent=$payload;}
    return ['body'=>'{"id":"fake-container"}','http_code'=>200,'error'=>'','errno'=>0];
};
$api=new InstagramApiService('test-token','test-user',$transport);
// Ephemeral local database + HTTP spy. No bootstrap, .env or real Meta calls.
$local=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$local->sqliteCreateFunction('NOW',static fn():string=>date('Y-m-d H:i:s'));
$local->exec('CREATE TABLE instagram_posts(id INTEGER PRIMARY KEY,status TEXT,publish_phase TEXT,creation_id TEXT,ig_media_id TEXT,error_log TEXT,atualizado_em TEXT)');
$repo=new InstagramPostRepository($local);
$cases=['ausente'=>'uploads/missing.mp4','vazia'=>'uploads/empty.mp4','corrompida'=>'uploads/corrupt.mp4',
    'PNG disfarçado de JPEG'=>'uploads/wrong-type.mp4','JPEG incompleto'=>'uploads/truncated.mp4',
    'fora de public'=>'../outside.mp4','vídeo remoto sem capa local'=>'https://example.test/video.mp4','extensão incompatível'=>'uploads/video.mov'];
$id=0;
foreach($cases as $name=>$video){
    $id++;$local->prepare("INSERT INTO instagram_posts(id,status,publish_phase) VALUES(?,'publicando','preparing')")->execute([$id]);
    $before=$posts;$blocked=false;
    try{$api->createVideoContainer('https://media.example.test/video.mp4','Legenda',['media_type'=>'REELS']+InstagramApiService::reelCoverParams($video,$work.'/public'));}
    catch(RuntimeException $e){$blocked=true;$repo->markError($id,$e->getMessage());}
    $post=$local->query('SELECT * FROM instagram_posts WHERE id='.$id)->fetch(PDO::FETCH_ASSOC);
    $check($name.': bloqueia antes de qualquer POST',$blocked && $posts===$before);
    $check($name.': aviso claro, sem IDs de publicação',$post['status']==='erro' && $post['publish_phase']==='failed' && str_contains($post['error_log'],'Capa do Reel') && str_contains($post['error_log'],'JPEG') && $post['creation_id']===null && $post['ig_media_id']===null);
}
foreach([[],['cover_url'=>''],['cover_url'=>'  '],['cover_url'=>123]] as $extra){
    $before=$posts;$blocked=false;
    try{$api->createVideoContainer('https://media.example.test/video.mp4','Legenda',$extra);}catch(RuntimeException){$blocked=true;}
    $check('Chamada direta sem capa não contorna a proteção',$blocked && $posts===$before);
}
$before=$posts;$blocked=false;
try{$api->createMediaContainer(['media_type'=>'REELS','video_url'=>'https://media.example.test/video.mp4']);}catch(RuntimeException){$blocked=true;}
$check('Container genérico de Reel também exige capa antes do POST',$blocked && $posts===$before);
$cover=InstagramApiService::reelCoverParams('uploads/valid.mp4',$work.'/public');
$before=$posts;$container=$api->createVideoContainer('https://media.example.test/valid.mp4','Legenda',['media_type'=>'REELS']+$cover);
$check('Capa válida acompanha o único POST simulado',$container==='fake-container' && $posts===$before+1 && $sent['cover_url']==='https://media.example.test/uploads/valid.jpg');
$before=$posts;$api->createVideoContainer('https://media.example.test/story.mp4',null,['media_type'=>'STORIES']);
$check('Story conserva seu fluxo sem exigência de capa',$posts===$before+1 && !isset($sent['cover_url']));
// Existing scheduled covers are read only; never remove a real JPG to test failure.
$manifestPath=$root.'/storage/backups/reels-konva/batch-20261007-145016-0065c4eb/manifest.json';
if(is_file($manifestPath)){
    $manifest=json_decode((string) file_get_contents($manifestPath),true,512,JSON_THROW_ON_ERROR);
    $valid=0;
    foreach($manifest['items'] as $item){$params=InstagramApiService::reelCoverParams($item['video'],$root.'/public');if(str_ends_with($params['cover_url'],$item['cover']) && hash_file('sha256',$root.'/public/'.$item['cover'])===$item['cover_sha256'])$valid++;}
    $check('26 capas agendadas válidas e preservadas',$valid===26);
}
echo "$passed OK / $failed falhas. Fixtures: $work\n";exit($failed>0?1:0);
