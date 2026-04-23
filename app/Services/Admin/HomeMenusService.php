<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\ConfiguracaoRepository;
use App\Services\Site\SitemapCacheService;
use App\Support\SiteSections;

final class HomeMenusService
{
    public function __construct(
        private ConfiguracaoRepository $configuracoes,
        private SitemapCacheService $sitemapCache,
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
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{ok: bool, errors?: array<string, string>, old?: array<string, array<string, mixed>>}
     */
    public function save(array $post): array
    {
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
        $this->sitemapCache->refreshQuietly();

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
}
