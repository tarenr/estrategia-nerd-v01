<?php
declare(strict_types=1);

namespace App\Services\Site;

use App\Repositories\LinkRepository;

final class CentralService
{
    public function __construct(private LinkRepository $links)
    {
    }

    public function getViewModel(): array
    {
        $items = $this->normalizeLinks($this->links->listForCentral(120));
        [$featuredLink, $groups] = $this->partitionLinks($items);
        $siteMeta = $this->buildSiteMeta();
        $siteName = (string) ($siteMeta['name'] ?? 'Estratégia Nerd');
        $title = 'Central Nerd | Links, Ofertas e Atalhos Oficiais de ' . $siteName;
        $metaDescription = (string) portal_config(
            'bio_descricao',
            'Central Nerd com links oficiais, promoções, cupons e atalhos rápidos do Estratégia Nerd para clique direto.'
        );
        $canonicalUrl = url('/central-nerd');
        $metaImage = (string) ($siteMeta['logo'] ?: $siteMeta['avatar'] ?: $siteMeta['brand_symbol']);

        return [
            'title' => $title,
            'meta_description' => $metaDescription,
            'canonical_url' => $canonicalUrl,
            'meta_image' => $metaImage,
            'og_type' => 'website',
            'structured_data' => $this->buildStructuredData($title, $metaDescription, $canonicalUrl, $siteName),
            'site_chrome' => false,
            'body_class' => 'central-nerd-body',
            'site_meta' => $siteMeta,
            'central_featured_link' => $featuredLink,
            'central_groups' => $groups,
            'central_theme' => 'cyan-blue',
            'central_total_links' => count($items),
            'central_quick_links' => $this->buildQuickLinks(),
        ];
    }

    private function buildSiteMeta(): array
    {
        return [
            'name' => (string) portal_config('nome_site', 'Estratégia Nerd'),
            'description' => (string) portal_config('descricao_site', 'Conteúdo, tecnologia, cultura geek e oportunidades em um só lugar.'),
            'kicker' => (string) portal_config('site_kicker', 'Portal geek estratégico'),
            'footer' => (string) portal_config('footer_texto', 'Estratégia Nerd - Conteúdo, links e ofertas geek'),
            'bio_title' => (string) portal_config('bio_titulo', 'Central Nerd'),
            'bio_description' => (string) portal_config('bio_descricao', 'Ofertas, descontos e novidades para geeks e tech lovers!'),
            'email' => (string) portal_config('email_contato', ''),
            'instagram' => (string) portal_config('instagram_url', ''),
            'tiktok' => (string) portal_config('tiktok_url', ''),
            'kwai' => (string) portal_config('kwai_url', ''),
            'youtube' => (string) portal_config('youtube_url', ''),
            'telegram' => (string) portal_config('telegram_url', ''),
            'whatsapp' => (string) portal_config('whatsapp_url', ''),
            'logo' => $this->toPublicUrl((string) portal_config('logo_url', '')),
            'brand_symbol' => $this->toPublicUrl((string) portal_config('brand_symbol_url', '')),
            'avatar' => $this->toPublicUrl((string) portal_config('bio_avatar_url', '')),
        ];
    }

    private function normalizeLinks(array $items): array
    {
        return array_map(function (array $item): array {
            $tipo = (string) ($item['tipo'] ?? 'produto');
            $promocao = (int) ($item['promocao'] ?? 0) === 1;
            $discountRaw = trim((string) ($item['desconto_percentual'] ?? ''));

            return [
                'id' => (int) ($item['id'] ?? 0),
                'titulo' => public_text(trim((string) ($item['titulo'] ?? ''))),
                'slug' => (string) ($item['slug'] ?? ''),
                'url' => trim((string) ($item['url'] ?? '#')),
                'tipo' => $tipo,
                'promocao' => $promocao,
                'group' => public_text(trim((string) ($item['subgrupo_publico'] ?? ''))),
                'descricao' => public_text(trim((string) ($item['descricao'] ?? ''))),
                'cta' => public_text(trim((string) ($item['texto_botao'] ?? '')) ?: trim((string) ($item['cta_curto'] ?? '')) ?: $this->defaultCta($tipo)),
                'cta_short' => public_text(trim((string) ($item['cta_curto'] ?? ''))),
                'selo' => public_text(trim((string) ($item['selo'] ?? ''))),
                'imagem' => $this->toPublicUrl((string) ($item['imagem'] ?? '')),
                'destaque' => (int) ($item['destaque'] ?? 0) === 1,
                'posicao' => (int) ($item['posicao'] ?? 0),
                'tone' => $this->toneFor($tipo, $promocao),
                'discount' => $discountRaw,
                'discount_text' => $this->normalizeDiscountText($discountRaw),
                'discount_context' => trim((string) ($item['desconto_contexto'] ?? '')),
                'coupon_code' => trim((string) ($item['codigo_cupom'] ?? '')),
            ];
        }, $items);
    }

    private function partitionLinks(array $items): array
    {
        $featured = null;
        $promoItems = [];
        $productGroups = [];
        $couponItems = [];
        $contentItems = [];
        $socialItems = [];
        $serviceItems = [];

        foreach ($items as $item) {
            if ($featured === null && $item['destaque']) {
                $featured = $item;
            }

            if ($item['tipo'] === 'produto' && $item['promocao']) {
                $promoItems[] = $item;
                continue;
            }

            if ($item['tipo'] === 'produto') {
                $groupLabel = $item['group'] !== '' ? $item['group'] : 'Produtos';
                $groupKey = $this->slugifyGroup($groupLabel);
                if (!isset($productGroups[$groupKey])) {
                    $productGroups[$groupKey] = [
                        'slug' => $groupKey,
                        'label' => $groupLabel,
                        'subtitle' => '',
                        'tone' => 'product',
                        'items' => [],
                    ];
                }
                $productGroups[$groupKey]['items'][] = $item;
                continue;
            }

            match ($item['tipo']) {
                'cupom' => $couponItems[] = $item,
                'conteudo' => $contentItems[] = $item,
                'rede_social' => $socialItems[] = $item,
                'servico' => $serviceItems[] = $item,
                default => null,
            };
        }

        if ($featured === null) {
            foreach ($items as $item) {
                if ($item['tipo'] === 'produto' && $item['promocao']) {
                    $featured = $item;
                    break;
                }
            }
        }

        if ($featured === null) {
            $featured = $items[0] ?? null;
        }

        $groups = [];
        if ($promoItems !== []) {
            $groups[] = [
                'slug' => 'promocoes',
                'label' => 'PROMOÇÕES E OFERTAS',
                'subtitle' => 'As melhores ofertas selecionadas pra você economizar sem perder tempo.',
                'tone' => 'promo',
                'open' => false,
                'items' => $promoItems,
            ];
        }

        uasort($productGroups, static function (array $a, array $b): int {
            $aFirst = (int) ($a['items'][0]['posicao'] ?? 999999);
            $bFirst = (int) ($b['items'][0]['posicao'] ?? 999999);
            return $aFirst <=> $bFirst;
        });

        foreach ($productGroups as $group) {
            $group['open'] = false;
            $groups[] = $group;
        }

        $tailGroups = [
            ['slug' => 'cupons', 'label' => 'CUPONS DE DESCONTO', 'subtitle' => 'Pegue cupons ativos e pague mais barato nos seus produtos favoritos.', 'tone' => 'coupon', 'items' => $couponItems],
            ['slug' => 'conteudo', 'label' => 'CONTEÚDO', 'subtitle' => 'Guias, dicas e conteúdos nerds pra você aprender e escolher melhor.', 'tone' => 'content', 'items' => $contentItems],
            ['slug' => 'rede-social', 'label' => 'REDE SOCIAL', 'subtitle' => 'Fique por dentro de tudo que rola no Estratégia Nerd em tempo real.', 'tone' => 'social', 'items' => $socialItems],
            ['slug' => 'servicos', 'label' => 'Serviços', 'subtitle' => 'Soluções, trabalhos e acessos especiais.', 'tone' => 'service', 'items' => $serviceItems],
        ];

        foreach ($tailGroups as $group) {
            if ($group['items'] === []) {
                continue;
            }
            $group['open'] = false;
            $groups[] = $group;
        }

        return [$featured, $groups];
    }

    private function defaultCta(string $tipo): string
    {
        return match ($tipo) {
            'cupom' => 'Abrir site',
            'rede_social' => 'Abrir perfil',
            'servico' => 'Conhecer serviço',
            'conteudo' => 'Acessar conteúdo',
            default => 'Ver produto',
        };
    }

    private function toneFor(string $tipo, bool $promocao): string
    {
        if ($tipo === 'produto' && $promocao) {
            return 'promo';
        }

        return match ($tipo) {
            'cupom' => 'coupon',
            'conteudo' => 'content',
            'rede_social' => 'social',
            'servico' => 'service',
            default => 'product',
        };
    }

    private function normalizeDiscountText(string $discount): string
    {
        $discount = public_text(trim($discount));
        if ($discount === '') {
            return '';
        }

        if (preg_match('/[%$]|r\\$/i', $discount) === 1) {
            return $discount;
        }

        if (preg_match('/^\\d+(?:[\\.,]\\d+)?$/', $discount) === 1) {
            return $discount . '%';
        }

        return $discount;
    }

    private function toPublicUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return preg_match('~^https?://~i', $value) ? $value : url('/' . ltrim($value, '/'));
    }

    private function slugifyGroup(string $label): string
    {
        $value = trim(mb_strtolower($label));
        if ($value === '') {
            return 'produtos';
        }

        if (function_exists('iconv')) {
            $normalized = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($normalized) && $normalized !== '') {
                $value = $normalized;
            }
        }

        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? 'produtos';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'produtos';
    }

    private function buildStructuredData(string $title, string $metaDescription, string $canonicalUrl, string $siteName): array
    {
        return [[
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $title,
            'url' => $canonicalUrl,
            'description' => $metaDescription,
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => url('/'),
            ],
        ]];
    }

    private function buildQuickLinks(): array
    {
        $links = [[
            'label' => 'Home',
            'url' => url('/'),
        ]];

        if (site_section_public_active('blog')) {
            $links[] = [
                'label' => 'Blog',
                'url' => site_section_href('blog'),
            ];
        }

        if (site_section_public_active('newsletter')) {
            $links[] = [
                'label' => 'Newsletter',
                'url' => site_section_href('newsletter'),
            ];
        }

        return $links;
    }
}
