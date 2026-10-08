<?php
declare(strict_types=1);
require __DIR__ . '/backup/EnvLoader.php';
require dirname(__DIR__) . '/app/Services/Instagram/TrackPickerService.php';
use App\Services\Instagram\TrackPickerService;
use Scripts\Backup\EnvLoader;

$root=dirname(__DIR__);
EnvLoader::load($root.'/.env');
$cfg=require $root.'/config/database.php';
$db=new PDO(sprintf('%s:host=%s;port=%s;dbname=%s;charset=%s',$cfg['driver'],$cfg['host'],$cfg['port'],$cfg['database'],$cfg['charset']),$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$query='SELECT id,audio_track_id,audio_start_seconds,audio_duration_seconds FROM instagram_posts ORDER BY id';
$before=$db->query($query)->fetchAll(PDO::FETCH_ASSOC);
$spec=json_decode((string)file_get_contents(__DIR__.'/reels-konva/pilot.json'),true,512,JSON_THROW_ON_ERROR);
$picker=new TrackPickerService($db);
$choice=$picker->pick('games',24,['titulo'=>$spec['articleTitle'],'resumo'=>implode(' ',array_column($spec['scenes'],'body')),'ritmo'=>'slow']);
if($choice===null){throw new RuntimeException($picker->warning()??'Sem trilha');}
$spec['audio']='public/'.ltrim((string)$choice['track']['arquivo_path'],'/');
$spec['audioStart']=$choice['start'];
$dir=$root.'/storage/previews/reels-music/diablo-'.date('Ymd-His');
if(is_dir($dir)){throw new RuntimeException('Saída já existe');}
mkdir($dir,0755,true);
file_put_contents($dir.'/spec.json',json_encode($spec,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
$after=$db->query($query)->fetchAll(PDO::FETCH_ASSOC);
if($before!==$after){throw new RuntimeException('Referências musicais mudaram durante o piloto; investigar concorrência');}
$report=['track'=>$choice['track'],'start'=>$choice['start'],'reused'=>$choice['reused'],'profile'=>TrackPickerService::profileFor('games',['titulo'=>$spec['articleTitle'],'ritmo'=>'slow']),'posts_unchanged'=>true,'posts_count'=>count($before),'posts_sha256'=>hash('sha256',json_encode($before,JSON_THROW_ON_ERROR))];
file_put_contents($dir.'/selection.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
echo json_encode(['directory'=>$dir,'title'=>$choice['track']['titulo'],'source_id'=>$choice['track']['origem_id'],'start'=>$choice['start'],'reused'=>$choice['reused'],'posts_unchanged'=>true],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
