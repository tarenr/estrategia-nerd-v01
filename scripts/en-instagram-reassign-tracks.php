<?php
/**
 * -----------------------------------------------------------------------------
 * @file        scripts/en-instagram-reassign-tracks.php
 * @project     Estrategia Nerd
 * @purpose     Troca a trilha e o trecho dos Reels locais agendados usando o
 *              seletor sem repetição (IMP-032). Não renderiza vídeo: depois de
 *              aplicar, regerar com scripts/en-instagram-regenerate-motion-reels.php.
 *
 * Uso:
 *   php scripts/en-instagram-reassign-tracks.php --dry-run
 *       Monta o plano (faixa e início por Reel) e grava em storage/backups/reels-audio/.
 *   php scripts/en-instagram-reassign-tracks.php --apply=<plano.json> --sha=<sha256 do plano>
 *       Aplica exatamente o plano revisado; guarda backup dos valores atuais.
 *   php scripts/en-instagram-reassign-tracks.php --rollback=<backup.json>
 *       Devolve faixa e início gravados no backup.
 *   Acrescente --ids=190,194,... para limitar planejamento/aplicação/rollback.
 *   O modo --dry-run não escreve no banco, mas grava o plano em disco.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

use App\Services\Instagram\EditorialMotionReelRenderer;
use App\Services\Instagram\TrackPickerService;
use App\Support\TargetEnvironmentDatabase;

const JANELA_SEGUNDOS = 900; // mesma janela do lote de vídeos (MotionReelBatchService::eligibility)

/** @param array<string,mixed> $row @param array<string,mixed> $expected */
function assertAudioPost(array $row, array $expected): void
{
    $when = strtotime((string) ($row['agendado_para'] ?? ''));
    if (($row['status'] ?? '') !== 'agendado' || ($row['tipo'] ?? '') !== 'reels'
        || ($row['origin'] ?? '') !== 'local' || $when === false || $when <= time() + JANELA_SEGUNDOS
        || (string) $row['agendado_para'] !== (string) $expected['agendado_para']
        || ($row['audio_track_id'] === null ? null : (int) $row['audio_track_id']) !== $expected['audio_track_id']
        || (int) $row['audio_start_seconds'] !== (int) $expected['audio_start_seconds']
        || (string) $row['atualizado_em'] !== (string) $expected['atualizado_em']) {
        throw new RuntimeException('Reel mudou ou entrou na janela de proteção; estado preservado.');
    }
}

/** @param array<string,mixed> $data */
function saveAudioBackup(string $path, array $data): void
{
    $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $handle = @fopen($path, 'x');
    if ($handle === false) { throw new RuntimeException('Não foi possível criar backup exclusivo.'); }
    try {
        if (fwrite($handle, $content) !== strlen($content) || !fflush($handle)
            || (function_exists('fsync') && !fsync($handle))) {
            throw new RuntimeException('Backup incompleto; nada aplicado.');
        }
    } finally { fclose($handle); }
    if (hash_file('sha256', $path) !== hash('sha256', $content)) {
        throw new RuntimeException('Integridade do backup não confirmada.');
    }
}

// Permite testes isolados das mesmas guardas, sem bootstrap, banco real ou .env.
if (defined('TRACK_REASSIGN_TEST')) { return; }
require dirname(__DIR__) . '/bootstrap.php';

$opts = getopt('', ['dry-run', 'apply:', 'sha:', 'rollback:', 'ids:']);
$modes = array_intersect(['dry-run', 'apply', 'rollback'], array_keys($opts));
if (count($modes) !== 1) { fwrite(STDERR, "Escolha exatamente um modo.\n"); exit(1); }
$allowedIds = isset($opts['ids']) ? array_values(array_unique(array_map('intval', explode(',', (string) $opts['ids'])))) : [];
$pasta = base_path('storage/backups/reels-audio');
if (!is_dir($pasta) && !@mkdir($pasta, 0775, true) && !is_dir($pasta)) {
    fwrite(STDERR, "ERRO: não foi possível criar {$pasta}\n");
    exit(1);
}

/** @var PDO $db */
$db = $GLOBALS['pdo'];

if (is_string($opts['rollback'] ?? null)) {
    $backup = json_decode((string) @file_get_contents($opts['rollback']), true);
    if (!is_array($backup) || !is_array($backup['posts'] ?? null)) {
        fwrite(STDERR, "ERRO: backup inválido.\n");
        exit(1);
    }
    $upd = $db->prepare('UPDATE instagram_posts SET audio_track_id = ?, audio_start_seconds = ?, atualizado_em = ? WHERE id = ?');
    $db->beginTransaction();
    try {
        $read = $db->prepare('SELECT * FROM instagram_posts WHERE id = ? FOR UPDATE');
        foreach ($backup['posts'] as $p) {
            if ($allowedIds !== [] && !in_array((int) $p['id'], $allowedIds, true)) { throw new RuntimeException('ID fora do escopo.'); }
            $read->execute([(int) $p['id']]);
            $row = $read->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) { throw new RuntimeException('Reel ausente.'); }
            assertAudioPost($row, $p['depois']);
            $upd->execute([$p['audio_track_id'], (int) $p['audio_start_seconds'], $p['atualizado_em'], (int) $p['id']]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack(); fwrite(STDERR, 'ERRO: rollback recusado; estado preservado. ' . $e->getMessage() . "\n"); exit(1);
    }
    echo 'Rollback aplicado em ' . count($backup['posts']) . " Reels.\n";
    exit(0);
}

if (is_string($opts['apply'] ?? null)) {
    $arquivo = $opts['apply'];
    $conteudo = (string) @file_get_contents($arquivo);
    $plano = json_decode($conteudo, true);
    if (!is_array($plano) || !is_array($plano['itens'] ?? null)) {
        fwrite(STDERR, "ERRO: plano inválido.\n");
        exit(1);
    }
    if (!is_string($opts['sha'] ?? null) || !hash_equals(hash('sha256', $conteudo), strtolower($opts['sha']))) {
        fwrite(STDERR, "ERRO: --sha não confere com o plano revisado.\n");
        exit(1);
    }

    if ($plano['itens'] === []) { fwrite(STDERR, "ERRO: plano vazio.\n"); exit(1); }
    $atual = $db->prepare('SELECT * FROM instagram_posts WHERE id = ? FOR UPDATE');
    $faixa = $db->prepare('SELECT ativo, duracao_s, origem, arquivo_path, file_hash FROM instagram_audio_tracks WHERE id = ? FOR UPDATE');
    $backup = ['criado_em' => date('c'), 'plano' => basename($arquivo), 'posts' => []];
    $appliedAt = date('Y-m-d H:i:s');
    $seenPosts = []; $seenTracks = [];
    $db->beginTransaction();
    try {
    foreach ($plano['itens'] as $item) {
        $id = (int) $item['post_id']; $trackId = (int) $item['track_id'];
        if (isset($seenPosts[$id]) || isset($seenTracks[$trackId]) || !empty($item['repetida'])
            || ($allowedIds !== [] && !in_array($id, $allowedIds, true))) { throw new RuntimeException('ID duplicado, faixa repetida ou fora do escopo.'); }
        $seenPosts[$id] = true; $seenTracks[$trackId] = true;
        $atual->execute([(int) $item['post_id']]);
        $row = $atual->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) { throw new RuntimeException('Reel ausente.'); }
        assertAudioPost($row, $item['antes'] + ['agendado_para' => $item['agendado_para']]);
        $faixa->execute([(int) $item['track_id']]);
        $t = $faixa->fetch(PDO::FETCH_ASSOC);
        if (!is_array($t) || $t['origem'] !== 'pixabay' || (int) $t['ativo'] !== 1 || (int) $item['inicio'] < 0
            || (int) $item['duracao_reel'] < 1 || (int) $item['inicio'] + (int) $item['duracao_reel'] > (int) $t['duracao_s']) {
            throw new RuntimeException('Faixa não elegível.');
        }
        $audio = realpath(base_path('public/' . $t['arquivo_path']));
        $audioBase = realpath(base_path('public/uploads/audio'));
        if ($audio === false || $audioBase === false
            || !str_starts_with(str_replace('\\', '/', $audio), str_replace('\\', '/', $audioBase) . '/')
            || !hash_equals((string) $t['file_hash'], (string) hash_file('sha256', $audio))) { throw new RuntimeException('Arquivo da faixa mudou ou está ausente.'); }
        $backup['posts'][] = ['id' => (int) $row['id'], 'audio_track_id' => $row['audio_track_id'] === null ? null : (int) $row['audio_track_id'],
            'audio_start_seconds' => (int) $row['audio_start_seconds'], 'atualizado_em' => $row['atualizado_em'],
            'depois' => ['audio_track_id' => $trackId, 'audio_start_seconds' => (int) $item['inicio'],
                'agendado_para' => $row['agendado_para'], 'atualizado_em' => $appliedAt]];
    }

    $arqBackup = $pasta . '/reassign-backup-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
    saveAudioBackup($arqBackup, $backup);

    $upd = $db->prepare("UPDATE instagram_posts SET audio_track_id = ?, audio_start_seconds = ?, atualizado_em = ? WHERE id = ? AND status = 'agendado'");
        foreach ($plano['itens'] as $item) {
            $upd->execute([(int) $item['track_id'], (int) $item['inicio'], $appliedAt, (int) $item['post_id']]);
            if ($upd->rowCount() !== 1) { throw new RuntimeException('Atualização não confirmada.'); }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        fwrite(STDERR, 'ERRO: ' . $e->getMessage() . "\n");
        exit(1);
    }
    echo 'Aplicado em ' . count($plano['itens']) . " Reels. Backup: {$arqBackup}\n";
    echo "Próximo passo: regerar os vídeos com scripts/en-instagram-regenerate-motion-reels.php.\n";
    exit(0);
}

if (!array_key_exists('dry-run', $opts)) {
    fwrite(STDERR, "Use --dry-run, --apply=<plano> --sha=<hash> ou --rollback=<backup>.\n");
    exit(1);
}

// --dry-run: monta o plano sem gravar no banco.
$posts = $db->query("SELECT id, post_blog_id, agendado_para, audio_track_id, audio_start_seconds, atualizado_em
                       FROM instagram_posts
                      WHERE status = 'agendado' AND tipo = 'reels' AND origin = 'local' AND post_blog_id IS NOT NULL
                      ORDER BY agendado_para, id")->fetchAll(PDO::FETCH_ASSOC);
$posts = array_values(array_filter($posts, static fn (array $p): bool => $allowedIds === [] || in_array((int) $p['id'], $allowedIds, true)));

$prod = TargetEnvironmentDatabase::pdo('production');
$artigo = $prod->prepare('SELECT id, titulo, resumo, categoria FROM posts WHERE id = ?');
$renderer = new EditorialMotionReelRenderer();
$picker = new TrackPickerService($db, array_map(static fn (array $p): int => (int) $p['id'], $posts));

$itens = [];
$pulados = [];
foreach ($posts as $p) {
    $quando = strtotime((string) $p['agendado_para']);
    if ($quando === false || $quando <= time() + JANELA_SEGUNDOS) {
        $pulados[] = "#{$p['id']} ({$p['agendado_para']}): a menos de 15 minutos ou vencido";
        continue;
    }
    $artigo->execute([(int) $p['post_blog_id']]);
    $a = $artigo->fetch(PDO::FETCH_ASSOC);
    if (!is_array($a)) {
        $pulados[] = "#{$p['id']}: artigo de origem ausente em produção";
        continue;
    }
    $categoria = strtolower(trim((string) $a['categoria'])) ?: 'cultura';
    $segundos = $renderer->duration($a);
    $escolha = $picker->pick($categoria, $segundos, $a);
    if ($escolha === null || $escolha['reused']) {
        $pulados[] = "#{$p['id']}: " . ($picker->warning() ?? 'Nenhuma faixa temática compatível sem uso disponível');
        continue;
    }
    $itens[] = [
        'post_id' => (int) $p['id'],
        'agendado_para' => (string) $p['agendado_para'],
        'categoria' => $categoria,
        'duracao_reel' => $segundos,
        'track_id' => (int) $escolha['track']['id'],
        'faixa' => $escolha['track']['titulo'] . ' — ' . $escolha['track']['artista'],
        'grupo' => $escolha['track']['genero'],
        'inicio' => $escolha['start'],
        'repetida' => $escolha['reused'],
        'antes' => ['audio_track_id' => $p['audio_track_id'] === null ? null : (int) $p['audio_track_id'],
            'audio_start_seconds' => (int) $p['audio_start_seconds'], 'atualizado_em' => $p['atualizado_em']],
    ];
}

foreach ($itens as $i) {
    printf("#%-4d %s %-9s %2ds -> faixa #%-3d %-9s início %3ds %s\n", $i['post_id'], substr($i['agendado_para'], 0, 16),
        $i['categoria'], $i['duracao_reel'], $i['track_id'], $i['grupo'], $i['inicio'], mb_substr($i['faixa'], 0, 45));
}
foreach ($pulados as $msg) {
    echo "PULADO {$msg}\n";
}

$distintas = count(array_unique(array_column($itens, 'track_id')));
$conteudo = (string) json_encode(['criado_em' => date('c'), 'itens' => $itens], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$arqPlano = $pasta . '/reassign-plan-' . date('Ymd-His') . '.json';
saveAudioBackup($arqPlano, ['criado_em' => date('c'), 'itens' => $itens]);
$conteudo = (string) file_get_contents($arqPlano);
printf("\n%d Reels, %d faixas distintas, %d pulados.\nPlano: %s\nSHA-256: %s\n", count($itens), $distintas, count($pulados), $arqPlano, hash('sha256', $conteudo));
