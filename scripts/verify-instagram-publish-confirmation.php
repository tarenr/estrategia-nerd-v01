<?php
declare(strict_types=1);

use App\Repositories\InstagramPostRepository;
use App\Services\Instagram\InstagramApiService;
use App\Services\Instagram\InstagramPublishConfirmationService;

// Isolated SQLite and injected HTTP: no bootstrap, credentials or real publication.
if (PHP_SAPI !== 'cli') { exit(1); }
$root = dirname(__DIR__);
foreach (['Repositories/InstagramPostRepository','Services/Instagram/InstagramApiService','Services/Instagram/InstagramPublishOutcomeUnknownException','Services/Instagram/InstagramPublishConfirmationService'] as $class) {
    require_once $root . '/app/' . $class . '.php';
}
$passed = 0; $failed = 0;
$check = static function (string $name, bool $ok) use (&$passed, &$failed): void {
    $ok ? $passed++ : $failed++;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
};
$fixture = static function (): PDO {
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'));
    $pdo->exec("CREATE TABLE instagram_posts (id INTEGER PRIMARY KEY, account_id INTEGER, status TEXT, publish_phase TEXT, publish_attempted_at TEXT, creation_id TEXT, ig_media_id TEXT, permalink TEXT, error_log TEXT, publicado_em TEXT, atualizado_em TEXT)");
    $pdo->exec("INSERT INTO instagram_posts (id,account_id,status,publish_phase) VALUES (1,7,'agendado','idle')");
    return $pdo;
};
$response = static fn (string|false $body, int $code = 200, string $error = '', int $errno = 0): array => ['body'=>$body,'http_code'=>$code,'error'=>$error,'errno'=>$errno];
$receiptRoot = $root . '/storage/backups/instagram-publish/tests-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
foreach (['success','timeout','server','invalid','missing','container','rejected','interrupted'] as $case) {
    $pdo = $fixture(); $repo = new InstagramPostRepository($pdo);
    $check($case . ': claim', $repo->lockForPublishing(1));
    $check($case . ': competing claim refused', !$repo->claimForImmediatePublishing(1));
    $check($case . ': container persisted', $repo->saveCreationId(1,'111'));
    $posts = 0; $gets = 0; $checkpoint = false; $timeout = 0; $connection = 0;
    $transport = static function (string $method, string $url, array $payload, int $limit, int $connect) use ($case,$pdo,$response,&$posts,&$gets,&$checkpoint,&$timeout,&$connection): array {
        if ($method === 'POST') {
            $posts++; $timeout=$limit; $connection=$connect;
            $checkpoint = $pdo->query('SELECT publish_phase FROM instagram_posts WHERE id=1')->fetchColumn() === 'awaiting_confirmation';
            return match ($case) {
                'timeout'=>$response(false,0,'secret must not leak',28),
                'server'=>$response('{"error":{"message":"secret"}}',503),
                'invalid'=>$response('not-json'),
                'missing'=>$response('{}'),
                'container'=>$response('{"id":"111"}'),
                'rejected'=>$response('{"error":{"code":190,"message":"secret"}}',400),
                default=>$response('{"id":"222"}'),
            };
        }
        $gets++;
        return str_contains($url,'/111?') ? $response('{"status_code":"PUBLISHED"}') : $response(false,0,'secret',28);
    };
    $api = new InstagramApiService('999','fake-token',$transport);
    $service = new InstagramPublishConfirmationService($repo,$api,$receiptRoot . '/' . $case);
    if ($case === 'interrupted') {
        $pdo->exec("CREATE TRIGGER interrupt_confirmation BEFORE UPDATE OF status ON instagram_posts WHEN NEW.status='publicado' BEGIN SELECT RAISE(FAIL,'simulated interruption'); END");
    }
    $result = $service->publish(1,'111');
    $check($case . ': durable checkpoint precedes POST', $checkpoint);
    $check($case . ': bounded dedicated timeouts', $timeout===45 && $connection===5);
    $check($case . ': secrets absent from stored notice', !str_contains((string) ($repo->findById(1)['error_log'] ?? ''),'secret'));
    if ($case === 'success') {
        $check('permalink failure preserves confirmed publication', $result['state']==='published' && $repo->findById(1)['ig_media_id']==='222');
        $repo->markError(1,'later error');
        $check('later error cannot overwrite publication', $repo->findById(1)['status']==='publicado');
    } elseif ($case === 'rejected') {
        $check('definite authentication rejection allows explicit retry', $result['state']==='failed' && $repo->claimForImmediatePublishing(1));
        continue;
    } elseif ($case === 'interrupted') {
        $check('received ID has durable receipt despite DB interruption', $result['state']==='pending' && $result['media_id']==='222');
        $pdo->exec('DROP TRIGGER interrupt_confirmation');
        $service->reconcile(1);
        $check('receipt recovers exact ID without another POST', $repo->findById(1)['ig_media_id']==='222' && $posts===1);
    } else {
        $check($case . ': uncertainty remains pending', $result['state']==='pending' && $repo->findById(1)['status']==='publicando');
        $repo->markError(1,'outer failure');
        $check($case . ': generic error preserves pending attempt', $repo->findById(1)['publish_phase']==='awaiting_confirmation');
        $service->reconcile(1);
        $check($case . ': PUBLISHED hint never invents media ID', $repo->findById(1)['publish_phase']==='published_id_pending' && $repo->findById(1)['ig_media_id']===null);
    }
    $service->publish(1,'111');
    $check($case . ': repeated entry cannot resend', $posts===1);
}
echo "{$passed} passed; {$failed} failed. Fixtures retained under {$receiptRoot}" . PHP_EOL;
exit($failed === 0 ? 0 : 1);
