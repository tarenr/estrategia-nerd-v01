<?php
declare(strict_types=1);

// Restricted to the approved replacement of deleted Instagram post 209.
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Repositories\InstagramPostRepository;
use App\Services\Instagram\InstagramApiService;
use App\Services\Instagram\InstagramPublishConfirmationService;

$action = $argv[1] ?? '--check';
if (!in_array($action, ['--check', '--prepare', '--publish', '--confirm'], true)) { throw new RuntimeException('Ação inválida.'); }
$catalog = json_decode((string) file_get_contents(base_path('resources/reels-review/corrections-20261010.json')), true, 512, JSON_THROW_ON_ERROR);
$items = array_values(array_filter($catalog['items'], static fn(array $item): bool => $item['id'] === 209));
if (count($items) !== 1) { throw new RuntimeException('Item 209 não é unívoco.'); }
$item = $items[0];
if ($item['state'] !== 'ready' || $item['video'] !== 'uploads/reels/correcao-instagram-20261010/reel-209-v3.mp4'
    || hash_file('sha256', base_path('public/' . $item['video'])) !== $item['sha256']) { throw new RuntimeException('Vídeo divergente.'); }
$cover = InstagramApiService::reelCoverParams($item['video'], base_path('public'));
$videoUrl = InstagramApiService::buildPublicMediaUrl($item['video']);
$pdo = $GLOBALS['pdo'];
$repo = new InstagramPostRepository($pdo);
$source = $repo->findById(209);
$account = $repo->findActiveAccount();
if ($source === null || $account === null || (int)$source['account_id'] !== (int)$account['id']) { throw new RuntimeException('Conta/post não conferem.'); }
$key = 'republication:209:20261011:v3';
$post = $repo->findByIdempotencyKey($key);
if ($action === '--check') { echo "PASS: vídeo, capa e conta conferidos; nenhum envio.\n"; exit; }
$dir = base_path('storage/backups/instagram-publish/windows-20261011');
if (!is_dir($dir) && !mkdir($dir, 0700, true)) { throw new RuntimeException('Backup indisponível.'); }
if ($action === '--prepare' && $post === null) {
    $backup = $dir . '/before.json';
    if (!is_file($backup)) {
        $handle = fopen($backup, 'x');
        if ($handle === false) { throw new RuntimeException('Backup não criado.'); }
        try { $bytes = json_encode(['post'=>$source,'media'=>$repo->findMediaByPostId(209)], JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR); if (fwrite($handle,$bytes) !== strlen($bytes)) { throw new RuntimeException('Backup incompleto.'); } } finally { fclose($handle); }
    }
    $pdo->beginTransaction();
    try {
        $id = $repo->create(['account_id'=>(int)$account['id'],'status'=>'rascunho','tipo'=>'reels','legenda'=>$item['caption'],
            'hashtags_count'=>preg_match_all('/#[\p{L}\p{N}_]+/u',$item['caption']),'post_blog_id'=>$source['post_blog_id'],
            'idempotency_key'=>$key,'origin'=>'local','audio_track_id'=>$source['audio_track_id'],
            'audio_start_seconds'=>$source['audio_start_seconds'],'audio_duration_seconds'=>20]);
        $repo->addMedia($id,['ordem'=>0,'tipo_arquivo'=>'video','caminho'=>$item['video'],'url_publica'=>$videoUrl,'largura'=>1080,'altura'=>1920,'duracao_s'=>20]);
        $repo->markRenderedReady($id,$item['video']);
        $pdo->commit();
        $post = $repo->findById($id);
    } catch (Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
}
if ($post === null || $post['video_rendered_path'] !== $item['video'] || $post['legenda'] !== $item['caption']) { throw new RuntimeException('Registro da republicação divergente.'); }
$id = (int)$post['id'];
if ($action === '--prepare') { echo 'PASS: rascunho separado #' . $id . "; original preservado.\n"; exit; }
$api = new InstagramApiService((string)$account['access_token'],(string)$account['ig_user_id']);
$confirmation = new InstagramPublishConfirmationService($repo,$api);
if ($action === '--confirm' || $post['status'] === 'publicado') { $confirmation->reconcile($id); }
else {
    // Never restart a publication, including uncertain attempts or rejected containers.
    if ($post['status'] !== 'rascunho' || !$repo->claimForImmediatePublishing($id)) { throw new RuntimeException('Tentativa existente; usar --confirm, sem reenviar.'); }
    $creationId = $api->createVideoContainer($videoUrl,$item['caption'],['media_type'=>'REELS']+$cover);
    if (!$repo->saveCreationId($id,$creationId)) { throw new RuntimeException('Container não registrado; nada publicado.'); }
    $ready = false;
    for ($attempt=0;$attempt<24;$attempt++) {
        $status = $api->checkContainerStatus($creationId);
        echo 'Container: ' . $status . PHP_EOL;
        if ($status === 'FINISHED') { $ready=true; break; }
        if (in_array($status,['ERROR','EXPIRED'],true)) { throw new RuntimeException('Processamento rejeitado.'); }
        sleep(5);
    }
    if (!$ready) { throw new RuntimeException('Container ainda não pronto; não reenviar.'); }
    $result = $confirmation->publish($id,$creationId);
    echo 'Resultado: ' . $result['state'] . PHP_EOL;
}
$post=$repo->findById($id);
$evidence=['post_id'=>$id,'source_post_id'=>209,'status'=>$post['status'],'permalink'=>$post['permalink'],'video'=>$item['video'],'cover'=>$item['poster'],'sha256'=>$item['sha256'],'verified_at'=>date('c')];
file_put_contents($dir.'/result.json',json_encode($evidence,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
echo json_encode($evidence,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
