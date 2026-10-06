<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Repositories\LinkClickRepository;
use App\Repositories\LinkRepository;
use App\Services\Admin\LinksService;
use App\Services\Admin\MidiaService;

// Verificacao semanal dos links de afiliado no banco LOCAL (IMP-031).
// Agendada no Windows como "EstrategiaNerd-VerificarLinks". Stage e producao recebem o status
// so quando o usuario publica com scripts/en-links-sync.php.

const PAUSA_MS = 3000;
const LIMITE_SEGUNDOS = 900;
const FORGE_TAREFAS = 'http://127.0.0.1:4477/api/projects/4/tasks/item';

set_time_limit(LIMITE_SEGUNDOS);

$pastaLog = dirname(__DIR__) . '/storage/logs/links-monitor';
if (!is_dir($pastaLog)) {
    @mkdir($pastaLog, 0775, true);
}
$arquivoLog = $pastaLog . '/' . date('Y-m') . '.log';

function lm_log(string $mensagem): void
{
    global $arquivoLog;
    $linha = '[' . date('Y-m-d H:i:s') . '] ' . $mensagem . PHP_EOL;
    file_put_contents($arquivoLog, $linha, FILE_APPEND | LOCK_EX);
    echo $linha;
}

/**
 * @return array<int, array{status: string, titulo: string, observacao: string}>
 */
function lerStatus(PDO $pdo): array
{
    $mapa = [];
    foreach ($pdo->query('SELECT id, status, titulo, observacao_status FROM links')->fetchAll(PDO::FETCH_ASSOC) as $linha) {
        $mapa[(int) $linha['id']] = [
            'status' => (string) $linha['status'],
            'titulo' => (string) $linha['titulo'],
            'observacao' => (string) ($linha['observacao_status'] ?? ''),
        ];
    }

    return $mapa;
}

function avisarForge(string $titulo): bool
{
    $ch = curl_init(FORGE_TAREFAS);
    if ($ch === false) {
        return false;
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => (string) json_encode(['titulo' => $titulo, 'concluida' => false]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8', 'x-forge-csrf: 1'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $resposta = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return is_string($resposta) && $codigo >= 200 && $codigo < 300;
}

// Mesma trava do content-sync e do en-links-sync: nao roda junto com uma publicacao.
$config = require dirname(__DIR__) . '/config/content-sync.php';
$raizPacotes = rtrim((string) ($config['package_root'] ?? ''), '\\/');
$caminhoTrava = $raizPacotes . DIRECTORY_SEPARATOR . '.content-sync-running.lock.json';
$trava = $raizPacotes !== '' && is_dir($raizPacotes) ? fopen($caminhoTrava, 'c+') : false;
if ($trava === false || !flock($trava, LOCK_EX | LOCK_NB)) {
    lm_log('PULADO: outra rotina de conteudo em execucao ou trava indisponivel.');
    exit(0);
}
ftruncate($trava, 0);
fwrite($trava, (string) json_encode(['operation' => 'links-check', 'profile' => 'local', 'started_at' => date('c'), 'pid' => getmypid()]));
fflush($trava);

try {
    /** @var PDO $pdo */
    $pdo = $GLOBALS['pdo'];
    $antes = lerStatus($pdo);
    lm_log('INICIO: verificando ' . count($antes) . ' links (banco local).');

    $servico = new LinksService(new LinkRepository($pdo), new LinkClickRepository($pdo), new MidiaService($pdo), 'local');
    $resultado = $servico->checkAllLinks(PAUSA_MS);
    $depois = lerStatus($pdo);

    $novosQuebrados = [];
    foreach ($depois as $id => $atual) {
        $anterior = $antes[$id]['status'] ?? '';
        if ($anterior !== $atual['status']) {
            lm_log("MUDOU #{$id} {$atual['titulo']}: {$anterior} -> {$atual['status']} ({$atual['observacao']})");
            if ($atual['status'] === 'quebrado') {
                $novosQuebrados[] = $id;
            }
        }
        if (str_starts_with($atual['observacao'], 'Verificacao inconclusiva')) {
            lm_log("INCONCLUSIVO #{$id} {$atual['titulo']}: {$atual['observacao']}");
        }
    }

    lm_log(sprintf(
        'FIM: %d verificados, %d ok, %d com falha, %d passaram a quebrado.',
        (int) ($resultado['checked'] ?? 0),
        (int) ($resultado['ok_count'] ?? 0),
        (int) ($resultado['broken_count'] ?? 0),
        count($novosQuebrados)
    ));

    if ($novosQuebrados !== []) {
        $titulo = sprintf(
            '[Links] - Revisar %d link(s) apontado(s) pela verificação de %s (#%s)',
            count($novosQuebrados),
            date('d/m/Y'),
            implode(', #', $novosQuebrados)
        );
        lm_log(avisarForge($titulo) ? 'AVISO: tarefa criada no Forge.' : 'AVISO: Forge indisponivel; ver este log.');
    }
} catch (Throwable $e) {
    lm_log('ERRO: ' . $e->getMessage());
} finally {
    flock($trava, LOCK_UN);
    fclose($trava);
    @unlink($caminhoTrava);
}
