<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Support\LocalOnlyAccess;
use App\Support\View;
use Scripts\Operations\OperationLogger;

final class LocalDocsController
{
    public function index(): void
    {
        $this->ensureLocalOnly();

        View::render('site/local-docs-v2', $this->documentationViewData());
    }

    public function backlog(): void
    {
        $this->ensureLocalOnly();

        View::render('site/local-backlog', $this->backlogViewData());
    }

    public function rules(): void
    {
        $this->ensureLocalOnly();

        View::render('site/local-rules', $this->rulesViewData());
    }

    public function changes(): void
    {
        $this->ensureLocalOnly();

        View::render('site/local-changes', $this->changesViewData());
    }

    public function changeDocument(): void
    {
        $this->ensureLocalOnly();

        $document = $this->selectedChangeDocument();
        if ($document === null) {
            http_response_code(404);
            echo 'Documento nao encontrado.';
            exit;
        }

        View::render('site/local-doc-file', [
            'title' => $document['file'] . ' | Estrategia Nerd',
            'meta_description' => 'Documento interno de governanca e historico de mudancas do projeto.',
            'site_chrome' => false,
            'project_version' => $this->projectVersion(),
            'generated_at' => date('Y-m-d H:i:s'),
            'embed_mode' => $this->embedMode(),
            'admin_embed' => false,
            'doc_group' => $document['group'],
            'doc_file' => $document['file'],
            'doc_path' => $document['path'],
            'doc_body' => $document['body'],
            'back_url' => $this->requestedBackUrl(
                $document['group'] === 'governanca'
                    ? url('/local/documentacao?docs_secao=governanca-releases')
                    : url('/local/mudancas')
            ),
            'back_label' => $document['group'] === 'governanca'
                ? 'Voltar para Documentacao'
                : 'Voltar para Mudancas',
        ]);
    }

    public function changeDocumentData(): void
    {
        $this->ensureLocalOnly();

        $document = $this->selectedChangeDocument();
        if ($document === null) {
            http_response_code(404);
            header('Content-Type: application/json; charset=UTF-8');
            echo (string) json_encode([
                'error' => 'Documento nao encontrado.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        header('Content-Type: application/json; charset=UTF-8');
        echo (string) json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * @return array<string,mixed>
     */
    public function documentationViewData(bool $adminEmbed = false): array
    {
        return [
            'title' => 'Documentacao Local | Estrategia Nerd',
            'meta_description' => 'Base tecnica local do projeto Estrategia Nerd.',
            'site_chrome' => false,
            'project_version' => $this->projectVersion(),
            'generated_at' => date('Y-m-d H:i:s'),
            'embed_mode' => $adminEmbed ? true : $this->embedMode(),
            'admin_embed' => $adminEmbed,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function backlogViewData(bool $adminEmbed = false): array
    {
        $activeSection = strtolower(trim((string) ($_GET['secao'] ?? 'visao-geral')));
        $allowedSections = ['visao-geral', 'trilha-complementar', 'fase-1', 'fase-2', 'fase-3', 'fase-4', 'fase-5'];
        if (!in_array($activeSection, $allowedSections, true)) {
            $activeSection = 'visao-geral';
        }

        return [
            'title' => 'Backlog Tecnico Local | Estrategia Nerd',
            'meta_description' => 'Backlog tecnico de evolucao do sistema Estrategia Nerd.',
            'site_chrome' => false,
            'project_version' => $this->projectVersion(),
            'generated_at' => date('Y-m-d H:i:s'),
            'embed_mode' => $adminEmbed ? true : $this->embedMode(),
            'admin_embed' => $adminEmbed,
            'active_section' => $activeSection,
            'section_base_url' => $adminEmbed
                ? url('/admin/base-tecnica?aba=backlog&secao=')
                : url('/local/backlog?secao='),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function rulesViewData(bool $adminEmbed = false): array
    {
        $rulesFile = base_path('docs/CODEX-REGRAS-PERMANENTES.txt');
        $rulesBody = is_file($rulesFile)
            ? (string) file_get_contents($rulesFile)
            : 'Arquivo de regras permanentes nao encontrado.';

        return [
            'title' => 'Regras Permanentes | Estrategia Nerd',
            'meta_description' => 'Regras permanentes de arquitetura, operacao e deploy do projeto Estrategia Nerd.',
            'site_chrome' => false,
            'project_version' => $this->projectVersion(),
            'generated_at' => date('Y-m-d H:i:s'),
            'rules_body' => $rulesBody,
            'embed_mode' => $adminEmbed ? true : $this->embedMode(),
            'admin_embed' => $adminEmbed,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function changesViewData(bool $adminEmbed = false): array
    {
        $selectedDocument = $this->selectedChangeDocument();

        return [
            'title' => 'Mudancas Recentes | Estrategia Nerd',
            'meta_description' => 'Historico local de alteracoes, operacoes e documentos recentes do projeto.',
            'site_chrome' => false,
            'project_version' => $this->projectVersion(),
            'generated_at' => date('Y-m-d H:i:s'),
            'embed_mode' => $adminEmbed ? true : $this->embedMode(),
            'admin_embed' => $adminEmbed,
            'feature_docs' => $this->recentDocs('features'),
            'release_docs' => $this->recentDocs('releases'),
            'change_docs' => $this->allChangeDocs(),
            'selected_change_doc' => $selectedDocument,
            'change_doc_base_url' => $adminEmbed
                ? url('/admin/base-tecnica?aba=mudancas&grupo=')
                : url('/local/mudancas/documento?grupo='),
            'change_doc_data_base_url' => url('/local/mudancas/documento-dados?grupo='),
            'activity_logs' => $this->recentActivityLogs(),
            'operation_logs' => $this->recentOperationLogs(),
        ];
    }

    public function blogStructures(): void
    {
        $this->ensureLocalOnly();

        $file = base_path('docs/blog-estruturas-de-conteudo.html');
        if (!is_file($file)) {
            http_response_code(404);
            echo 'Arquivo de documentacao nao encontrado.';
            exit;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
        }

        readfile($file);
        exit;
    }

    private function ensureLocalOnly(): void
    {
        LocalOnlyAccess::enforce();
    }

    private function embedMode(): bool
    {
        return (string) ($_GET['embed'] ?? '0') === '1';
    }

    private function projectVersion(): string
    {
        $default = 'local';
        $projectRoot = base_path('');
        $command = sprintf(
            'git -C %s rev-parse --short HEAD 2>NUL',
            escapeshellarg($projectRoot)
        );
        $result = @shell_exec($command);
        $version = trim((string) $result);

        return $version !== '' ? $version : $default;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function recentDocs(string $folder): array
    {
        $directory = base_path('docs/' . trim($folder, '/'));
        if (!is_dir($directory)) {
            return [];
        }

        $items = [];
        $files = glob($directory . DIRECTORY_SEPARATOR . '*.md');
        if ($files === false) {
            return [];
        }

        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        foreach (array_slice($files, 0, 8) as $file) {
            $items[] = [
                'name' => basename($file),
                'path' => $file,
                'updated_at' => date('Y-m-d H:i:s', (int) filemtime($file)),
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function allDocs(string $folder, string $typeLabel): array
    {
        $directory = base_path('docs/' . trim($folder, '/'));
        if (!is_dir($directory)) {
            return [];
        }

        $items = [];
        $files = glob($directory . DIRECTORY_SEPARATOR . '*.md');
        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $items[] = [
                'type' => $typeLabel,
                'name' => basename($file),
                'path' => $file,
                'updated_at' => date('Y-m-d H:i:s', (int) filemtime($file)),
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function allChangeDocs(): array
    {
        $items = array_merge(
            $this->allDocs('features', 'Feature'),
            $this->allDocs('releases', 'Release')
        );

        usort(
            $items,
            static fn (array $left, array $right): int => strtotime((string) ($right['updated_at'] ?? '')) <=> strtotime((string) ($left['updated_at'] ?? ''))
        );

        return $items;
    }

    /**
     * @return array<string, string>|null
     */
    private function selectedChangeDocument(): ?array
    {
        $group = strtolower(trim((string) ($_GET['grupo'] ?? '')));
        $file = basename(trim((string) ($_GET['arquivo'] ?? '')));
        $allowedGroups = ['features', 'releases', 'governanca'];

        if (!in_array($group, $allowedGroups, true) || $file === '' || !str_ends_with(strtolower($file), '.md')) {
            return null;
        }

        $path = base_path('docs/' . $group . '/' . $file);
        if (!is_file($path)) {
            return null;
        }

        return [
            'group' => $group,
            'file' => $file,
            'path' => $path,
            'body' => (string) file_get_contents($path),
            'updated_at' => date('Y-m-d H:i:s', (int) filemtime($path)),
        ];
    }

    private function requestedBackUrl(string $default): string
    {
        $backUrl = trim((string) ($_GET['redirect_to'] ?? ''));
        if ($backUrl === '') {
            return $default;
        }

        $localRoot = rtrim(url('/'), '/');
        if (!str_starts_with($backUrl, $localRoot)) {
            return $default;
        }

        return $backUrl;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentActivityLogs(): array
    {
        $directory = base_path('storage/logs');
        if (!is_dir($directory)) {
            return [];
        }

        $entries = [];
        $files = glob($directory . DIRECTORY_SEPARATOR . '*.log');
        if ($files === false) {
            return [];
        }

        rsort($files, SORT_STRING);

        foreach ($files as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines) || $lines === []) {
                continue;
            }

            for ($index = count($lines) - 1; $index >= 0; $index--) {
                $payload = json_decode((string) $lines[$index], true);
                if (!is_array($payload)) {
                    continue;
                }

                $entries[] = [
                    'channel' => preg_replace('/-\d{4}-\d{2}\.log$/', '', basename($file)) ?: basename($file),
                    'timestamp' => (string) ($payload['timestamp'] ?? ''),
                    'event' => (string) ($payload['event'] ?? 'event'),
                    'context' => is_array($payload['context'] ?? null) ? $payload['context'] : [],
                ];

                if (count($entries) >= 20) {
                    return $entries;
                }
            }
        }

        return $entries;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentOperationLogs(): array
    {
        require_once base_path('scripts/operations/OperationLogger.php');

        $config = require base_path('config/backup.php');
        $root = trim((string) ($config['backup_root'] ?? ''));
        if ($root === '') {
            return [];
        }

        try {
            $logger = new OperationLogger($root);
            return $logger->recentEntries(20);
        } catch (\Throwable) {
            return [];
        }
    }
}
