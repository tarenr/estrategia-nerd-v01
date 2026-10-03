<?php

declare(strict_types=1);

use App\Services\Instagram\BlogCrosspostService;
use App\Services\Instagram\EditorialMotionReelRenderer;
use App\Services\Instagram\MotionReelBatchService;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
date_default_timezone_set('America/Sao_Paulo');
$root = dirname(__DIR__);
require_once $root . '/app/Support/Helpers.php';
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) {
        $path = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($path)) { require_once $path; }
    }
});
$options = getopt('', ['pilot:', 'ffmpeg:', 'ffprobe:']);
$passed = 0; $failed = 0;
$check = static function (string $name, bool $ok) use (&$passed, &$failed): void {
    $ok ? $passed++ : $failed++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . "\n";
};
$rejects = static function (callable $call): bool { try { $call(); return false; } catch (RuntimeException $e) { return true; } };
// Nunca conecta MySQL, carrega .env ou chama APIs: dois bancos efemeros independentes.
$local = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$origin = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$local->exec('CREATE TABLE instagram_posts(id INTEGER PRIMARY KEY,status TEXT,tipo TEXT,origin TEXT,
    legenda TEXT,agendado_para TEXT,post_blog_id INTEGER,idempotency_key TEXT,audio_track_id INTEGER,
    audio_start_seconds INTEGER,audio_duration_seconds INTEGER,video_rendered_path TEXT,render_status TEXT,atualizado_em TEXT)');
$local->exec('CREATE TABLE instagram_post_media(id INTEGER PRIMARY KEY,post_id INTEGER,ordem INTEGER,tipo_arquivo TEXT,
    caminho TEXT,url_publica TEXT,largura INTEGER,altura INTEGER,duracao_s INTEGER)');
$local->exec('CREATE TABLE instagram_audio_tracks(id INTEGER PRIMARY KEY,arquivo_path TEXT)');
$origin->exec('CREATE TABLE posts(id INTEGER PRIMARY KEY,titulo TEXT,resumo TEXT,categoria TEXT,imagem_capa TEXT)');
$work = $root . '/storage/previews/reels-motion/test-batch-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
mkdir($work . '/public/uploads', 0755, true);
mkdir($work . '/config', 0755, true);
copy($root . '/config/instagram-motion-templates.php', $work . '/config/instagram-motion-templates.php');
$cover = imagecreatetruecolor(100, 100);
if (!$cover instanceof GdImage) { throw new RuntimeException('GD indisponivel.'); }
imagefill($cover, 0, 0, (int) imagecolorallocate($cover, 34, 211, 238));
imagepng($cover, $work . '/public/uploads/cover.png'); imagedestroy($cover);
file_put_contents($work . '/public/uploads/audio.mp3', 'source-fixture-not-an-actual-track');
file_put_contents($work . '/public/uploads/old.mp4', 'old-media-preserved');
$local->exec("INSERT INTO instagram_audio_tracks VALUES(7,'uploads/audio.mp3')");
$key = BlogCrosspostService::idempotencyKey('production', 27);
$stmt = $local->prepare("INSERT INTO instagram_posts VALUES(1,'agendado','reels','local','Legenda original',?,27,?,7,0,12,'uploads/old.mp4','ready','2026-01-01 10:00:00')");
$stmt->execute([date('Y-m-d H:i:s', time() + 86400), $key]);
$local->exec("INSERT INTO instagram_post_media VALUES(3,1,0,'video','uploads/old.mp4','https://example.test/old.mp4',1080,1920,12)");
$origin->exec("INSERT INTO posts VALUES(27,'Artigo original','Resumo original para testar.','hardware','uploads/cover.png')");
$renderer = new EditorialMotionReelRenderer((string) ($options['ffmpeg'] ?? 'ffmpeg'), (string) ($options['ffprobe'] ?? 'ffprobe'));
$batch = new MotionReelBatchService($local, $renderer, $work);
$before = $batch->snapshot(1);
$item = ['id' => 1, 'before' => $before, 'state' => 'ready', 'environment' => 'production',
    'article' => $origin->query('SELECT * FROM posts')->fetch(PDO::FETCH_ASSOC),
    'track' => ['id' => 7, 'arquivo_path' => 'uploads/audio.mp3'], 'audio_start' => 0, 'duration' => 17,
    'template' => 'hardware', 'source_cover_sha256' => hash_file('sha256', $work . '/public/uploads/cover.png'),
    'previous_media_sha256' => hash_file('sha256', $work . '/public/uploads/old.mp4'),
    'source_audio_sha256' => hash_file('sha256', $work . '/public/uploads/audio.mp3')];
$batch->assertSources($item); $batch->assertArticle($origin, $item);
$check('Vinculo de producao e fontes individuais aceitos', true);
$wrong = $item; $wrong['article']['id'] = 28;
$check('Rejeita capa/artigo de outro registro', $rejects(static fn () => $batch->assertSources($wrong)));
$wrong = $item; $wrong['environment'] = 'local';
$check('Nao confunde IDs iguais entre ambientes', $rejects(static fn () => $batch->assertSources($wrong)));
$wrong = $item; $wrong['audio_start'] = 5;
$check('Nao desloca inicio da musica silenciosamente', $rejects(static fn () => $batch->assertSources($wrong)));
$local->exec("UPDATE instagram_audio_tracks SET arquivo_path='uploads/other.mp3' WHERE id=7");
$check('Troca de arquivo no cadastro da trilha impede uso de audio antigo', $rejects(static fn () => $batch->assertSources($item)));
$local->exec("UPDATE instagram_audio_tracks SET arquivo_path='uploads/audio.mp3' WHERE id=7");
$origin->exec("UPDATE posts SET titulo='Editado' WHERE id=27");
$check('Edicao do artigo original exige novo inventario', $rejects(static fn () => $batch->assertArticle($origin, $item)));
$origin->exec("UPDATE posts SET titulo='Artigo original' WHERE id=27");
file_put_contents($work . '/public/uploads/cover.png', 'changed-cover');
$check('Mudanca nos bytes da capa invalida fontes', $rejects(static fn () => $batch->assertSources($item)));
$image = imagecreatetruecolor(100, 100);
if (!$image instanceof GdImage) { throw new RuntimeException('GD indisponivel.'); }
imagefill($image, 0, 0, (int) imagecolorallocate($image, 34, 211, 238)); imagepng($image, $work . '/public/uploads/cover.png'); imagedestroy($image);
foreach (['../outside.mp4', 'https://example.test/video.mp4', 'C:/outside.mp4', '/outside.mp4'] as $path) {
    $check('Rejeita asset externo ' . $path, $rejects(static fn () => $batch->asset($path)));
}
foreach (['publicado', 'publicando', 'erro'] as $status) {
    $post = $before['post']; $post['status'] = $status;
    $check('Preserva status ' . $status, $rejects(static fn () => MotionReelBatchService::eligibility($post)));
}
$post = $before['post']; $post['agendado_para'] = date('Y-m-d H:i:s', time() + 120);
$check('Preserva agendamento iminente sem pausar publicador', $rejects(static fn () => MotionReelBatchService::eligibility($post)));
$wrongManifest = ['renderer' => 'obsolete', 'config_sha256' => hash_file('sha256', $work . '/config/instagram-motion-templates.php')];
$check('Retomada recusa versao/configuracao diferente', $rejects(static fn () => $batch->assertVersion($wrongManifest)));
$check('Categorias reais e desconhecidas recebem template correto', EditorialMotionReelRenderer::templateKey(' Games ') === 'games'
    && EditorialMotionReelRenderer::templateKey('') === 'editorial' && EditorialMotionReelRenderer::templateKey('outra') === 'editorial');
$check('Quatro templates possuem movimentos distintos', count(array_unique(array_map(static fn (string $c): string => json_encode(EditorialMotionReelRenderer::template($c), JSON_THROW_ON_ERROR), ['hardware', 'games', 'dicas', 'editorial']))) === 4);
if (isset($options['pilot'])) {
    try {
        copy((string) $options['pilot'], $work . '/public/uploads/new.mp4');
        $spec = $renderer->probe($work . '/public/uploads/new.mp4');
        $item['duration'] = (int) round((float) $spec['format']['duration']);
        $item['video'] = 'uploads/new.mp4'; $item['video_sha256'] = hash_file('sha256', $work . '/public/uploads/new.mp4');
        $item['apply_time'] = date('Y-m-d H:i:s');
        $after = $batch->applyItem($item);
        $check('Aplica MP4 real pronto com ID da midia preservado', $after === $batch->expectedAfter($item));
        foreach (['legenda', 'agendado_para', 'id', 'post_blog_id', 'idempotency_key', 'audio_track_id', 'audio_start_seconds', 'status'] as $field) {
            $check('Aplicacao preserva ' . $field, $after['post'][$field] === $before['post'][$field]);
        }
        $check('Arquivo antigo preservado', file_get_contents($work . '/public/uploads/old.mp4') === 'old-media-preserved');
        $hash = hash_file('sha256', $work . '/public/uploads/new.mp4'); $mtime = filemtime($work . '/public/uploads/new.mp4');
        $local->beginTransaction();
        // Snapshot do item aplicado exercita retomada validada sem reencode.
        $resume = $item; $resume['before'] = $after;
        $batch->prepareItem($resume, 'batch-20261003-120000-12345678');
        $local->rollBack();
        $check('Retomada conserva hash e data do MP4 pronto', $hash === hash_file('sha256', $work . '/public/uploads/new.mp4') && $mtime === filemtime($work . '/public/uploads/new.mp4'));
        $check('Reaplicacao de snapshot antigo recusada', $rejects(static fn () => $batch->applyItem($item)));
        $local->exec("UPDATE instagram_posts SET legenda='Edicao posterior' WHERE id=1");
        $check('Rollback preserva edicao posterior', $rejects(static fn () => $batch->rollbackItem($item, $after)));
        $local->exec("UPDATE instagram_posts SET legenda='Legenda original' WHERE id=1");
        $local->exec("UPDATE instagram_posts SET status='publicando' WHERE id=1");
        $check('Rollback recusa registro em publicacao', $rejects(static fn () => $batch->rollbackItem($item, $after)));
        $local->exec("UPDATE instagram_posts SET status='agendado' WHERE id=1");
        $batch->rollbackItem($item, $after);
        $check('Rollback recupera exatamente campos e referencias anteriores', $batch->snapshot(1) === $before);
        $local->exec("CREATE TRIGGER fail_update BEFORE UPDATE ON instagram_posts BEGIN SELECT RAISE(ABORT,'forced-test-failure'); END");
        try { $batch->applyItem($item); } catch (Throwable $e) {}
        $check('Falha SQL desfaz tambem alteracao da midia', $batch->snapshot(1) === $before && !$local->inTransaction());
        $local->exec('DROP TRIGGER fail_update');
        $badVideo = $item; $badVideo['video_sha256'] = str_repeat('0', 64);
        $check('MP4 alterado nao e aplicado', $rejects(static fn () => $batch->applyItem($badVideo)));
        file_put_contents($work . '/public/uploads/old.mp4', 'old-media-changed');
        $check('Midia antiga alterada impede troca sem backup recuperavel', $rejects(static fn () => $batch->applyItem($item)));
        file_put_contents($work . '/public/uploads/old.mp4', 'old-media-preserved');
        // Usa o audio real incorporado no piloto como fonte da fixture, sem biblioteca do projeto.
        copy((string) $options['pilot'], $work . '/public/uploads/audio.mp3');
        $fresh = $item; $fresh['state'] = 'inventoried';
        $fresh['source_audio_sha256'] = hash_file('sha256', $work . '/public/uploads/audio.mp3');
        $prepared = $batch->prepareItem($fresh, 'batch-20261003-120000-12345678');
        $check('Prepare real gera novo MP4 e contato visual sem alterar banco', $prepared['state'] === 'ready'
            && is_file($work . '/public/' . $prepared['contact_sheet']) && $batch->snapshot(1) === $before);
        $resumeHash = hash_file('sha256', $batch->asset($prepared['video']));
        $resumed = $batch->prepareItem($prepared, 'batch-20261003-120000-12345678');
        $check('Retomar prepare reutiliza exatamente o video ja validado', $resumed === $prepared
            && $resumeHash === hash_file('sha256', $batch->asset($prepared['video'])));
        MotionReelBatchService::save($work . '/manifest.json', ['item' => $prepared]);
        MotionReelBatchService::save($work . '/manifest.json', ['item' => $resumed, 'retomado' => true]);
        $check('Gravacao atomica permite atualizar manifesto existente', json_decode((string) file_get_contents($work . '/manifest.json'), true)['retomado'] === true);
        $check('Inicio inviavel da faixa e recusado antes de criar saida', $rejects(static fn () => $renderer->render(
            $work . '/public/uploads/cover.png', $work . '/public/uploads/audio.mp3', $fresh['article'], $work . '/public/uploads/invalid.mp4', 1000))
            && !file_exists($work . '/public/uploads/invalid.mp4'));
    } catch (Throwable $e) { $check('Integracao MP4/banco efemero: ' . $e->getMessage(), false); }
}
echo "RESULTADO: {$passed} OK / {$failed} FALHAS\nArtefatos: {$work}\n";
exit($failed === 0 ? 0 : 1);
