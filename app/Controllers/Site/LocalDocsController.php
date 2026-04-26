<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Support\LocalOnlyAccess;
use App\Support\View;

final class LocalDocsController
{
    public function index(): void
    {
        $this->ensureLocalOnly();

        View::render('site/local-docs-v2', [
            'title' => 'Documentacao Local | Estrategia Nerd',
            'meta_description' => 'Base tecnica local do projeto Estrategia Nerd.',
            'site_chrome' => false,
            'project_version' => $this->projectVersion(),
            'generated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function backlog(): void
    {
        $this->ensureLocalOnly();

        View::render('site/local-backlog', [
            'title' => 'Backlog Tecnico Local | Estrategia Nerd',
            'meta_description' => 'Backlog tecnico de evolucao do sistema Estrategia Nerd.',
            'site_chrome' => false,
            'project_version' => $this->projectVersion(),
            'generated_at' => date('Y-m-d H:i:s'),
        ]);
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
}
