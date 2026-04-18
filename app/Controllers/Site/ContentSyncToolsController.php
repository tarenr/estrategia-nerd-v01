<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Support\Csrf;
use App\Support\Session;
use App\Support\View;
use Scripts\ContentSync\ContentSyncManager;

final class ContentSyncToolsController
{
    public function index(): void
    {
        $this->ensureLocalOnly();

        $manager = $this->manager();
        $status = $manager->status();
        $flash = Session::pull('content_sync_flash');
        $lastVerification = Session::pull('content_sync_verification');
        $lastPostCheck = Session::pull('content_sync_postcheck');
        $deploymentPolicy = $manager->deploymentPolicyStatus();

        View::render('site/content-sync-tools', [
            'title' => 'Conteudo Local | Estrategia Nerd',
            'meta_description' => 'Painel local para exportar, validar e publicar conteudo em stage e producao.',
            'site_chrome' => false,
            'content_status' => $this->presentStatus($status),
            'code_status' => $this->presentCodeStatus($manager->codeStatus()),
            'parity_status' => $manager->parityStatus(),
            'deployment_policy' => $deploymentPolicy,
            'flash' => is_array($flash) ? $flash : null,
            'last_verification' => is_array($lastVerification) ? $lastVerification : null,
            'last_post_check' => is_array($lastPostCheck) ? $lastPostCheck : null,
            'stage_ready' => $this->profileReady('stage'),
            'stage_code_ready' => $this->codeProfileReady('stage'),
            'production_ready' => $this->profileReady('production'),
            'production_code_ready' => $this->codeProfileReady('production'),
        ]);
    }

    public function handle(): void
    {
        $this->ensureLocalOnly();

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            $this->flash('error', 'Sessao expirada. Atualize a pagina e tente novamente.');
            $this->redirect();
        }

        $action = strtolower(trim((string) ($_POST['action'] ?? '')));
        $manager = $this->manager();

        try {
            switch ($action) {
                case 'export':
                    $profile = strtolower(trim((string) ($_POST['profile'] ?? 'local')));
                    $manifest = $manager->export($profile);
                    $this->flash('success', sprintf('Pacote %s gerado com sucesso.', (string) ($manifest['package_id'] ?? '')));
                    break;

                case 'verify':
                    $packageId = $this->normalizeOptionalId($_POST['package_id'] ?? 'latest');
                    $verification = $manager->verify($packageId);
                    Session::put('content_sync_verification', $verification);
                    $this->flash('success', 'Verificacao concluida.');
                    break;

                case 'apply':
                    $phrase = trim((string) ($_POST['apply_phrase'] ?? ''));
                    if (mb_strtoupper($phrase, 'UTF-8') !== 'PUBLICAR') {
                        throw new \RuntimeException('Digite PUBLICAR para confirmar o envio do conteudo.');
                    }

                    $packageId = $this->normalizeOptionalId($_POST['package_id'] ?? 'latest');
                    $targetProfile = strtolower(trim((string) ($_POST['target_profile'] ?? 'production')));
                    $result = $manager->apply($packageId, $targetProfile, true);
                    Session::put('content_sync_postcheck', $manager->parityStatus());
                    $this->flash('success', sprintf('Pacote %s aplicado em %s.', (string) ($result['package_id'] ?? ''), (string) ($result['target_profile'] ?? '')));
                    break;

                case 'apply_code':
                    $phrase = trim((string) ($_POST['apply_phrase'] ?? ''));
                    if (mb_strtoupper($phrase, 'UTF-8') !== 'PUBLICAR') {
                        throw new \RuntimeException('Digite PUBLICAR para confirmar o envio do codigo.');
                    }

                    $packageId = $this->normalizeOptionalId($_POST['package_id'] ?? 'latest');
                    $targetProfile = strtolower(trim((string) ($_POST['target_profile'] ?? 'production')));
                    $result = $manager->applyCode($packageId, $targetProfile, true);
                    Session::put('content_sync_postcheck', $manager->parityStatus());
                    $this->flash('success', sprintf('Pacote de codigo %s aplicado em %s (%d arquivos).', (string) ($result['package_id'] ?? ''), (string) ($result['target_profile'] ?? ''), (int) ($result['result']['files_applied'] ?? 0)));
                    break;

                default:
                    throw new \RuntimeException('Acao invalida para a rotina de conteudo.');
            }
        } catch (\Throwable $exception) {
            $this->flash('error', $exception->getMessage());
        }

        $this->redirect();
    }

    private function ensureLocalOnly(): void
    {
        $env = (string) config('app.env', 'production');
        $debug = (bool) config('app.debug', false);

        if ($env === 'local' || $debug) {
            return;
        }

        http_response_code(404);
        echo 'Pagina nao encontrada.';
        exit;
    }

    private function manager(): ContentSyncManager
    {
        require_once base_path('scripts/content-sync/ContentSyncManager.php');

        return new ContentSyncManager(require base_path('config/content-sync.php'));
    }

    private function redirect(): void
    {
        header('Location: ' . url('/local/conteudo'));
        exit;
    }

    private function flash(string $type, string $message): void
    {
        Session::put('content_sync_flash', [
            'type' => $type,
            'message' => $message,
        ]);
    }

    private function normalizeOptionalId(mixed $packageId): ?string
    {
        $value = trim((string) $packageId);
        if ($value === '' || strtolower($value) === 'latest') {
            return null;
        }

        return $value;
    }

    private function profileReady(string $profileName): bool
    {
        $config = require base_path('config/content-sync.php');
        $profile = (array) ($config['profiles'][$profileName] ?? []);
        $database = (array) ($profile['database'] ?? []);
        $uploads = (array) ($profile['uploads'] ?? []);

        foreach (['host', 'database', 'username'] as $required) {
            if (trim((string) ($database[$required] ?? '')) === '') {
                return false;
            }
        }

        $mode = strtolower(trim((string) ($uploads['mode'] ?? 'ftp')));
        if ($mode === 'local') {
            return trim((string) ($uploads['path'] ?? '')) !== '';
        }

        foreach (['host', 'username', 'password', 'root'] as $required) {
            if (trim((string) ($uploads[$required] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    private function codeProfileReady(string $profileName): bool
    {
        $config = require base_path('config/content-sync.php');
        $profile = (array) ($config['profiles'][$profileName] ?? []);
        $code = (array) ($profile['code_deploy'] ?? []);
        $mode = strtolower(trim((string) ($code['mode'] ?? 'ftp')));

        if ($mode === 'local') {
            return trim((string) ($code['root'] ?? '')) !== '';
        }

        foreach (['host', 'username', 'password', 'root'] as $required) {
            if (trim((string) ($code[$required] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    private function presentStatus(array $status): array
    {
        $items = [];
        foreach ((array) ($status['items'] ?? []) as $item) {
            $items[] = [
                'package_id' => (string) ($item['package_id'] ?? ''),
                'source_profile' => (string) ($item['source_profile'] ?? ''),
                'source_profile_label' => (string) ($item['source_profile_label'] ?? ''),
                'created_at' => (string) ($item['created_at'] ?? ''),
                'is_valid' => (bool) ($item['is_valid'] ?? false),
                'stats' => [
                    'posts' => (int) ($item['stats']['posts'] ?? 0),
                    'links' => (int) ($item['stats']['links'] ?? 0),
                    'configuracoes' => (int) ($item['stats']['configuracoes'] ?? 0),
                    'uploads' => (int) ($item['uploads']['included_files'] ?? 0),
                ],
                'data_files' => array_values(array_map('strval', array_keys((array) ($item['data_files'] ?? [])))),
                'uploads_paths_preview' => array_slice(array_values(array_map('strval', (array) ($item['uploads']['paths'] ?? []))), 0, 5),
                'uploads_paths_extra' => max(0, count((array) ($item['uploads']['paths'] ?? [])) - 5),
                'last_apply' => $this->presentLastApply((array) ($item['applied_targets'] ?? [])),
            ];
        }

        return [
            'package_root' => (string) ($status['package_root'] ?? ''),
            'total_packages' => (int) ($status['total_packages'] ?? 0),
            'latest' => $items[0] ?? null,
            'latest_stage_apply' => is_array($status['latest_stage_apply'] ?? null) ? $status['latest_stage_apply'] : null,
            'latest_production_apply' => is_array($status['latest_production_apply'] ?? null) ? $status['latest_production_apply'] : null,
            'running' => $status['running'] ?? null,
            'items' => $items,
        ];
    }

    private function presentCodeStatus(array $status): array
    {
        $items = [];
        foreach ((array) ($status['items'] ?? []) as $item) {
            $files = array_values(array_map('strval', (array) ($item['files'] ?? [])));
            $items[] = [
                'package_id' => (string) ($item['package_id'] ?? ''),
                'commit' => (string) ($item['commit'] ?? ''),
                'created_at' => (string) ($item['created_at'] ?? ''),
                'files_count' => (int) ($item['files_count'] ?? count($files)),
                'notes' => (string) ($item['notes'] ?? ''),
                'zip_path' => (string) ($item['zip_path'] ?? ''),
                'manifest_path' => (string) ($item['manifest_path'] ?? ''),
                'files_preview' => array_slice($files, 0, 8),
                'files_extra' => max(0, count($files) - 8),
                'last_apply' => $this->presentLastApply((array) ($item['applied_targets'] ?? [])),
            ];
        }

        return [
            'package_root' => (string) ($status['package_root'] ?? ''),
            'total_packages' => (int) ($status['total_packages'] ?? 0),
            'latest' => $items[0] ?? null,
            'latest_stage_apply' => is_array($status['latest_stage_apply'] ?? null) ? $status['latest_stage_apply'] : null,
            'latest_production_apply' => is_array($status['latest_production_apply'] ?? null) ? $status['latest_production_apply'] : null,
            'items' => $items,
        ];
    }

    private function presentLastApply(array $targets): ?array
    {
        if ($targets === []) {
            return null;
        }

        $last = $targets[count($targets) - 1] ?? null;
        if (!is_array($last)) {
            return null;
        }

        return [
            'target_profile' => (string) ($last['target_profile'] ?? ''),
            'target_profile_label' => (string) ($last['target_profile_label'] ?? ''),
            'applied_at' => (string) ($last['applied_at'] ?? ''),
        ];
    }
}