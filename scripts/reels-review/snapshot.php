<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/backup/EnvLoader.php';
use Scripts\Backup\EnvLoader;
$root = dirname(__DIR__, 2);
EnvLoader::load($root . '/.env');
$config = require $root . '/config/content-sync.php';
$connect = static function (array $cfg): PDO {
    return new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['port'], $cfg['database']), $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
};
try {
    $local = $connect($config['profiles']['local']['database']);
    $posts = $local->query('SELECT id,status,post_blog_id,legenda,agendado_para,publicado_em,video_rendered_path,audio_track_id,audio_start_seconds,audio_duration_seconds,render_status FROM instagram_posts WHERE id BETWEEN 190 AND 219 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $remote = $connect($config['profiles']['production']['database']);
    $articles = $remote->query('SELECT p.id,p.titulo,p.slug,p.resumo,p.conteudo,p.imagem_capa,c.slug AS categoria FROM posts p LEFT JOIN categorias c ON c.id=p.categoria_id WHERE p.id BETWEEN 11 AND 40 ORDER BY p.id')->fetchAll(PDO::FETCH_ASSOC);
    $tracks = $local->query('SELECT id,titulo,arquivo_path,duracao_s,origem,origem_id,ativo FROM instagram_audio_tracks ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $dir = $root . '/storage/previews/reels-review/source';
    if (!is_dir($dir)) { mkdir($dir, 0755, true); }
    $name = $argv[1] ?? 'snapshot.json';
    if (!preg_match('/^[a-z0-9-]+\.json$/', $name)) { throw new RuntimeException('Nome de snapshot inválido'); }
    $out = $dir . '/' . $name;
    if (file_exists($out)) { throw new RuntimeException('Snapshot existente; use outro nome'); }
    $snapshot = ['posts'=>$posts,'articles'=>$articles,'tracks'=>$tracks];
    file_put_contents($out, json_encode($snapshot, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    echo json_encode(['snapshot'=>$out,'posts'=>count($posts),'articles'=>count($articles),'tracks'=>count($tracks)], JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $e) { fwrite(STDERR, 'Não foi possível obter o snapshot editorial: ' . get_class($e) . PHP_EOL); exit(1); }
