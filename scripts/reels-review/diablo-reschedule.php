<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__) . '/backup/EnvLoader.php';
require dirname(__DIR__, 2) . '/app/Support/Helpers.php';
Scripts\Backup\EnvLoader::load(dirname(__DIR__, 2) . '/.env');
date_default_timezone_set('America/Sao_Paulo');
$root = dirname(__DIR__, 2);
$GLOBALS['config']['instagram'] = require $root . '/config/instagram.php';
$config = require $root . '/config/content-sync.php';
$d = $config['profiles']['local']['database'];
$pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $d['host'], $d['port'], $d['database']), $d['username'], $d['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$plan = json_decode((string) file_get_contents($root . '/resources/reels-review/diablo-schedule-20261008.json'), true, 512, JSON_THROW_ON_ERROR);
$sourceIds = [191,193,192,195,194,197];
if (array_column($plan['items'], 'sourceId') !== $sourceIds || $plan['deletedSourceIds'] !== [191,192,193,194]) { throw new RuntimeException('Plano fora do escopo'); }
$dir = $root . '/storage/previews/reels-review/diablo-agenda-20261008';
if (!is_dir($dir)) { mkdir($dir, 0755, true); }
$backupFile = $dir . '/backup.json'; $appliedFile = $dir . '/applied.json';
function scheduleJson(string $file, array $value): void {
    $h = fopen($file, 'x'); if ($h === false) { throw new RuntimeException('Evidencia existente'); }
    fwrite($h, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)); fclose($h);
}
function scheduleRows(PDO $pdo, array $ids, bool $lock = false): array {
    $s = $pdo->prepare('SELECT * FROM instagram_posts WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id' . ($lock ? ' FOR UPDATE' : ''));
    $s->execute($ids); return $s->fetchAll();
}
function scheduleMedia(PDO $pdo, int $id): array {
    $s = $pdo->prepare('SELECT * FROM instagram_post_media WHERE post_id=? ORDER BY ordem,id'); $s->execute([$id]); return $s->fetchAll();
}
function scheduleKey(int $id): string {
    $h = substr(hash('sha256', 'diablo-republication-20261008:' . $id), 0, 32);
    return substr($h,0,8) . '-' . substr($h,8,4) . '-' . substr($h,12,4) . '-' . substr($h,16,4) . '-' . substr($h,20,12);
}
function unrelatedQueue(PDO $pdo, array $exclude): array {
    $rows = $pdo->query('SELECT id,account_id,status,agendado_para,post_blog_id,video_rendered_path,audio_track_id,audio_start_seconds,audio_duration_seconds,render_status,creation_id,ig_media_id,publish_phase FROM instagram_posts ORDER BY id')->fetchAll();
    return array_values(array_filter($rows, static fn(array $r): bool => !in_array((int) $r['id'], $exclude, true)));
}
$repo = new App\Repositories\InstagramPostRepository($pdo);
$action = $argv[1] ?? '';
if (!in_array($action, ['--apply','--verify'], true)) { throw new RuntimeException('Use --apply|--verify (somente local; nunca publica na Meta)'); }
foreach ($plan['items'] as $n => $item) {
    if ($item['scheduled'] !== sprintf('2026-11-%02d 19:30:00', $n + 2)) { throw new RuntimeException('Data nao aprovada'); }
    foreach (['video'=>'sha256','cover'=>'coverSha256'] as $field => $hash) {
        if (!str_starts_with($item[$field], 'uploads/reels/diablo-agenda-20261008/') || !is_file($root . '/public/' . $item[$field]) || hash_file('sha256', $root . '/public/' . $item[$field]) !== $item[$hash]) { throw new RuntimeException('Midia ausente ou divergente'); }
    }
    App\Services\Instagram\InstagramApiService::reelCoverParams($item['video'], $root . '/public');
    $track = $repo->findAudioTrack((int) $item['trackId']);
    if ($track === null || $track['origem_id'] !== $item['musicSourceId'] || !$track['ativo'] || !is_file($root . '/public/' . $track['arquivo_path']) || (float) $track['duracao_s'] < $item['audioStart'] + $item['duration']) { throw new RuntimeException('Trilha invalida'); }
}
$lock = fopen(sys_get_temp_dir() . '/en-instagram-publish.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { throw new RuntimeException('Publicador ativo; tente novamente depois'); }
try {
    if ($action === '--apply' && !is_file($appliedFile)) {
        $pdo->beginTransaction();
        $rows = scheduleRows($pdo, $sourceIds, true);
        if (count($rows) !== 6) { throw new RuntimeException('Seis originais obrigatorios'); }
        $media = []; $originalHashes = [];
        foreach ($rows as $row) {
            $id = (int) $row['id']; $item = array_values(array_filter($plan['items'], static fn(array $i): bool => $i['sourceId'] === $id))[0];
            if ((int) $row['post_blog_id'] !== $item['articleId'] || $row['origin'] !== 'local' || $row['tipo'] !== 'reels' || (int) $row['account_id'] !== 1) { throw new RuntimeException('Origem do post divergente'); }
            $deleted = in_array($id, $plan['deletedSourceIds'], true);
            if ($row['status'] !== ($deleted ? 'publicado' : 'agendado') || (!$deleted && ($row['creation_id'] !== null || $row['ig_media_id'] !== null || $row['publish_phase'] !== 'idle'))) { throw new RuntimeException('Estado original mudou; nenhuma escrita'); }
            $media[$id] = scheduleMedia($pdo, $id);
            if (count($media[$id]) !== 1) { throw new RuntimeException('Uma midia original obrigatoria'); }
            $originalHashes[$row['video_rendered_path']] = hash_file('sha256', $root . '/public/' . $row['video_rendered_path']);
        }
        $slots = $pdo->prepare("SELECT id FROM instagram_posts WHERE account_id=1 AND status IN ('agendado','publicando','erro') AND DATE(agendado_para)=DATE(?) AND id NOT IN (195,197) FOR UPDATE");
        foreach ($plan['items'] as $item) { $slots->execute([$item['scheduled']]); if ($slots->fetch()) { throw new RuntimeException('Dia ocupado; agenda nao alterada'); } }
        $before = ['posts'=>$rows,'media'=>$media,'originalHashes'=>$originalHashes,'unrelated'=>unrelatedQueue($pdo,$sourceIds)];
        if (!is_file($backupFile)) { scheduleJson($backupFile, $before); }
        elseif (json_decode((string) file_get_contents($backupFile), true, 512, JSON_THROW_ON_ERROR) !== $before) { throw new RuntimeException('Backup anterior divergente'); }
        $mapping = [];
        foreach ($plan['items'] as $item) {
            $id = $item['sourceId']; $row = array_values(array_filter($rows, static fn(array $r): bool => (int) $r['id'] === $id))[0];
            if (in_array($id, $plan['deletedSourceIds'], true)) {
                if ($repo->findByIdempotencyKey(scheduleKey($id)) !== null) { throw new RuntimeException('Republicacao ja existe sem evidencia; verificar'); }
                $newId = $repo->create(['account_id'=>1,'status'=>'agendado','tipo'=>'reels','legenda'=>$row['legenda'],'hashtags_count'=>$row['hashtags_count'],'agendado_para'=>$item['scheduled'],'post_blog_id'=>$row['post_blog_id'],'audio_track_id'=>$item['trackId'],'audio_start_seconds'=>$item['audioStart'],'audio_duration_seconds'=>$item['duration'],'video_rendered_path'=>$item['video'],'render_status'=>'ready','idempotency_key'=>scheduleKey($id),'origin'=>'local','criado_por'=>$row['criado_por']]);
                $note = 'Excluido do Instagram pelo usuario; registro historico preservado. Republicacao revisada #' . $newId . ', agenda ' . $item['scheduled'] . '.';
                $s = $pdo->prepare("UPDATE instagram_posts SET status='rascunho',error_log=? WHERE id=? AND status='publicado'"); $s->execute([trim(($row['error_log'] ?? '') . "\n" . $note),$id]);
                if ($s->rowCount() !== 1) { throw new RuntimeException('Status nao atualizado'); }
                $s = $pdo->prepare("INSERT INTO instagram_post_media (post_id,ordem,tipo_arquivo,caminho,largura,altura,duracao_s) VALUES (?,0,'video',?,1080,1920,?)"); $s->execute([$newId,$item['video'],$item['duration']]);
            } else {
                $newId = $id;
                $s = $pdo->prepare("UPDATE instagram_posts SET agendado_para=?,audio_track_id=?,audio_start_seconds=?,audio_duration_seconds=?,video_rendered_path=?,render_status='ready' WHERE id=? AND status='agendado' AND creation_id IS NULL AND ig_media_id IS NULL");
                $s->execute([$item['scheduled'],$item['trackId'],$item['audioStart'],$item['duration'],$item['video'],$id]);
                if ($s->rowCount() !== 1) { throw new RuntimeException('Reagendamento nao aplicado'); }
                $s = $pdo->prepare("UPDATE instagram_post_media SET caminho=?,url_publica=NULL,largura=1080,altura=1920,duracao_s=? WHERE id=? AND post_id=?"); $s->execute([$item['video'],$item['duration'],$media[$id][0]['id'],$id]);
            }
            $mapping[] = ['sourceId'=>$id,'postId'=>$newId,'scheduled'=>$item['scheduled']];
        }
        $excluded = array_merge($sourceIds,array_column($mapping,'postId'));
        if (unrelatedQueue($pdo,$excluded) !== $before['unrelated']) { throw new RuntimeException('Outra fila alterada'); }
        $pdo->commit();
        scheduleJson($appliedFile, ['mapping'=>$mapping,'appliedAt'=>date(DATE_ATOM),'unrelatedPreserved'=>true]);
    }
    if (!is_file($appliedFile)) { throw new RuntimeException('Aplicacao ausente'); }
    $applied = json_decode((string) file_get_contents($appliedFile), true, 512, JSON_THROW_ON_ERROR);
    $backup = json_decode((string) file_get_contents($backupFile), true, 512, JSON_THROW_ON_ERROR);
    foreach ($applied['mapping'] as $entry) {
        $item = array_values(array_filter($plan['items'], static fn(array $i): bool => $i['sourceId'] === $entry['sourceId']))[0];
        $row = $repo->findById((int) $entry['postId']);
        if (!$row || $row['status'] !== 'agendado' || $row['agendado_para'] !== $item['scheduled'] || $row['post_blog_id'] != $item['articleId'] || $row['video_rendered_path'] !== $item['video'] || $row['audio_track_id'] != $item['trackId'] || $row['audio_start_seconds'] != $item['audioStart'] || $row['audio_duration_seconds'] != 28 || $row['creation_id'] !== null || $row['ig_media_id'] !== null || $row['publicado_em'] !== null || $row['publish_phase'] !== 'idle') { throw new RuntimeException('Nova agenda divergente'); }
        $medias = $repo->findMediaByPostId((int) $row['id']);
        if (count($medias) !== 1 || $medias[0]['caminho'] !== $item['video'] || $medias[0]['duracao_s'] != 28 || App\Repositories\InstagramPostRepository::mediaRuleError('reels', ['video'], false, true) !== null || App\Services\Instagram\AudioReelGeneratorService::readyVideoPath($row,$root . '/public') !== $item['video']) { throw new RuntimeException('Midia nao pronta para publicador'); }
        $s = $pdo->prepare("SELECT COUNT(*) FROM instagram_posts WHERE account_id=1 AND status IN ('agendado','publicando','erro') AND DATE(agendado_para)=DATE(?)"); $s->execute([$item['scheduled']]);
        if ((int) $s->fetchColumn() !== 1) { throw new RuntimeException('Conflito de dia'); }
        echo 'PASS #' . $row['id'] . ': ' . $item['scheduled'] . ', origem #' . $entry['sourceId'] . PHP_EOL;
    }
    foreach ($backup['posts'] as $old) {
        if (!in_array((int) $old['id'], $plan['deletedSourceIds'], true)) { continue; }
        $now = $repo->findById((int) $old['id']);
        if (!$now || $now['status'] !== 'rascunho' || !str_contains($now['error_log'] ?? '', 'Excluido do Instagram pelo usuario')) { throw new RuntimeException('Excluido ainda publicado'); }
        unset($now['status'],$now['error_log'],$now['atualizado_em'],$old['status'],$old['error_log'],$old['atualizado_em']);
        if ($now !== $old) { throw new RuntimeException('Historico antigo alterado'); }
    }
    foreach ($backup['originalHashes'] as $path => $hash) { if (hash_file('sha256',$root . '/public/' . $path) !== $hash) { throw new RuntimeException('Video original alterado'); } }
    if (unrelatedQueue($pdo,array_merge($sourceIds,array_column($applied['mapping'],'postId'))) !== $backup['unrelated']) { throw new RuntimeException('Outros agendamentos divergentes'); }
    echo "PASS: historico preservado, quatro excluidos fora dos publicados, demais posts intactos\n";
} catch (Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
finally { flock($lock,LOCK_UN); fclose($lock); }
