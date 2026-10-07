<?php
/**
 * -----------------------------------------------------------------------------
 * @file        scripts/en-instagram-import-tracks.php
 * @project     Estrategia Nerd
 * @purpose     Cadastra na biblioteca de trilhas dos Reels as faixas do Pixabay
 *              listadas no manifesto (IMP-032)
 *
 * Uso:
 *   php scripts/en-instagram-import-tracks.php [--manifest=docs/IMP-032-faixas-pixabay.json] [--dir=PASTA] [--dry-run]
 *
 * Para cada faixa do manifesto, procura na pasta de downloads o arquivo
 * "*-<id>.mp3", mede a duração com ffprobe, calcula o SHA-256, copia para
 * public/uploads/audio/pixabay_<id>.mp3 e grava em instagram_audio_tracks
 * (origem 'pixabay'). Faixa já cadastrada (mesmo id ou mesmo hash) é pulada.
 * --dry-run não copia nem grava nada.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/bootstrap.php';

const GRUPOS = ['synthwave', 'epic', 'chiptune', 'lofi', 'upbeat'];
const DURACAO_MINIMA = 75;

$opts = getopt('', ['manifest::', 'dir::', 'dry-run']);
$dryRun = array_key_exists('dry-run', $opts);
$manifestPath = is_string($opts['manifest'] ?? null) ? $opts['manifest'] : dirname(__DIR__) . '/docs/IMP-032-faixas-pixabay.json';

$manifest = json_decode((string) @file_get_contents($manifestPath), true);
if (!is_array($manifest) || !is_array($manifest['faixas'] ?? null)) {
    fwrite(STDERR, "ERRO: manifesto inválido ou ausente: {$manifestPath}\n");
    exit(1);
}
$dir = rtrim(is_string($opts['dir'] ?? null) ? $opts['dir'] : (string) ($manifest['pasta_download'] ?? ''), '\\/');
if ($dir === '' || !is_dir($dir)) {
    fwrite(STDERR, "ERRO: pasta de downloads não encontrada: {$dir}\n");
    exit(1);
}

$ffprobe = (string) config('instagram.ffprobe_path', 'ffprobe');
$publicAudio = base_path('public/uploads/audio');
if (!$dryRun && !is_dir($publicAudio) && !@mkdir($publicAudio, 0755, true) && !is_dir($publicAudio)) {
    fwrite(STDERR, "ERRO: não foi possível criar {$publicAudio}\n");
    exit(1);
}

/** @var PDO $db */
$db = $GLOBALS['pdo'];
$porOrigem = $db->prepare("SELECT id FROM instagram_audio_tracks WHERE origem = 'pixabay' AND origem_id = ?");
$porHash = $db->prepare('SELECT id FROM instagram_audio_tracks WHERE file_hash = ?');
$insere = $db->prepare(
    "INSERT INTO instagram_audio_tracks
        (origem, origem_id, source_url, titulo, artista, genero, arquivo_path, duracao_s, file_hash, ativo)
     VALUES ('pixabay', :origem_id, :url, :titulo, :artista, :genero, :path, :duracao, :hash, 1)"
);

echo $dryRun ? "DRY-RUN: nada será copiado nem gravado.\n" : "EXECUÇÃO REAL\n";
$cont = ['cadastrada' => 0, 'pulada' => 0, 'falta' => 0, 'rejeitada' => 0];

foreach ($manifest['faixas'] as $f) {
    $id = (int) ($f['id'] ?? 0);
    $titulo = trim((string) ($f['titulo'] ?? ''));
    $grupo = strtolower(trim((string) ($f['grupo'] ?? '')));
    $rotulo = sprintf('#%d %s', $id, mb_substr($titulo, 0, 40));

    if ($id < 1 || $titulo === '' || !in_array($grupo, GRUPOS, true)) {
        echo "REJEITADA {$rotulo}: id, título ou grupo inválido ({$grupo}).\n";
        $cont['rejeitada']++;
        continue;
    }

    $porOrigem->execute([(string) $id]);
    if ($porOrigem->fetchColumn() !== false) {
        echo "PULADA    {$rotulo}: já cadastrada.\n";
        $cont['pulada']++;
        continue;
    }

    $achados = glob($dir . DIRECTORY_SEPARATOR . '*-' . $id . '.mp3') ?: [];
    if ($achados === []) {
        echo "FALTA     {$rotulo}: arquivo *-{$id}.mp3 não encontrado em {$dir}.\n";
        $cont['falta']++;
        continue;
    }
    $origem = $achados[0];

    $hash = (string) hash_file('sha256', $origem);
    $porHash->execute([$hash]);
    if ($porHash->fetchColumn() !== false) {
        echo "PULADA    {$rotulo}: mesmo arquivo já cadastrado (hash).\n";
        $cont['pulada']++;
        continue;
    }

    $saida = (string) @shell_exec(sprintf(
        '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s',
        escapeshellcmd($ffprobe),
        escapeshellarg($origem)
    ));
    $duracao = (int) floor((float) trim($saida));
    if ($duracao < DURACAO_MINIMA) {
        echo "REJEITADA {$rotulo}: duração {$duracao}s (mínimo " . DURACAO_MINIMA . "s) ou arquivo ilegível.\n";
        $cont['rejeitada']++;
        continue;
    }

    $rel = 'uploads/audio/pixabay_' . $id . '.mp3';
    printf("%s %s: %s, %ds, %.1f MB, sha256 %s…\n", $dryRun ? 'CADASTRARIA' : 'CADASTRADA', $rotulo, $grupo, $duracao, filesize($origem) / 1048576, substr($hash, 0, 12));
    $cont['cadastrada']++;
    if ($dryRun) {
        continue;
    }

    $destino = base_path('public/' . $rel);
    if (!copy($origem, $destino) || hash_file('sha256', $destino) !== $hash) {
        fwrite(STDERR, "ERRO: falha ao copiar {$origem} para {$destino}; interrompido.\n");
        exit(1);
    }
    $insere->execute([
        ':origem_id' => (string) $id,
        ':url'       => (string) ($f['url'] ?? ''),
        ':titulo'    => $titulo,
        ':artista'   => trim((string) ($f['artista'] ?? '')) ?: 'Desconhecido',
        ':genero'    => $grupo,
        ':path'      => $rel,
        ':duracao'   => $duracao,
        ':hash'      => $hash,
    ]);
}

printf("\nResumo: %d %s, %d puladas, %d faltando, %d rejeitadas.\n",
    $cont['cadastrada'], $dryRun ? 'a cadastrar' : 'cadastradas', $cont['pulada'], $cont['falta'], $cont['rejeitada']);
exit($cont['falta'] > 0 || $cont['rejeitada'] > 0 ? 2 : 0);
