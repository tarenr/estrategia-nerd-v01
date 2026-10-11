<?php
/**
 * -----------------------------------------------------------------------------
 * @file        scripts/verify-instagram-feed-sync.php
 * @project     Estrategia Nerd
 * @purpose     Testes isolados (sem banco, rede ou FTP) da reconciliação do feed e
 *              da validação do publicador (FEAT-010 v2.12.0)
 * @usage       C:\xampp\php\php.exe scripts/verify-instagram-feed-sync.php
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

use App\Services\Instagram\InstagramFeedPublisher;
use App\Services\Instagram\InstagramFeedReconciler;

if (PHP_SAPI !== 'cli') { exit(1); }
$root = dirname(__DIR__);
require_once $root . '/app/Services/Instagram/InstagramFeedReconciler.php';
require_once $root . '/app/Services/Instagram/InstagramFeedPublisher.php';

$passed = 0; $failed = 0;
$check = static function (string $name, bool $ok) use (&$passed, &$failed): void {
    $ok ? $passed++ : $failed++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
};

// ── Reconciliação ────────────────────────────────────────────────────────────
$now = strtotime('2026-10-11 20:00:00');
$rows = [];
for ($i = 1; $i <= 40; $i++) {
    $rows[] = ['id' => $i, 'ig_media_id' => 'M' . $i, 'publicado_em' => '2026-09-01 10:00:00'];
}
$allLive = array_map(static fn (array $r): string => $r['ig_media_id'], $rows);

$r = InstagramFeedReconciler::findOrphans($rows, $allLive, $now);
$check('tudo presente na Meta: nenhum órfão', $r['orphans'] === [] && $r['aborted'] === null);

$live = array_values(array_diff($allLive, ['M5', 'M9']));
$r = InstagramFeedReconciler::findOrphans($rows, $live, $now);
$check('dois ausentes da Meta: apenas eles são órfãos', $r['orphans'] === [5, 9] && $r['aborted'] === null);

$r = InstagramFeedReconciler::findOrphans($rows, [], $now);
$check('lista da Meta vazia: aborta sem cancelar', $r['orphans'] === [] && $r['aborted'] !== null);

$r = InstagramFeedReconciler::findOrphans($rows, array_slice($allLive, 0, 10), $now);
$check('lista truncada (10 de 40): aborta sem cancelar', $r['orphans'] === [] && $r['aborted'] !== null);

$r = InstagramFeedReconciler::findOrphans($rows, array_slice($allLive, 0, 28), $now);
$check('12 ausentes (acima do teto de 10): aborta sem cancelar', $r['orphans'] === [] && $r['aborted'] !== null);

$recent = $rows;
$recent[] = ['id' => 99, 'ig_media_id' => 'NOVO', 'publicado_em' => '2026-10-11 19:30:00'];
$r = InstagramFeedReconciler::findOrphans($recent, $allLive, $now);
$check('post publicado há 30 min e ainda fora da lista: preservado', $r['orphans'] === [] && $r['aborted'] === null);

$old = $rows;
$old[] = ['id' => 98, 'ig_media_id' => 'VELHO', 'publicado_em' => '2026-10-11 16:00:00'];
$r = InstagramFeedReconciler::findOrphans($old, $allLive, $now);
$check('post publicado há 4 h e ausente: órfão', $r['orphans'] === [98]);

$noId = $rows;
$noId[] = ['id' => 97, 'ig_media_id' => '', 'publicado_em' => '2026-09-01 10:00:00'];
$noId[] = ['id' => 96, 'ig_media_id' => null, 'publicado_em' => '2026-09-01 10:00:00'];
$r = InstagramFeedReconciler::findOrphans($noId, $allLive, $now);
$check('post sem ig_media_id nunca é cancelado', $r['orphans'] === []);

// ── Validação do feed pelo publicador ────────────────────────────────────────
$tmp = sys_get_temp_dir() . '/en-feed-verify-' . bin2hex(random_bytes(4));
mkdir($tmp . '/assets/instagram-feed', 0700, true);
file_put_contents($tmp . '/assets/instagram-feed/post_1.jpg', str_repeat('x', 2000));
file_put_contents($tmp . '/assets/instagram-feed/post_2.jpg', str_repeat('x', 2000));
file_put_contents($tmp . '/assets/instagram-feed/post_3.jpg', 'x');
$post = static fn (int $id, string $media, string $date): array => ['id' => $id, 'media_url' => $media, 'publicado_em' => $date];
$feed = static fn (array $posts): array => ['username' => 'x', 'posts' => $posts];

$v = static fn (array $f): array => InstagramFeedPublisher::validateFeed($f, $tmp);

$ok = $v($feed([$post(2, '/assets/instagram-feed/post_2.jpg', '2026-10-02 10:00:00'), $post(1, '/assets/instagram-feed/post_1.jpg', '2026-10-01 10:00:00')]));
$check('feed válido é aceito', $ok['errors'] === [] && $ok['covers'] === ['post_2.jpg', 'post_1.jpg']);
$check('feed sem posts é recusado', $v($feed([]))['errors'] !== []);
$check('feed com .mp4 no media_url é recusado', $v($feed([$post(1, 'uploads/reels/x.mp4', '2026-10-01 10:00:00')]))['errors'] !== []);
$check('capa ausente é recusada', $v($feed([$post(9, '/assets/instagram-feed/post_9.jpg', '2026-10-01 10:00:00')]))['errors'] !== []);
$check('capa menor que 1 KB é recusada', $v($feed([$post(3, '/assets/instagram-feed/post_3.jpg', '2026-10-01 10:00:00')]))['errors'] !== []);
$check('ordem crescente de data é recusada', $v($feed([$post(1, '/assets/instagram-feed/post_1.jpg', '2026-10-01 10:00:00'), $post(2, '/assets/instagram-feed/post_2.jpg', '2026-10-02 10:00:00')]))['errors'] !== []);
$many = [];
for ($i = 0; $i < 19; $i++) { $many[] = $post(1, '/assets/instagram-feed/post_1.jpg', sprintf('2026-09-%02d 10:00:00', 28 - $i % 28)); }
$check('mais de 18 posts é recusado', $v($feed($many))['errors'] !== []);
$check('caminho fora da pasta de capas é recusado', $v($feed([$post(1, '/assets/instagram-feed/../../.env', '2026-10-01 10:00:00')]))['errors'] !== []);

foreach (glob($tmp . '/assets/instagram-feed/*') ?: [] as $file) { unlink($file); }
rmdir($tmp . '/assets/instagram-feed'); rmdir($tmp . '/assets'); rmdir($tmp);

// ── Falhas integradas do publicador (raiz de projeto descartavel; FTP inexistente) ──
$fixtureRoot = sys_get_temp_dir() . '/en-feed-verify-root-' . bin2hex(random_bytes(4));
mkdir($fixtureRoot . '/config', 0700, true);
mkdir($fixtureRoot . '/public/assets/instagram-feed', 0700, true);
file_put_contents($fixtureRoot . '/public/assets/instagram-feed/post_1.jpg', str_repeat('x', 2000));
$deadProfile = [
    'code_deploy' => ['mode' => 'ftp', 'host' => '127.0.0.1', 'port' => 1, 'username' => 'u', 'password' => 'secret-pass', 'root' => '/x/public_html/stage/_app_core', 'passive' => true],
    'uploads' => ['root' => '/x/public_html/stage/uploads'],
];
$publish = static function (string $json) use ($fixtureRoot, $deadProfile): array {
    file_put_contents($fixtureRoot . '/config/instagram-feed.json', $json);

    return (new InstagramFeedPublisher('stage', $deadProfile, $fixtureRoot))->publish(false);
};
$validJson = json_encode(['posts' => [['id' => 1, 'media_url' => '/assets/instagram-feed/post_1.jpg', 'publicado_em' => '2026-10-01 10:00:00']]]);
$r = $publish((string) $validJson);
$check('FTP indisponível: falha controlada, sem exceção e sem vazar senha', $r['status'] === 'failed' && !str_contains($r['message'], 'secret-pass') && str_contains($r['message'], 'FTP'));
$r = $publish('{"posts":[]}');
$check('JSON vazio: recusado antes de abrir FTP', $r['status'] === 'failed' && str_contains($r['message'], 'reprovado'));
$r = $publish((string) json_encode(['posts' => [['id' => 1, 'media_url' => 'uploads/reels/x.mp4', 'publicado_em' => '2026-10-01 10:00:00']]]));
$check('.mp4 no media_url: recusado antes de abrir FTP', $r['status'] === 'failed' && str_contains($r['message'], 'reprovado'));
$r = $publish('nao-e-json');
$check('JSON corrompido: recusado antes de abrir FTP', $r['status'] === 'failed' && str_contains($r['message'], 'invalido'));
$r = (new InstagramFeedPublisher('stage', $deadProfile, $fixtureRoot . '/inexistente'))->publish(false);
$check('JSON local ausente: recusado', $r['status'] === 'failed');
try { new InstagramFeedPublisher('qa', $deadProfile, $fixtureRoot); $check('alvo invalido é rejeitado', false); }
catch (RuntimeException) { $check('alvo invalido é rejeitado', true); }

$check('needsPublish: sem estado, precisa publicar', InstagramFeedPublisher::needsPublish($fixtureRoot, 'production') === true);
InstagramFeedPublisher::writeState($fixtureRoot, ['production' => ['status' => 'ok', 'hash' => hash_file('sha256', $fixtureRoot . '/config/instagram-feed.json')]]);
$check('needsPublish: estado ok e hash igual, nada a fazer', InstagramFeedPublisher::needsPublish($fixtureRoot, 'production') === false);
file_put_contents($fixtureRoot . '/config/instagram-feed.json', $validJson . ' ');
$check('needsPublish: JSON mudou, precisa publicar', InstagramFeedPublisher::needsPublish($fixtureRoot, 'production') === true);
InstagramFeedPublisher::writeState($fixtureRoot, ['production' => ['status' => 'failed', 'hash' => hash_file('sha256', $fixtureRoot . '/config/instagram-feed.json')]]);
$check('needsPublish: ultimo envio falhou, precisa publicar', InstagramFeedPublisher::needsPublish($fixtureRoot, 'production') === true);

foreach (['/storage/instagram-feed-publish/state.json', '/config/instagram-feed.json', '/public/assets/instagram-feed/post_1.jpg'] as $file) { @unlink($fixtureRoot . $file); }
foreach (['/storage/instagram-feed-publish', '/storage', '/config', '/public/assets/instagram-feed', '/public/assets', '/public'] as $dir) { @rmdir($fixtureRoot . $dir); }
@rmdir($fixtureRoot);

echo PHP_EOL . "Resultado: {$passed} passaram, {$failed} falharam." . PHP_EOL;
exit($failed === 0 ? 0 : 1);
