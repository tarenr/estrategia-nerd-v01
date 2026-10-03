<?php

declare(strict_types=1);

use App\Repositories\InstagramPostRepository;
use App\Services\Instagram\AudioReelGeneratorService;
use App\Services\Instagram\EditorialMotionReelRenderer;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require_once $root . '/app/Support/Helpers.php';
require_once $root . '/app/Repositories/InstagramPostRepository.php';
require_once $root . '/app/Services/Instagram/AudioReelGeneratorService.php';
require_once $root . '/app/Services/Instagram/EditorialMotionReelRenderer.php';
$options = getopt('', ['pilot:', 'ffmpeg:', 'ffprobe:']);
$passed = 0;
$failed = 0;
$check = static function (string $name, bool $ok) use (&$passed, &$failed): void {
    $ok ? $passed++ : $failed++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . "\n";
};

// Banco efemero em memoria: nunca carrega bootstrap, MySQL, tokens ou integracoes.
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'));
$pdo->exec('CREATE TABLE instagram_posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER, status TEXT, tipo TEXT, legenda TEXT,
    hashtags_count INTEGER, agendado_para TEXT, post_blog_id INTEGER, audio_track_id INTEGER,
    audio_start_seconds INTEGER, audio_duration_seconds INTEGER, video_rendered_path TEXT,
    render_status TEXT, idempotency_key TEXT, origin TEXT, criado_por INTEGER, atualizado_em TEXT
)');
$pdo->exec('CREATE TABLE instagram_post_media (
    id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER, ordem INTEGER, tipo_arquivo TEXT,
    caminho TEXT, url_publica TEXT, largura INTEGER, altura INTEGER, duracao_s REAL
)');
$repo = new InstagramPostRepository($pdo);
$data = ['account_id' => 1, 'status' => 'agendado', 'tipo' => 'reels', 'legenda' => 'Original',
    'agendado_para' => '2026-10-10 12:00:00', 'audio_track_id' => 1, 'audio_start_seconds' => 0, 'audio_duration_seconds' => 16];
$id = $repo->create($data);
$media = $repo->addMedia($id, ['caminho' => 'sample.mp4', 'tipo_arquivo' => 'video', 'largura' => 1080, 'altura' => 1920]);
$repo->markRenderedReady($id, 'sample.mp4');
$data['legenda'] = 'Legenda revisada';
$repo->update($id, $data);
$post = $repo->findById($id);
$check('Edicao de legenda preserva MP4 pronto e horario', $post['video_rendered_path'] === 'sample.mp4'
    && $post['render_status'] === 'ready' && $post['agendado_para'] === $data['agendado_para']);
$data['agendado_para'] = '2026-10-11 12:00:00';
$repo->update($id, $data);
$check('Edicao de agendamento preserva renderizacao', $repo->findById($id)['render_status'] === 'ready');
foreach (['audio_track_id' => 2, 'audio_start_seconds' => 3, 'audio_duration_seconds' => 20] as $key => $value) {
    $repo->markRenderedReady($id, 'sample.mp4');
    $changed = $data;
    $changed[$key] = $value;
    $repo->update($id, $changed);
    $post = $repo->findById($id);
    $check('Mudanca em ' . $key . ' invalida cache', $post['render_status'] === 'idle' && $post['video_rendered_path'] === null);
    $repo->update($id, $data);
}
$repo->markRenderedReady($id, 'sample.mp4');
$repo->renumberMedia($id);
$check('Salvar ordem sem mudar midias preserva cache', $repo->findById($id)['render_status'] === 'ready');
$repo->updateMediaFile($media, 'sample.mp4', '/nova-url/sample.mp4', 1080, 1920);
$check('Mudanca apenas de URL nao recodifica video', $repo->findById($id)['render_status'] === 'ready');
$repo->updateMediaFile($media, 'replacement.mp4', null, 1080, 1920);
$check('Troca do arquivo de midia invalida cache', $repo->findById($id)['render_status'] === 'idle');
$repo->markRenderedReady($id, 'replacement.mp4');
$repo->updateMediaFile($media, 'replacement.mp4', null, 720, 1280);
$check('Alteracao de dimensoes invalida cache', $repo->findById($id)['render_status'] === 'idle');
$repo->markRenderedReady($id, 'replacement.mp4');
$repo->deleteMedia($media, $id + 99);
$check('Remocao de midia de outro post nao invalida cache', $repo->findById($id)['render_status'] === 'ready');
$repo->deleteMedia($media, $id);
$check('Remocao efetiva de midia invalida cache', $repo->findById($id)['render_status'] === 'idle');
$repo->markRenderedReady($id, 'sample.mp4');
$repo->addMedia($id, ['caminho' => 'another.png']);
$check('Adicao de midia invalida cache', $repo->findById($id)['render_status'] === 'idle');
$repo->markRenderedReady($id, 'sample.mp4');
$pdo->beginTransaction();
$repo->deleteMediaByPostId($id);
$check('Substituicao do conjunto invalida cache', $repo->findById($id)['render_status'] === 'idle');
$pdo->rollBack();
$check('Rollback restaura midias e cache anterior', count($repo->findMediaByPostId($id)) === 1 && $repo->findById($id)['render_status'] === 'ready');
$check('Post inexistente nao recebe atualizacao', !$repo->update(9999, $data));

$work = $root . '/storage/previews/reels-motion/verify-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
mkdir($work, 0755, true);
mkdir($work . '/public', 0755, true);
// Fixtures apenas para resolver caminhos, nao utilizadas como video real.
file_put_contents($work . '/public/sample.mp4', 'cache-fixture');
file_put_contents($work . '/public/empty.mp4', '');
file_put_contents($work . '/outside.mp4', 'outside-fixture');
$ready = ['video_rendered_path' => 'sample.mp4', 'render_status' => 'ready'];
$hashBefore = hash_file('sha256', $work . '/public/sample.mp4');
$check('MP4 pronto e existente pode ser reutilizado', AudioReelGeneratorService::readyVideoPath($ready, $work . '/public') === 'sample.mp4');
$check('Consulta de cache nao modifica arquivo', $hashBefore === hash_file('sha256', $work . '/public/sample.mp4'));
foreach ([['sample.mp4', 'idle'], ['sample.mp4', 'failed'], ['missing.mp4', 'ready'], ['empty.mp4', 'ready'], ['../outside.mp4', 'ready']] as [$path, $status]) {
    $check('Rejeita cache ' . $path . ' / ' . $status, AudioReelGeneratorService::readyVideoPath(
        ['video_rendered_path' => $path, 'render_status' => $status], $work . '/public') === null);
}

if (isset($options['pilot'])) {
    try {
        $dir = rtrim((string) $options['pilot'], '/\\');
        $renderer = new EditorialMotionReelRenderer((string) ($options['ffmpeg'] ?? 'ffmpeg'), (string) ($options['ffprobe'] ?? 'ffprobe'));
        $manifest = json_decode((string) file_get_contents($dir . '/pilot-layers/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $renderer->assertVideo($dir . '/pilot.mp4', (int) $manifest['duration']);
        $check('MP4 real: H264 1080x1920 30fps + AAC 48kHz e duracao exata', true);
        $hash = hash_file('sha256', $dir . '/pilot.mp4');
        $refusedOverwrite = false;
        try { $renderer->render('', '', [], $dir . '/pilot.mp4'); }
        catch (RuntimeException $e) { $refusedOverwrite = true; }
        $check('Renderer recusa sobrescrever piloto e preserva bytes', $refusedOverwrite && $hash === hash_file('sha256', $dir . '/pilot.mp4'));
        $spec = $renderer->probe($dir . '/pilot.mp4');
        $check('Audio incorporado uma vez: exatamente um stream de cada tipo', count($spec['streams']) === 2);
        $videoStreams = array_values(array_filter($spec['streams'], static fn (array $stream): bool => $stream['codec_type'] === 'video'));
        $check('Video entrega todos os frames previstos', (int) ($videoStreams[0]['nb_frames'] ?? 0) === (int) $manifest['duration'] * 30);
        $frames = [];
        foreach ([1, 2, 3, 4, 5] as $i) {
            $frame = imagecreatefrompng($dir . '/frame-' . $i . '.png');
            if (!$frame instanceof GdImage) { throw new RuntimeException('Frame ausente.'); }
            $check('Frame ' . $i . ': dimensoes completas', imagesx($frame) === 1080 && imagesy($frame) === 1920);
            $frames[] = $frame;
        }
        $differentPixels = static function (GdImage $a, GdImage $b, int $top, int $bottom): int {
            $count = 0;
            for ($y = $top; $y < $bottom; $y += 8) {
                for ($x = 60; $x < 1020; $x += 8) {
                    if (imagecolorat($a, $x, $y) !== imagecolorat($b, $x, $y)) { $count++; }
                }
            }
            return $count;
        };
        $check('Entrada da capa altera a cena', $differentPixels($frames[0], $frames[1], 590, 1190) > 1000);
        $check('Destaques trocam de conteudo ao longo da timeline', $differentPixels($frames[2], $frames[3], 1270, 1570) > 100);
        $check('Encerramento apresenta nova cena', $differentPixels($frames[3], $frames[4], 1270, 1570) > 100);
        foreach ($frames as $frame) { imagedestroy($frame); }
        if (isset($manifest['opening'])) {
            $openingFrames = [];
            foreach (range(1, 7) as $i) {
                $frame = imagecreatefrompng($dir . '/opening-frame-' . $i . '.png');
                if (!$frame instanceof GdImage) { throw new RuntimeException('Frame intermediario da abertura ausente.'); }
                $openingFrames[] = $frame;
            }
            // Mede o conteudo visivel do MP4 decodificado, nao apenas os parametros do filtro.
            $accent = $manifest['template_settings']['accent'] ?? [34, 211, 238];
            $bounds = static function (GdImage $frame, int $top, int $bottom, string $kind) use ($accent): ?array {
                $left = 1080; $right = -1; $first = 1920; $last = -1; $count = 0;
                for ($y = $top; $y < $bottom; $y += 2) {
                    for ($x = 60; $x < 1020; $x += 2) {
                        $rgb = imagecolorat($frame, $x, $y);
                        $r = ($rgb >> 16) & 255; $g = ($rgb >> 8) & 255; $b = $rgb & 255;
                        $match = match ($kind) {
                            'white' => $r > 170 && $g > 170 && $b > 170,
                            'cyan' => abs($r - $accent[0]) < 65 && abs($g - $accent[1]) < 65 && abs($b - $accent[2]) < 65,
                            default => max($r, $g, $b) > 100,
                        };
                        if ($match) { $left = min($left, $x); $right = max($right, $x); $first = min($first, $y); $last = max($last, $y); $count++; }
                    }
                }
                return $count === 0 ? null : ['left' => $left, 'right' => $right, 'top' => $first, 'bottom' => $last, 'count' => $count];
            };
            $lines = $manifest['opening']['title_lines'];
            $firstLine = $lines[0];
            $firstColor = count($lines) === 1 ? 'cyan' : 'white';
            $empty = $bounds($openingFrames[0], 260, 545, $firstColor);
            $partial = $bounds($openingFrames[1], $firstLine['y'], $firstLine['y'] + $firstLine['height'], $firstColor);
            $settled = $bounds($openingFrames[3], $firstLine['y'], $firstLine['y'] + $firstLine['height'], $firstColor);
            $check('Titulo realmente surge e sobe dentro da mascara', $empty === null && $partial !== null && $settled !== null
                && $partial['top'] > $settled['top'] + 3);
            if (count($lines) > 1) {
                $second = $lines[1];
                $check('Linhas seguintes aguardam sua vez', $bounds($openingFrames[1], $second['y'], 545, 'white') === null
                    && $bounds($openingFrames[1], $second['y'], 545, 'cyan') === null);
            }
            $lastLine = $lines[count($lines) - 1];
            $check('Ultima linha aparece na cor da categoria', $bounds($openingFrames[3], $lastLine['y'],
                $lastLine['y'] + $lastLine['height'], 'cyan') !== null);
            $smallCover = $bounds($openingFrames[4], 590, 1250, 'content');
            $fullCover = $bounds($openingFrames[6], 590, 1250, 'content');
            $check('Imagem cresce de forma evidente na entrada', $smallCover !== null && $fullCover !== null
                && ($fullCover['right'] - $fullCover['left']) > ($smallCover['right'] - $smallCover['left']) + 20);
            $horizontal = abs((int) ($manifest['template_settings']['cover_x'] ?? 0)) > abs((int) ($manifest['template_settings']['cover_y'] ?? 150));
            $check('Imagem se desloca ate o encaixe da categoria', $smallCover !== null && $fullCover !== null
                && ($horizontal
                    ? abs(($smallCover['left'] + $smallCover['right']) - ($fullCover['left'] + $fullCover['right'])) > 20
                    : $smallCover['top'] > $fullCover['top'] + 20));
            $check('Moldura aparece somente depois do encaixe', $bounds($openingFrames[4], 590, 612, 'cyan') === null
                && $bounds($openingFrames[6], 590, 612, 'cyan') !== null);
            foreach ($openingFrames as $frame) { imagedestroy($frame); }
            $check('Linhas do titulo mantem o texto original', implode(' ', array_column($lines, 'text')) === $manifest['titulo']);
        }
        $article = json_decode((string) file_get_contents($dir . '/article.json'), true, 512, JSON_THROW_ON_ERROR);
        $summary = trim((string) preg_replace('/\[\[(.*?)\]\]/u', '$1', html_entity_decode(strip_tags((string) $article['resumo']), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $joined = str_replace('…', '', implode('', $manifest['destaques']));
        $check('Destaques preservam o resumo exibido', preg_replace('/\s+/u', '', $joined) === preg_replace('/\s+/u', '', str_replace('…', '', $manifest['resumo'])));
        $check('Resumo exibido vem do artigo original', str_starts_with((string) preg_replace('/\s+/u', '', $summary),
            (string) preg_replace('/\s+/u', '', str_replace('…', '', $manifest['resumo']))));
        $check('Rotulos fixos removidos do resumo', ($manifest['summary_labels'] ?? ['legacy']) === []);
        $check('Template corresponde a categoria do artigo', ($manifest['category_template'] ?? '') === EditorialMotionReelRenderer::templateKey((string) $article['categoria']));
        file_put_contents($work . '/pilot-spec.json', json_encode($spec, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    } catch (Throwable $e) { $check('Verificacao do piloto: ' . $e->getMessage(), false); }
}
echo "RESULTADO: {$passed} OK / {$failed} FALHAS\nArtefatos de teste: {$work}\n";
exit($failed === 0 ? 0 : 1);
