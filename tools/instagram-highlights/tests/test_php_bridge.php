<?php
/** Offline security tests: no bootstrap, credentials, database or Instagram. */
declare(strict_types=1);
if (in_array($argv[1] ?? '', ['status', 'reconcile'], true)) {
    $payload = json_decode((string) file_get_contents('php://stdin'), true);
    $mode = basename((string) ($argv[3] ?? ''));
    if ($mode === 'timeout') { sleep(5); }
    if ($mode === 'stderr') { fwrite(STDERR, str_repeat('private-test-marker', 10000)); }
    if ($mode === 'malformed') { fwrite(STDERR, 'private-test-marker'); echo 'private-test-marker'; exit(1); }
    if ($mode === 'overflow') { echo str_repeat('x', 1200000); exit; }
    echo json_encode(['ok' => true, 'payload' => $payload]); exit;
}
require dirname(__DIR__, 3) . '/vendor/autoload.php';
require dirname(__DIR__, 3) . '/app/Support/helpers.php';
use App\Controllers\Admin\InstagramHighlightsController;
use App\Services\Instagram\InstagramHighlightsService;
use App\Support\Csrf;
$GLOBALS['config'] = ['app' => ['env' => 'local', 'url' => 'http://localhost']];
$_SESSION = ['auth.user_id' => 1, 'auth.user' => ['id' => 1, 'papel' => 'admin']];
function ensure(bool $condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } }
function property(object $object, string $name, mixed $value): void { (new ReflectionProperty($object, $name))->setValue($object, $value); }
function fakeService(string $root, string $mode = 'normal'): InstagramHighlightsService {
    $service = (new ReflectionClass(InstagramHighlightsService::class))->newInstanceWithoutConstructor();
    foreach (['projectRoot' => dirname(__DIR__, 3), 'pythonBinary' => PHP_BINARY, 'cliScript' => __FILE__, 'storageRoot' => $root . '/' . $mode, 'assetsDir' => $root . '/assets', 'processTimeoutSeconds' => 0.4] as $key => $value) { property($service, $key, $value); }
    return $service;
}
function expectFailure(callable $operation, string $message): string {
    try { $operation(); } catch (RuntimeException $exception) {
        ensure(!str_contains($exception->getMessage(), 'private-test-marker'), 'Raw process data leaked.');
        return $exception->getMessage();
    }
    throw new RuntimeException($message);
}
if (str_starts_with($argv[1] ?? '', 'guard-')) {
    $mode = substr($argv[1], 6);
    if ($mode === 'anonymous') { $_SESSION = []; }
    elseif ($mode === 'production') { $GLOBALS['config']['app']['env'] = 'production'; }
    else { $_SESSION['auth.user']['papel'] = $mode; }
    register_shutdown_function(static function (): void { echo json_encode(['status' => http_response_code() ?: 200]); });
    new InstagramHighlightsController(); echo 'AUTHORIZED'; exit;
}
$root = sys_get_temp_dir() . '/en-highlights-php-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
foreach (['normal', 'timeout', 'stderr', 'malformed', 'overflow', 'assets'] as $directory) { mkdir($root . '/' . $directory, 0700); }
try {
    foreach (['editor', 'anonymous', 'production', 'admin'] as $role) {
        $process = proc_open([PHP_BINARY, __FILE__, 'guard-' . $role], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
        ensure(is_resource($process), 'Cannot start isolated auth test.');
        $output = stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
        ensure(str_contains((string) $output, 'AUTHORIZED') === ($role === 'admin'), 'Authorization failed for ' . $role);
        if ($role === 'editor') { ensure(str_contains((string) $output, '403'), 'Editor must receive HTTP 403.'); }
    }
    $service = fakeService($root);
    ensure($service->getStatus()['ok'] === true, 'Fake bridge failed.');
    expectFailure(fn() => $service->getJob('../../auth/session'), 'Traversal accepted.');
    expectFailure(fn() => $service->uploadAsset(['error' => UPLOAD_ERR_NO_FILE], 'story'), 'Empty upload accepted.');
    $controller = (new ReflectionClass(InstagramHighlightsController::class))->newInstanceWithoutConstructor();
    property($controller, 'service', $service);
    $_SESSION['_csrf_token'] = 'test-token';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'incorrect';
    $csrf = new ReflectionMethod($controller, 'validateCsrf');
    ensure($csrf->invoke($controller) === false, 'Invalid CSRF accepted.');
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'test-token';
    ensure($csrf->invoke($controller) === true, 'Valid CSRF rejected.');
    foreach (['false', 'true', 1, false, null] as $invalid) {
        $_POST = ['job_id' => str_repeat('a', 32), 'verified_visual' => $invalid];
        ob_start(); $controller->reconcile(); $response = json_decode((string) ob_get_clean(), true);
        ensure($response['ok'] === false, 'Non-boolean confirmation accepted.');
    }
    $_POST = ['job_id' => str_repeat('a', 32), 'verified_visual' => true, 'remote_id' => '123'];
    ob_start(); $controller->reconcile(); $response = json_decode((string) ob_get_clean(), true);
    ensure($response['ok'] === true && $response['payload']['verified_visual'] === true, 'Explicit true rejected.');
    $_POST = [];
    eval('namespace App\Support; final class View { public static array $data = []; public static function render(string $view, array $data = []): void { self::$data = $data; } }');
    session_save_path($root); session_id('test' . bin2hex(random_bytes(10))); session_start();
    $_SESSION = ['auth.user_id' => 1, 'auth.user' => ['papel' => 'admin']];
    $controller->index(); $renderedToken = \App\Support\View::$data['csrf_token'];
    ensure(session_status() !== PHP_SESSION_ACTIVE, 'Bridge did not release session.');
    session_start(); ensure(Csrf::validate($renderedToken), 'Rendered CSRF token was not persisted.'); session_write_close();
    ensure(fakeService($root, 'stderr')->getStatus()['ok'] === true, 'Stderr drain deadlocked or failed.');
    expectFailure(fn() => fakeService($root, 'malformed')->getStatus(), 'Malformed output accepted.');
    expectFailure(fn() => fakeService($root, 'overflow')->getStatus(), 'Oversized output accepted.');
    $start = microtime(true);
    $message = expectFailure(fn() => fakeService($root, 'timeout')->getStatus(), 'Timeout ignored.');
    ensure(microtime(true) - $start < 3, 'Deadline did not stop process.');
    ensure(str_contains($message, 'incerto'), 'Timeout must explain uncertain result.');
    echo "PASS: admin/local auth, CSRF persistence, strict reconciliation, bridge deadline/output/secrecy, path and upload rejection.\n";
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) { $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname()); }
    rmdir($root);
}
