<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\ConfiguracaoRepository;
use App\Services\Site\SitemapCacheService;
use App\Support\SiteSections;
use App\Support\SystemActivityLogger;

final class HomeMenusService
{
    public function __construct(
        private ConfiguracaoRepository $configuracoes,
        private SitemapCacheService $sitemapCache,
        private string $targetEnvironment = 'local',
    )
    {
    }

    public function getIndexViewModel(array $old = [], array $errors = []): array
    {
        $stored = $this->configuracoes->all();
        $sections = $old !== []
            ? SiteSections::normalizeStored($old)
            : SiteSections::fromStorage((string) ($stored[SiteSections::CONFIG_KEY] ?? ''));

        return [
            'title' => 'Home e Menus',
            'sections' => $sections,
            'summary' => SiteSections::summary($sections),
            'errors' => $errors,
            'target_environment' => $this->targetEnvironment,
            'target_environment_label' => environment_label($this->targetEnvironment),
            'is_remote_target' => $this->targetEnvironment !== current_environment(),
            'requires_production_confirmation' => requires_production_confirmation($this->targetEnvironment),
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{ok: bool, errors?: array<string, string>, old?: array<string, array<string, mixed>>}
     */
    public function save(array $post): array
    {
        $stored = $this->configuracoes->all();
        $beforeSections = SiteSections::fromStorage((string) ($stored[SiteSections::CONFIG_KEY] ?? ''));
        $sections = SiteSections::normalizeForm((array) ($post['sections'] ?? []));
        $errors = $this->validate($sections);

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
                'old' => $sections,
            ];
        }

        $this->configuracoes->saveMany([
            SiteSections::CONFIG_KEY => SiteSections::toStorage($sections),
        ]);

        if ($this->targetEnvironment === current_environment()) {
            $this->sitemapCache->refreshQuietly();
        }

        $operationId = $this->buildOperationId();
        SystemActivityLogger::write('system', 'home_menus_saved', [
            'operation_id' => $operationId,
            'module' => 'home_menus',
            'current_environment' => current_environment(),
            'target_environment' => $this->targetEnvironment,
            'status' => 'ok',
            'before' => [
                'sections' => $beforeSections,
                'summary' => SiteSections::summary($beforeSections),
            ],
            'after' => [
                'sections' => $sections,
                'summary' => SiteSections::summary($sections),
            ],
        ]);

        return ['ok' => true];
    }

    /**
     * @param array<string, array<string, mixed>> $sections
     * @return array<string, string>
     */
    private function validate(array $sections): array
    {
        $errors = [];

        foreach ($sections as $key => $section) {
            $label = trim((string) ($section['label'] ?? ''));
            if ($label === '') {
                $errors[$key . '.label'] = 'Informe um rotulo para este modulo.';
                continue;
            }

            if (mb_strlen($label) > 60) {
                $errors[$key . '.label'] = 'Use ate 60 caracteres no rotulo publico.';
            }
        }

        return $errors;
    }

    private function buildOperationId(): string
    {
        return 'home-menus-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
    }
}
