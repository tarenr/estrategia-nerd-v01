<?php
declare(strict_types=1);

// Align existing pending Reels with the reviewed catalog. Never publish or reschedule.
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Repositories\InstagramPostRepository;
use App\Services\Instagram\InstagramApiService;

$action = $argv[1] ?? '--check';
if (!in_array($action, ['--check', '--apply', '--verify'], true)) { throw new RuntimeException('Use --check, --apply ou --verify.'); }
$pdo = $GLOBALS['pdo'];
$repo = new InstagramPostRepository($pdo);
$catalog = json_decode((string)file_get_contents(base_path('resources/reels-review/corrections-20261010.json')), true, 512, JSON_THROW_ON_ERROR);
$dir = base_path('storage/backups/instagram-publish/approved-links-20261011');
$snapshot = static function () use ($pdo): array {
    return ['posts'=>$pdo->query('SELECT * FROM instagram_posts ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
        'media'=>$pdo->query('SELECT * FROM instagram_post_media ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)];
};
$plans = []; $preserved = [];
foreach ($catalog['items'] as $item) {
    $post = $repo->findById((int)$item['id']);
    if ($post === null) { throw new RuntimeException('Post do catálogo ausente.'); }
    if (!in_array($post['status'], ['agendado','rascunho'], true) || $post['tipo'] !== 'reels') {
        $preserved[] = ['id'=>(int)$post['id'],'status'=>$post['status'],'tipo'=>$post['tipo']]; continue;
    }
    if ($post['ig_media_id'] !== null || $post['creation_id'] !== null || $post['publish_phase'] !== 'idle') { throw new RuntimeException('Tentativa de publicação existente; interrompido.'); }
    if ($item['state'] !== 'ready' || (int)$item['spec']['duration'] !== 20
        || !str_starts_with($item['video'],'uploads/reels/correcao-instagram-20261010/') || str_contains($item['video'],'..')
        || hash_file('sha256',base_path('public/'.$item['video'])) !== $item['sha256']) { throw new RuntimeException('Versão revisada inválida.'); }
    $cover = InstagramApiService::reelCoverParams($item['video'],base_path('public'));
    if ($item['poster'] !== preg_replace('/\.mp4$/','.jpg',$item['video'])) { throw new RuntimeException('Capa divergente.'); }
    if ($item['trackId'] !== null && $repo->findAudioTrack((int)$item['trackId']) === null) { throw new RuntimeException('Trilha do catálogo inexistente.'); }
    $media = $repo->findMediaByPostId((int)$post['id']);
    if (count($media) !== 1 || $media[0]['tipo_arquivo'] !== 'video') { throw new RuntimeException('Composição inesperada.'); }
    $desired = ['video_rendered_path'=>$item['video'],'render_status'=>'ready','audio_track_id'=>$item['trackId'],
        'audio_start_seconds'=>(int)$item['spec']['audioStart'],'audio_duration_seconds'=>20,'legenda'=>$item['caption'],
        'hashtags_count'=>preg_match_all('/#[\p{L}\p{N}_]+/u',$item['caption'])];
    $plans[(int)$post['id']] = ['before'=>$post,'media'=>$media[0],'desired'=>$desired,'cover'=>$cover['cover_url']];
}
if ($action === '--check') {
    echo json_encode(['pendingReels'=>count($plans),'ids'=>array_keys($plans),'preserved'=>$preserved],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL; exit;
}
if ($action === '--apply') {
    $lock = fopen(sys_get_temp_dir().'/en-instagram-publish.lock','c');
    if ($lock === false || !flock($lock,LOCK_EX|LOCK_NB)) { throw new RuntimeException('Publicador ativo; nenhuma alteração.'); }
    try {
        $pdo->beginTransaction();
        // Lock rows, then compare with preflight to reject concurrent panel edits.
        $pdo->query('SELECT id FROM instagram_posts ORDER BY id FOR UPDATE')->fetchAll();
        $pdo->query('SELECT id FROM instagram_post_media ORDER BY id FOR UPDATE')->fetchAll();
        $before = $snapshot();
        $changes = [];
        foreach ($plans as $id=>$plan) {
            if ($repo->findById($id) !== $plan['before'] || $repo->findMediaByPostId($id)[0] !== $plan['media']) { throw new RuntimeException('Registro mudou durante preflight.'); }
            $same = true;
            foreach ($plan['desired'] as $field=>$value) { if ((string)$plan['before'][$field] !== (string)$value) { $same=false; } }
            $m=$plan['media'];
            if ($m['caminho'] !== $plan['desired']['video_rendered_path'] || $m['url_publica'] !== null
                || (int)$m['largura'] !== 1080 || (int)$m['altura'] !== 1920 || (int)$m['duracao_s'] !== 20) { $same=false; }
            if (!$same) { $changes[$id]=$plan; }
        }
        if ($changes === []) { $pdo->rollBack(); echo "PASS: todos os apontamentos já alinhados; nenhuma escrita no banco.\n"; exit; }
        if (!is_dir($dir) && !mkdir($dir,0700,true)) { throw new RuntimeException('Backup indisponível.'); }
        $backup=$dir.'/before.json';
        if (is_file($backup)) { throw new RuntimeException('Backup anterior existente; verificar antes de nova alteração.'); }
        $bytes=json_encode(['snapshot'=>$before,'ids'=>array_keys($changes),'plans'=>$changes],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        if (file_put_contents($backup,$bytes,LOCK_EX) !== strlen($bytes)) { throw new RuntimeException('Backup incompleto.'); }
        foreach ($changes as $id=>$plan) {
            $repo->updateMediaFile((int)$plan['media']['id'],$plan['desired']['video_rendered_path'],null,1080,1920);
            $pdo->prepare('UPDATE instagram_post_media SET duracao_s=20 WHERE id=? AND post_id=?')->execute([$plan['media']['id'],$id]);
            if (!$repo->update($id,array_replace($plan['before'],$plan['desired']))) { throw new RuntimeException('Atualização não concluída.'); }
        }
        // Check every field of every record before committing, allowing only reviewed metadata.
        $after=$snapshot();
        foreach ($after['posts'] as $index=>$row) {
            $original=$before['posts'][$index]; $id=(int)$row['id'];
            foreach ($row as $field=>$value) {
                if ($value !== $original[$field] && !(isset($changes[$id]) && (array_key_exists($field,$changes[$id]['desired']) || $field==='atualizado_em'))) { throw new RuntimeException('Campo fora do escopo alterado: '.$id.'/'.$field); }
            }
        }
        foreach ($after['media'] as $index=>$row) {
            $original=$before['media'][$index];
            foreach ($row as $field=>$value) {
                if ($value !== $original[$field] && !(isset($changes[(int)$row['post_id']]) && in_array($field,['caminho','url_publica','largura','altura','duracao_s'],true))) { throw new RuntimeException('Mídia fora do escopo alterada.'); }
            }
        }
        $pdo->commit();
        echo 'PASS: '.count($changes)." apontamentos sincronizados; agenda, status e históricos preservados.\n";
    } catch (Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
    finally { flock($lock,LOCK_UN); fclose($lock); }
}
foreach ($plans as $id=>$plan) {
    $post=$repo->findById($id);
    foreach ($plan['desired'] as $field=>$value) { if ((string)$post[$field] !== (string)$value) { throw new RuntimeException('Apontamento divergente: '.$id.'/'.$field); } }
    $media=$repo->findMediaByPostId($id);
    if (count($media)!==1 || $media[0]['caminho'] !== $post['video_rendered_path'] || $media[0]['url_publica'] !== null || (int)$media[0]['duracao_s']!==20) { throw new RuntimeException('Cache e mídia divergentes.'); }
}
if (!is_dir($dir)) { throw new RuntimeException('Backup anterior obrigatório.'); }
file_put_contents($dir.'/verified.json',json_encode(['ids'=>array_keys($plans),'preserved'=>$preserved,'verified_at'=>date('c')],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo 'PASS: '.count($plans)." Reels pendentes usam as versões revisadas e suas capas.\n";
