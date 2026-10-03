<?php

declare(strict_types=1);

use App\Services\Instagram\AudioReelGeneratorService;
use App\Services\Instagram\EditorialMotionReelRenderer;

// Nao usa bootstrap: nao abre sessao, nao inicia integracoes e nao publica.
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
$options = getopt('', ['post-id:', 'article:', 'audio:', 'audio-start:', 'list', 'help', 'ffmpeg:', 'ffprobe:']);
if (isset($options['help']) || $options === []) {
    echo "Piloto separado; somente SELECT no banco local, sem publicar ou substituir cadastros.\n"
        . "php scripts/en-instagram-preview-motion-reel.php --list\n"
        . "php scripts/en-instagram-preview-motion-reel.php --post-id=ID [--audio=uploads/audio/faixa.mp3]\n"
        . "php scripts/en-instagram-preview-motion-reel.php --article=artigo.json --audio=uploads/audio/faixa.mp3\n"
        . "JSON: titulo, resumo, categoria, imagem_capa. Assets devem existir localmente.\n";
    exit(0);
}
try {
    $pdo = null;
    if (isset($options['post-id']) || isset($options['list'])) {
        // Apenas credenciais locais; nao exibe valores e nao carrega tokens de terceiros.
        foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (preg_match('/^(DB_[A-Z_]+)=(.*)$/', trim($line), $match)) {
                $_ENV[$match[1]] = trim($match[2], "\"'");
            }
        }
        $db = require $root . '/config/database.php';
        if (!in_array((string) $db['host'], ['localhost', '127.0.0.1', '::1'], true)) {
            throw new RuntimeException('O piloto aceita somente banco local.');
        }
        $pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']),
            $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('SET TRANSACTION READ ONLY');
        $pdo->beginTransaction();
        if (isset($options['list'])) {
            foreach ($pdo->query('SELECT id, titulo, imagem_capa FROM posts WHERE imagem_capa IS NOT NULL ORDER BY id DESC LIMIT 30')->fetchAll() as $post) {
                if (is_file($root . '/public/' . ltrim((string) $post['imagem_capa'], '/'))) {
                    echo $post['id'] . ' | ' . $post['titulo'] . "\n";
                }
            }
            $pdo->rollBack();
            exit(0);
        }
        $stmt = $pdo->prepare('SELECT id, titulo, resumo, categoria, imagem_capa FROM posts WHERE id = :id');
        $stmt->execute([':id' => (int) $options['post-id']]);
        $article = $stmt->fetch();
        if (!is_array($article)) { throw new RuntimeException('Artigo local nao encontrado.'); }
        if (!isset($options['audio'])) {
            $track = $pdo->query('SELECT arquivo_path FROM instagram_audio_tracks WHERE ativo = 1 ORDER BY id LIMIT 1')->fetch();
            if (!is_array($track)) { throw new RuntimeException('Informe uma trilha local com --audio.'); }
            $options['audio'] = $track['arquivo_path'];
        }
        $pdo->rollBack();
    } elseif (isset($options['article'])) {
        $article = json_decode((string) file_get_contents((string) $options['article']), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($article)) { throw new RuntimeException('JSON de artigo invalido.'); }
    } else { throw new RuntimeException('Informe --post-id ou --article.'); }

    $asset = static function (string $path) use ($root): string {
        if (preg_match('~^[a-zA-Z]:[/\\\\]~', $path) || str_starts_with($path, '/')) { return $path; }
        return $root . '/public/' . $path;
    };
    $audio = $asset((string) ($options['audio'] ?? ''));
    $cover = $asset((string) ($article['imagem_capa'] ?? ''));
    if (!is_file($audio) || !is_file($cover)) { throw new RuntimeException('Capa e trilha precisam existir localmente.'); }
    $dir = $root . '/storage/previews/reels-motion/pilot-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    $ffmpeg = (string) ($options['ffmpeg'] ?? 'ffmpeg');
    $ffprobe = (string) ($options['ffprobe'] ?? 'ffprobe');
    $service = new AudioReelGeneratorService($ffmpeg, $ffprobe, $root . '/public');
    echo "Renderizando piloto em: {$dir}\n";
    $started = microtime(true);
    $result = $service->generateEditorialReel($cover, $audio, $article, (int) ($options['audio-start'] ?? 0), null, $dir . '/pilot.mp4', true);
    $renderer = new EditorialMotionReelRenderer($ffmpeg, $ffprobe);
    $times = [0.75, 3.5, 6.5, ($result['duration'] + 2) / 2 + 1, $result['duration'] - 1.5];
    $sheet = imagecreatetruecolor(270 * count($times), 480);
    foreach ($times as $i => $time) {
        $frame = $dir . '/frame-' . ($i + 1) . '.png';
        $renderer->capture($dir . '/pilot.mp4', $time, $frame);
        $image = imagecreatefrompng($frame);
        if (!$image instanceof GdImage || !$sheet instanceof GdImage) { throw new RuntimeException('Falha ao montar comparacao de frames.'); }
        imagecopyresampled($sheet, $image, $i * 270, 0, 0, 0, 270, 480, 1080, 1920);
        imagedestroy($image);
    }
    imagepng($sheet, $dir . '/contact-sheet.png'); imagedestroy($sheet);
    $manifest = json_decode((string) file_get_contents($dir . '/pilot-layers/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    $opening = $manifest['opening'];
    $lines = $opening['title_lines'];
    $openingTimes = [max(0, $lines[0]['start'] - 0.04), $lines[0]['start'] + min(0.3, $manifest['template_settings']['title_step'] / 2),
        $lines[count($lines) - 1]['start'] + 0.3, $opening['title_end'] + 0.05,
        $opening['cover_start'] + 0.22, $opening['cover_start'] + 0.6, $opening['hud_start'] + 0.5];
    $openingSheet = imagecreatetruecolor(270 * count($openingTimes), 480);
    foreach ($openingTimes as $i => $time) {
        $frame = $dir . '/opening-frame-' . ($i + 1) . '.png';
        $renderer->capture($dir . '/pilot.mp4', $time, $frame);
        $image = imagecreatefrompng($frame);
        if (!$image instanceof GdImage || !$openingSheet instanceof GdImage) { throw new RuntimeException('Falha ao montar frames da abertura.'); }
        imagecopyresampled($openingSheet, $image, $i * 270, 0, 0, 0, 270, 480, 1080, 1920);
        imagedestroy($image);
    }
    imagepng($openingSheet, $dir . '/opening-sheet.png'); imagedestroy($openingSheet);
    file_put_contents($dir . '/article.json', json_encode($article, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    $elapsed = round(microtime(true) - $started, 2);
    file_put_contents($dir . '/validation.json', json_encode(['duration' => $result['duration'], 'render_seconds' => $elapsed,
        'frames_at_seconds' => $times, 'opening_frames_at_seconds' => $openingTimes,
        'source_audio_sha256' => hash_file('sha256', $audio), 'source_cover_sha256' => hash_file('sha256', $cover),
        'spec' => $renderer->probe($dir . '/pilot.mp4')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo "PILOTO PRONTO: {$dir}/pilot.mp4\nDURACAO: {$result['duration']} s | RENDER: {$elapsed} s\n"
        . "COMPARACAO: {$dir}/contact-sheet.png\nABERTURA: {$dir}/opening-sheet.png\nNenhum cadastro foi alterado; nenhum Reel foi publicado.\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    // Nao imprime erros de conexao que possam incluir detalhes das credenciais.
    fwrite(STDERR, $e instanceof PDOException ? "Falha ao ler o banco local.\n" : $e->getMessage() . "\n");
    exit(1);
}
