<?php
/**
 * -----------------------------------------------------------------------------
 * en-blog-schedule-publish.php
 * Publica automaticamente (rascunho -> publicado) os posts da leva FEAT-005
 * no banco de PRODUCAO, na data planejada em docs/features/FEAT-008.md.
 *
 * Idempotente: so mexe em post cuja data planejada ja chegou E que ainda
 * esteja com status='rascunho' em producao. Post ja publicado (manualmente
 * ou em execucao anterior) ou com data futura e pulado. Seguro rodar todo
 * dia via Tarefa Agendada do Windows.
 *
 * Uso:
 *   php scripts/en-blog-schedule-publish.php
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

$sessionPath = dirname(__DIR__) . '/storage/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0777, true);
}
ini_set('session.save_path', $sessionPath);

require dirname(__DIR__) . '/bootstrap.php';

use App\Repositories\PostRepository;
use App\Support\EnvironmentGuard;
use App\Support\TargetEnvironmentDatabase;

EnvironmentGuard::requireLocal();

// Agenda definida em docs/features/FEAT-008.md - producao_id => data planejada.
// O post 30 (Windows 11 24H2) ja foi publicado manualmente e nao entra aqui.
const SCHEDULE = [
    31 => '2026-09-22', // RTX 5060 vs RX 9060 XT (1080p)
    32 => '2026-09-25', // AM4 ou AM5 em 2026
    33 => '2026-09-29', // Como escolher fonte para PC
    34 => '2026-10-02', // Como verificar saude de SSD/HD
    35 => '2026-10-06', // Temperatura CPU/GPU: quando se preocupar
    36 => '2026-10-09', // 12 jogos leves para PC fraco ou modesto
    37 => '2026-10-13', // A historia do Counter-Strike
    38 => '2026-10-16', // 10 filmes nerds essenciais
    39 => '2026-10-20', // 10 animes essenciais
    40 => '2026-10-23', // 10 desenhos dos anos 90/2000 que envelheceram bem
];

try {
    $prodPdo = TargetEnvironmentDatabase::pdo('production');
} catch (Throwable $exception) {
    fwrite(STDERR, 'ERRO: falha ao conectar no banco de producao: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

$prodPosts = new PostRepository($prodPdo);
$today = date('Y-m-d');
$results = [];

foreach (SCHEDULE as $prodId => $scheduledDate) {
    $post = $prodPosts->findAdminById($prodId);
    if ($post === null) {
        $results[] = ['production_id' => $prodId, 'action' => 'erro', 'motivo' => 'post nao encontrado em producao'];
        continue;
    }

    $status = (string) ($post['status'] ?? '');
    if ($status !== 'rascunho') {
        $results[] = ['production_id' => $prodId, 'action' => 'pulado', 'motivo' => "status atual e '{$status}', nao 'rascunho'"];
        continue;
    }

    if ($scheduledDate > $today) {
        $results[] = ['production_id' => $prodId, 'action' => 'pulado', 'motivo' => "data planejada ({$scheduledDate}) ainda nao chegou"];
        continue;
    }

    $payload = $post;
    $payload['status'] = 'publicado';
    $payload['data_publicacao'] = $today . ' ' . date('H:i:s');
    $prodPosts->updateAdmin($prodId, $payload);

    $results[] = ['production_id' => $prodId, 'action' => 'publicado', 'data_publicacao' => $payload['data_publicacao']];
}

echo json_encode([
    'ok' => true,
    'data_execucao' => $today,
    'resultados' => $results,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
