<?php

declare(strict_types=1);

namespace App\Controllers\Site;

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
