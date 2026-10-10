<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/backup/EnvLoader.php';
$root = dirname(__DIR__, 2);
Scripts\Backup\EnvLoader::load($root . '/.env');
$cfg = require $root . '/config/content-sync.php';
$connect = static function (array $c): PDO { return new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['database']), $c['username'], $c['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); };
$db = $connect($cfg['profiles']['local']['database']);
$production = $connect($cfg['profiles']['production']['database']);
$dir = $root . '/storage/correcao-instagram-20261010';
$ids = [1,...range(190,219),224,225,226,227,229,230,231,232,233,234];
$where = implode(',', $ids);
$action = $argv[1] ?? '';
if ($action === '--draft-source') {
    $s=$db->prepare('SELECT id,titulo,slug,conteudo FROM posts WHERE id=(SELECT post_blog_id FROM instagram_posts WHERE id=1)');$s->execute();$article=$s->fetch();
    if (!$article) { throw new RuntimeException('Origem do rascunho indisponível.'); }
    echo json_encode(['id'=>(int)$article['id'],'titulo'=>$article['titulo'],'slug'=>$article['slug'],'sha256'=>hash('sha256',$article['conteudo'])],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;exit;
}
if ($action === '--backup') {
    if (file_exists($dir . '/before.json')) { echo "Backup existente preservado.\n"; exit; }
    $posts = $db->query("SELECT * FROM instagram_posts WHERE id IN ($where) ORDER BY id")->fetchAll();
    $media = $db->query("SELECT * FROM instagram_post_media WHERE post_id IN ($where) ORDER BY post_id,ordem")->fetchAll();
    if (count($posts)!==41 || array_filter($posts, static fn(array $p):bool=>$p['status']==='publicando')) { throw new RuntimeException('Escopo incompleto ou publicação em andamento.'); }
    mkdir($dir,0700,true);
    $articles = $production->query('SELECT * FROM posts WHERE id BETWEEN 11 AND 40')->fetchAll();
    $localArticles = $db->query('SELECT * FROM posts WHERE id IN (77,78,79,80)')->fetchAll();
    $tracks = $db->query('SELECT id,titulo,arquivo_path,duracao_s,origem,origem_id,ativo FROM instagram_audio_tracks')->fetchAll();
    $before = ['posts'=>$posts,'media'=>$media,'productionArticles'=>$articles,'localArticles'=>$localArticles,'tracks'=>$tracks];
    file_put_contents($dir.'/before.json',json_encode($before,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    $paths = array_column($media,'caminho');
    foreach ($posts as $p) { if ($p['video_rendered_path']) { $paths[]=$p['video_rendered_path']; } }
    $paths = array_unique($paths); $files = [];
    foreach ($paths as $path) {
        if (!is_string($path) || !str_starts_with($path,'uploads/') || str_contains($path,'..')) { continue; }
        $source=$root.'/public/'.$path;
        if (!is_file($source)) { continue; }
        $target=$dir.'/media/'.$path; if (!is_dir(dirname($target))) { mkdir(dirname($target),0700,true); }
        if (!copy($source,$target) || hash_file('sha256',$source)!==hash_file('sha256',$target)) { throw new RuntimeException('Backup de mídia incompleto.'); }
        $files[$path]=hash_file('sha256',$source);
    }
    file_put_contents($dir.'/files.json',json_encode($files,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    echo json_encode(['posts'=>count($posts),'media'=>count($media),'files'=>count($files),'backup'=>$dir]).PHP_EOL;
    exit;
}
if ($action === '--article') {
    if (!is_file($dir.'/before.json')) { throw new RuntimeException('Backup obrigatório.'); }
    $production->beginTransaction();
    try {
        $statement=$production->prepare('SELECT titulo,slug,conteudo FROM posts WHERE id=17 FOR UPDATE');
        $statement->execute();$current=$statement->fetch();
        if (!$current || $current['slug']!=='a-lore-completa-de-diablo-entenda-toda-a-historia-do-universo'
            || !str_contains($current['conteudo'],'Tathamet')) { throw new RuntimeException('Origem de Diablo divergente.'); }
        $title='A Lore Completa de Diablo: Entenda Toda a História do Universo';
        if ($current['titulo']!==$title) {
            $before=json_decode((string) file_get_contents($dir.'/before.json'),true,512,JSON_THROW_ON_ERROR);
            $expected=array_values(array_filter($before['productionArticles'],static fn(array $p):bool=>(int)$p['id']===17))[0];
            if ($current['titulo']!==$expected['titulo'] || $current['conteudo']!==$expected['conteudo']) { throw new RuntimeException('Artigo editado após o backup.'); }
            $production->prepare('UPDATE posts SET titulo=? WHERE id=17 AND slug=?')->execute([$title,$current['slug']]);
        }
        $production->commit();
        echo "Título de Diablo alinhado ao conteúdo; slug, SEO, categoria e corpo preservados.\n";
    } catch (Throwable $e) { if ($production->inTransaction()) { $production->rollBack(); } throw $e; }
    exit;
}
if ($action === '--align-existing') {
    require $root.'/vendor/autoload.php';
    $before=json_decode((string) file_get_contents($dir.'/before.json'),true,512,JSON_THROW_ON_ERROR);
    $repository=new App\Repositories\InstagramPostRepository($db);
    $db->beginTransaction();
    try {
        foreach ([205,216,217,218,219] as $id) {
            $s=$db->prepare('SELECT * FROM instagram_posts WHERE id=? FOR UPDATE');$s->execute([$id]);$post=$s->fetch();
            $expected=array_values(array_filter($before['posts'],static fn(array $p):bool=>(int)$p['id']===$id))[0];
            if (!$post || $post['status']!==$expected['status'] || $post['agendado_para']!==$expected['agendado_para']
                || $post['video_rendered_path']!==$expected['video_rendered_path'] || $post['render_status']!=='ready') {
                throw new RuntimeException('Fila mudou após o backup; sincronização interrompida.');
            }
            $path=$post['video_rendered_path'];
            if (!is_string($path) || !str_starts_with($path,'uploads/') || str_contains($path,'..') || !is_file($root.'/public/'.$path)) {
                throw new RuntimeException('Cache vigente indisponível.');
            }
            $hashes=json_decode((string) file_get_contents($dir.'/files.json'),true,512,JSON_THROW_ON_ERROR);
            if (hash_file('sha256',$root.'/public/'.$path)!==($hashes[$path]??null)) { throw new RuntimeException('Cache mudou após o backup.'); }
            $s=$db->prepare('SELECT * FROM instagram_post_media WHERE post_id=? ORDER BY ordem FOR UPDATE');$s->execute([$id]);$media=$s->fetchAll();
            if (count($media)!==1 || $media[0]['tipo_arquivo']!=='video') { throw new RuntimeException('Composição de mídia divergente.'); }
            $repository->updateMediaFile((int)$media[0]['id'],$path,null,1080,1920);
            $repository->markRenderedReady($id,$path);
        }
        $db->commit();echo "5 referências alinhadas ao cache vigente; vídeos e agendamentos preservados.\n";
    } catch (Throwable $e) { if ($db->inTransaction()) { $db->rollBack(); } throw $e; }
    exit;
}
if ($action === '--verify-state') {
    $before=json_decode((string) file_get_contents($dir.'/before.json'),true,512,JSON_THROW_ON_ERROR);
    $current=$db->query("SELECT * FROM instagram_posts WHERE id IN ($where) ORDER BY id")->fetchAll();
    foreach ($current as $index=>$post) {
        $expected=$before['posts'][$index];
        foreach ($post as $key=>$value) {
            if ($value!==$expected[$key] && !(in_array((int)$post['id'],[205,216,217,218,219],true) && $key==='atualizado_em')) {
                throw new RuntimeException('Registro '.$post['id'].' divergiu no campo '.$key.'.');
            }
        }
    }
    $media=$db->query("SELECT * FROM instagram_post_media WHERE post_id IN ($where) ORDER BY post_id,ordem")->fetchAll();
    foreach ($media as $m) {
        $expected=array_values(array_filter($before['media'],static fn(array $p):bool=>(int)$p['id']===(int)$m['id']))[0];
        if (in_array((int)$m['post_id'],[205,216,217,218,219],true)) {
            $post=array_values(array_filter($current,static fn(array $p):bool=>(int)$p['id']===(int)$m['post_id']))[0];
            if ($m['caminho']!==$post['video_rendered_path'] || (int)$m['largura']!==1080 || (int)$m['altura']!==1920) { throw new RuntimeException('Mídia e cache divergentes.'); }
        } elseif ($m!==$expected) { throw new RuntimeException('Mídia fora da sincronização alterada.'); }
    }
    $p=$production->query('SELECT titulo,conteudo FROM posts WHERE id=17')->fetch();
    $expected=array_values(array_filter($before['productionArticles'],static fn(array $a):bool=>(int)$a['id']===17))[0];
    if ($p['titulo']!=='A Lore Completa de Diablo: Entenda Toda a História do Universo' || $p['conteudo']!==$expected['conteudo']) { throw new RuntimeException('Artigo divergente.'); }
    file_put_contents($dir.'/database-validation.json',json_encode(['postsUnchanged'=>41,'alignedMedia'=>5,'publishedAndCancelledPreserved'=>true,'article17TitleCorrected'=>true,'article17BodyPreserved'=>true],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo "PASS: 41 registros preservados, 5 mídias alinhadas, artigo 17 validado.\n";exit;
}
throw new RuntimeException('Use --backup|--article|--align-existing|--verify-state.');
