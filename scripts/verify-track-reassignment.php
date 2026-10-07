<?php
declare(strict_types=1);
define('TRACK_REASSIGN_TEST', true);
date_default_timezone_set('America/Sao_Paulo');
require __DIR__ . '/en-instagram-reassign-tracks.php';
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE instagram_posts(id INTEGER PRIMARY KEY,status TEXT,tipo TEXT,origin TEXT,agendado_para TEXT,audio_track_id INTEGER,audio_start_seconds INTEGER,atualizado_em TEXT)');
$date = date('Y-m-d H:i:s', time() + 86400);
$insert = $db->prepare("INSERT INTO instagram_posts VALUES(1,'agendado','reels','local',?,NULL,0,'2026-10-01 10:00:00')");
$insert->execute([$date]);
$before = $db->query('SELECT * FROM instagram_posts')->fetch(PDO::FETCH_ASSOC);
$passed = 0;
$check = static function (string $name, bool $ok) use (&$passed): void {
    if (!$ok) { throw new RuntimeException('FAIL: ' . $name); }
    $passed++; echo 'PASS: ' . $name . "\n";
};
$rejects = static function (array $row, array $expected): bool {
    try { assertAudioPost($row, $expected); return false; } catch (RuntimeException) { return true; }
};
assertAudioPost($before, $before);
$check('Registro elegível e valores originais aceitos', true);
foreach (['status' => 'publicado', 'tipo' => 'feed', 'origin' => 'production',
    'agendado_para' => date('Y-m-d H:i:s', time() + 100), 'audio_track_id' => 0,
    'audio_start_seconds' => 1, 'atualizado_em' => '2026-10-07 12:00:00'] as $field => $value) {
    $changed = $before; $changed[$field] = $value;
    $check('Recusa divergência: ' . $field, $rejects($changed, $before));
}
$db->beginTransaction();
$db->exec("UPDATE instagram_posts SET audio_track_id=8,audio_start_seconds=12,atualizado_em='2026-10-07 11:00:00' WHERE id=1");
$after = $db->query('SELECT * FROM instagram_posts')->fetch(PDO::FETCH_ASSOC);
assertAudioPost($after, $after);
$check('Estado aplicado permite recuperação sob guarda', true);
$later = $after; $later['atualizado_em'] = '2026-10-07 11:05:00';
$check('Edição posterior bloqueia rollback', $rejects($later, $after));
$db->rollBack();
$check('Rollback da transação preserva valores e NULL original', $db->query('SELECT * FROM instagram_posts')->fetch(PDO::FETCH_ASSOC) === $before);
$dir = dirname(__DIR__) . '/storage/backups/reels-audio';
if (!is_dir($dir)) { mkdir($dir, 0755, true); }
$path = $dir . '/guard-test-' . bin2hex(random_bytes(4)) . '.json';
saveAudioBackup($path, ['posts' => [$before], 'test' => true]);
$check('Backup durável mantém os valores originais', json_decode((string) file_get_contents($path), true)['posts'][0] === $before);
try { saveAudioBackup($path, ['overwrite' => true]); $blocked = false; } catch (RuntimeException) { $blocked = true; }
$check('Backup existente nunca é sobrescrito', $blocked);
echo $passed . " verificações; zero falhas.\n";
