<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\ConfiguracaoRepository;
use App\Services\Site\SitemapCacheService;
use App\Support\SystemActivityLogger;

final class ConfiguracoesService
{
    /** @var array<string, string> */
    private const DEFAULTS = [
        'nome_site' => 'Estrategia Nerd',
        'site_url' => '',
        'descricao_site' => '',
        'site_kicker' => 'Portal geek estrategico',
        'email_contato' => '',
        'logo_url' => '',
        'brand_symbol_url' => '',
        'favicon_url' => '',
        'bio_avatar_url' => '',
        'sobre_imagem_url' => '',
        'bio_titulo' => 'Estrategia Nerd',
        'bio_descricao' => '',
        'meta_title_padrao' => '',
        'meta_description_padrao' => '',
        'footer_texto' => '',
        'instagram_url' => '',
        'tiktok_url' => '',
        'kwai_url' => '',
        'youtube_url' => '',
        'telegram_url' => '',
        'whatsapp_url' => '',
    ];

    public function __construct(
        private ConfiguracaoRepository $configuracoes,
        private MidiaService $midia,
        private SitemapCacheService $sitemapCache,
        private string $targetEnvironment = 'local',
    ) {
    }

    public function getIndexViewModel(array $old = [], array $errors = []): array
    {
        $stored = $this->configuracoes->all();
        $form = array_merge(self::DEFAULTS, $stored, $old);

        return [
            'title' => 'Configuracoes',
            'form' => $form,
            'errors' => $errors,
            'media_items' => $this->allowsLocalMediaManagement() ? $this->midia->recentImages(12) : [],
            'target_environment' => $this->targetEnvironment,
            'target_environment_label' => environment_label($this->targetEnvironment),
            'is_remote_target' => $this->targetEnvironment !== current_environment(),
            'allow_media_uploads' => $this->allowsLocalMediaManagement(),
            'requires_production_confirmation' => requires_production_confirmation($this->targetEnvironment),
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @return array{ok: bool, errors?: array<string, string>, old?: array<string, string>}
     */
    public function save(array $post, array $files): array
    {
        $stored = $this->configuracoes->all();
        $before = array_merge(self::DEFAULTS, $stored);
        $data = $this->normalizeForm($post);
        $errors = $this->validate($data);

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'old' => $data];
        }

        if (!$this->allowsLocalMediaManagement()) {
            foreach (['logo_upload', 'brand_symbol_upload', 'favicon_upload', 'bio_avatar_upload', 'sobre_imagem_upload'] as $input) {
                $file = $files[$input] ?? null;
                if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $errors['media_remote'] = 'Upload de imagem ainda nao esta habilitado para ambiente remoto. Use URL/caminho existente neste alvo.';
                    break;
                }
            }
        }

        $uploads = [
            'logo_upload' => ['field' => 'logo_url', 'base' => 'portal-logo'],
            'brand_symbol_upload' => ['field' => 'brand_symbol_url', 'base' => 'brand-symbol'],
            'favicon_upload' => ['field' => 'favicon_url', 'base' => 'portal-favicon'],
            'bio_avatar_upload' => ['field' => 'bio_avatar_url', 'base' => 'bio-avatar'],
            'sobre_imagem_upload' => ['field' => 'sobre_imagem_url', 'base' => 'sobre-imagem'],
        ];

        if ($errors === [] && $this->allowsLocalMediaManagement()) {
            foreach ($uploads as $input => $meta) {
                $result = $this->midia->storeUploadedImage($files[$input] ?? null, 'configuracoes', $meta['base'], true);
                if (($result['ok'] ?? false) !== true) {
                    $errors[$meta['field']] = (string) ($result['error'] ?? 'Falha no upload da imagem.');
                    continue;
                }

                if (($result['skipped'] ?? false) !== true && isset($result['path'])) {
                    $data[$meta['field']] = (string) $result['path'];
                }
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'old' => $data];
        }

        $this->configuracoes->saveMany($data);
        if ($this->targetEnvironment === current_environment()) {
            $this->sitemapCache->refreshQuietly();
        }

        SystemActivityLogger::write('system', 'settings_saved', [
            'operation_id' => $this->buildOperationId(),
            'module' => 'settings',
            'current_environment' => current_environment(),
            'target_environment' => $this->targetEnvironment,
            'status' => 'ok',
            'before' => $before,
            'after' => $data,
        ]);

        return ['ok' => true];
    }

    private function allowsLocalMediaManagement(): bool
    {
        return $this->targetEnvironment === current_environment();
    }

    private function buildOperationId(): string
    {
        return 'settings-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, string>
     */
    private function normalizeForm(array $post): array
    {
        $normalized = [];
        foreach (self::DEFAULTS as $key => $default) {
            $normalized[$key] = trim((string) ($post[$key] ?? $default));
        }

        return $normalized;
    }

    /**
     * @param array<string, string> $data
     * @return array<string, string>
     */
    private function validate(array $data): array
    {
        $errors = [];

        if ($data['nome_site'] === '') {
            $errors['nome_site'] = 'Informe o nome do portal.';
        } elseif (mb_strlen($data['nome_site']) > 120) {
            $errors['nome_site'] = 'Use ate 120 caracteres no nome do portal.';
        }

        if ($data['bio_titulo'] === '') {
            $errors['bio_titulo'] = 'Informe o titulo da pagina de links.';
        } elseif (mb_strlen($data['bio_titulo']) > 120) {
            $errors['bio_titulo'] = 'Use ate 120 caracteres no titulo da bio.';
        }

        foreach (['descricao_site' => 255, 'bio_descricao' => 255, 'site_kicker' => 120] as $field => $max) {
            if (mb_strlen($data[$field]) > $max) {
                $errors[$field] = 'Use ate ' . $max . ' caracteres neste campo.';
            }
        }

        foreach (['meta_title_padrao' => 160, 'meta_description_padrao' => 255, 'footer_texto' => 180] as $field => $max) {
            if (mb_strlen($data[$field]) > $max) {
                $errors[$field] = 'Use ate ' . $max . ' caracteres neste campo.';
            }
        }

        if ($data['email_contato'] !== '' && filter_var($data['email_contato'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email_contato'] = 'Informe um email valido.';
        }

        foreach (['site_url', 'logo_url', 'brand_symbol_url', 'favicon_url', 'bio_avatar_url', 'sobre_imagem_url', 'instagram_url', 'tiktok_url', 'kwai_url', 'youtube_url', 'telegram_url', 'whatsapp_url'] as $field) {
            if (mb_strlen($data[$field]) > 255) {
                $errors[$field] = 'Use ate 255 caracteres neste campo.';
            }
        }

        foreach (['site_url', 'instagram_url', 'tiktok_url', 'kwai_url', 'youtube_url', 'telegram_url', 'whatsapp_url'] as $field) {
            $value = $data[$field];
            if ($value === '') {
                continue;
            }

            if (!preg_match('~^(https?://|/)~i', $value)) {
                $errors[$field] = 'Use uma URL valida iniciando com https:// ou um caminho interno.';
            }
        }

        return $errors;
    }
}
