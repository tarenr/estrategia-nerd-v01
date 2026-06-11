<?php
declare(strict_types=1);

namespace App\Services\Site;

use App\Repositories\CategoriaPostRepository;
use App\Repositories\LinkRepository;
use App\Repositories\NewsletterRepository;
use App\Repositories\PostRepository;

final class HomeService
{
    public function __construct(
        private PostRepository $posts,
        private CategoriaPostRepository $categorias,
        private LinkRepository $links,
        private NewsletterRepository $newsletter,
    ) {
    }

    public function getViewModel(): array
    {
        $featuredPosts = $this->normalizePosts($this->posts->featuredPublic(5));
        $latestExcludeIds = array_map(static fn (array $post): int => (int) ($post['id'] ?? 0), $featuredPosts);
        $latestPosts = $this->normalizePosts($this->posts->latestPublicWithCategoria(6, $latestExcludeIds));
        $categories = $this->normalizeCategories($this->categorias->listForHome(4));
        $links = $this->normalizeLinks($this->links->listForHome(6));
        $siteMeta = [
            'name' => (string) portal_config('nome_site', "Estrat\u{00E9}gia Nerd"),
            'description' => "Portal de tecnologia, games, gadgets e cultura geek com reviews, comparativos, guias pr\u{00E1}ticos e oportunidades para decidir melhor.",
            'kicker' => (string) portal_config('site_kicker', "Portal geek estrat\u{00E9}gico"),
            'footer' => "Estrat\u{00E9}gia Nerd | Reviews, comparativos, dicas e oportunidades para decidir melhor",
            'bio_title' => (string) portal_config('bio_titulo', portal_config('nome_site', "Estrat\u{00E9}gia Nerd")),
            'bio_description' => "Tecnologia, games, cultura geek e oportunidades selecionadas.",
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
            'about_image' => $this->toPublicUrl((string) portal_config('sobre_imagem_url', '')),
        ];

        $title = "Estrat\u{00E9}gia Nerd | Tecnologia, Games, Gadgets e Cultura Geek";
        $metaDescription = "Portal de tecnologia, games, gadgets e cultura geek com reviews, comparativos, dicas pr\u{00E1}ticas, listas e ofertas para descobrir o que realmente vale a pena.";

        return [
            'title' => $title,
            'meta_description' => $metaDescription,
            'canonical_url' => url('/'),
            'meta_image' => (string) ($siteMeta['logo'] ?: $siteMeta['brand_symbol']),
            'structured_data' => $this->buildStructuredData($siteMeta, $title, $metaDescription),
            'site_chrome' => false,
            'home_page' => true,
            'stats' => [
                'posts' => $this->posts->countPublished(),
                'links' => $this->links->countPublicActive(),
                'newsletter' => $this->newsletter->countActive(),
                'categorias' => count($categories),
            ],
            'featured_posts' => $featuredPosts,
            'latest_posts' => $latestPosts,
            'categories' => $categories,
            'links' => $links,
            'hero' => [
                'eyebrow' => "Portal geek estrat\u{00E9}gico",
                'descriptor' => "Tecnologia, games, gadgets e cultura geek para descobrir, comparar e decidir melhor",
                'description' => "O Estrat\u{00E9}gia Nerd re\u{00FA}ne reviews, comparativos, dicas pr\u{00E1}ticas, listas e ofertas para quem quer montar setup melhor, cortar ru\u{00ED}do e acertar no pr\u{00F3}ximo clique.",
                'primary_cta' => [
                    'href' => site_section_visible_on_home('blog') ? rtrim(url('/'), '/') . '/#blog' : url('/blog'),
                    'label' => "Explorar o blog",
                ],
                'secondary_cta' => $this->buildSecondaryCta(),
                'support_points' => [
                    "Reviews e comparativos para decidir melhor",
                    "Dicas para setup, hardware e perif\u{00E9}ricos",
                    "Games, cultura geek e oportunidades \u{00FA}teis",
                ],
            ],
            'home_intro' => [
                'title' => "O que voc\u{00EA} encontra no Estrat\u{00E9}gia Nerd",
                'lead' => "Tecnologia \u{00FA}til e cultura geek com contexto de verdade para transformar curiosidade em decis\u{00E3}o melhor.",
                'body' => [
                    "Aqui o foco n\u{00E3}o \u{00E9} publicar por volume. O portal conecta reviews, comparativos, guias, listas e dicas para ajudar voc\u{00EA} a escolher melhor, montar setup com mais intelig\u{00EA}ncia e encontrar conte\u{00FA}do que compensa ler at\u{00E9} o fim.",
                    "Em vez de recomendac\u{00E3}o vazia, a proposta \u{00E9} clara: dar contexto, reduzir ru\u{00ED}do e mostrar o pr\u{00F3}ximo passo com objetividade, seja comparar op\u{00E7}\u{00F5}es, seguir um tema ou aproveitar uma oportunidade real.",
                ],
                'context_links' => $this->buildIntroLinks($categories),
                'pillars' => [
                    [
                        'title' => "Reviews e comparativos",
                        'text' => "An\u{00E1}lises claras para escolher gadgets, perif\u{00E9}ricos, hardware e servi\u{00E7}os com mais seguran\u{00E7}a.",
                    ],
                    [
                        'title' => "Games e cultura geek",
                        'text' => "Listas, especiais, nostalgia e leitura editorial para quem acompanha o lado mais nerd do portal.",
                    ],
                    [
                        'title' => "Guias e dicas pr\u{00E1}ticas",
                        'text' => "Conte\u{00FA}do para montar setup, evitar compra ruim e extrair mais do seu PC sem perder tempo.",
                    ],
                    [
                        'title' => "Ofertas e oportunidades",
                        'text' => "Links e sele\u{00E7}\u{00F5}es com foco em utilidade real, n\u{00E3}o s\u{00F3} em clique f\u{00E1}cil.",
                    ],
                ],
            ],
            'topic_links' => $this->buildTopicLinks($categories),
            'posts_section' => [
                'eyebrow' => "Blog Estrat\u{00E9}gia Nerd",
                'title' => "Guias, reviews, comparativos e listas para continuar explorando",
                'description' => "Os posts recentes conectam tecnologia, gadgets, hardware, games e cultura geek em leituras feitas para ajudar voc\u{00EA} a descobrir, comparar e decidir melhor.",
                'context_links' => $this->buildContextLinks($categories),
            ],
            'site_meta' => $siteMeta,
        ];
    }

    private function buildSecondaryCta(): ?array
    {
        if (site_section_visible_on_home('newsletter')) {
            return ['href' => site_section_href('newsletter'), 'label' => "Receber novidades"];
        }

        return null;
    }

    private function buildStructuredData(array $siteMeta, string $title, string $metaDescription): array
    {
        $baseUrl = url('/');
        $siteName = (string) ($siteMeta['name'] ?? "Estrat\u{00E9}gia Nerd");
        $sameAs = array_values(array_filter([
            trim((string) ($siteMeta['instagram'] ?? '')),
            trim((string) ($siteMeta['tiktok'] ?? '')),
            trim((string) ($siteMeta['kwai'] ?? '')),
            trim((string) ($siteMeta['youtube'] ?? '')),
            trim((string) ($siteMeta['telegram'] ?? '')),
            trim((string) ($siteMeta['whatsapp'] ?? '')),
        ], static fn (string $url): bool => $url !== ''));

        $organization = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $siteName,
            'url' => $baseUrl,
            'description' => $metaDescription,
        ];

        $logo = trim((string) ($siteMeta['logo'] ?? ''));
        if ($logo !== '') {
            $organization['logo'] = $logo;
        }

        if ($sameAs !== []) {
            $organization['sameAs'] = $sameAs;
        }

        $website = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteName,
            'url' => $baseUrl,
        ];

        if (site_section_public_active('blog')) {
            $website['potentialAction'] = [
                '@type' => 'SearchAction',
                'target' => url('/blog?q={search_term_string}'),
                'query-input' => 'required name=search_term_string',
            ];
        }

        $webPage = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $title,
            'url' => $baseUrl,
            'description' => $metaDescription,
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => $baseUrl,
            ],
            'about' => [
                ['@type' => 'Thing', 'name' => 'Tecnologia'],
                ['@type' => 'Thing', 'name' => 'Games'],
                ['@type' => 'Thing', 'name' => 'Gadgets'],
                ['@type' => 'Thing', 'name' => 'Cultura geek'],
            ],
        ];

        return [$organization, $website, $webPage];
    }

    private function buildTopicLinks(array $categories): array
    {
        $descriptions = [
            'hardware' => "Comparativos, builds, upgrades e decis\u{00F5}es mais inteligentes para montar ou melhorar seu setup.",
            'gadgets' => "Reviews, sele\u{00E7}\u{00F5}es e recomenda\u{00E7}\u{00F5}es para quem quer tecnologia \u{00FA}til no dia a dia.",
            'games' => "Listas, especiais e leituras sobre jogos que marcaram, decepcionaram ou ainda merecem aten\u{00E7}\u{00E3}o.",
            'dicas' => "Guias pr\u{00E1}ticos para resolver d\u{00FA}vidas, evitar compra ruim e tirar mais do seu PC.",
            'cultura-geek' => "Conte\u{00FA}do editorial sobre o universo geek, tend\u{00EA}ncias e repert\u{00F3}rio para continuar explorando.",
        ];

        return array_map(static function (array $category) use ($descriptions): array {
            $slug = (string) ($category['slug'] ?? '');
            $name = (string) ($category['nome'] ?? '');

            return [
                'title' => $name,
                'url' => (string) ($category['url'] ?? url('/blog')),
                'count' => (int) ($category['total_posts'] ?? 0),
                'text' => $descriptions[$slug] ?? "Explore conte\u{00FA}dos relacionados a esse tema no blog do portal.",
                'cta' => 'Ver posts de ' . $name,
            ];
        }, $categories);
    }

    private function buildIntroLinks(array $categories): array
    {
        $links = [
            [
                'label' => 'blog',
                'url' => site_section_visible_on_home('blog') ? rtrim(url('/'), '/') . '/#blog' : url('/blog'),
            ],
        ];

        foreach (array_slice($categories, 0, 2) as $category) {
            $links[] = [
                'label' => mb_strtolower((string) ($category['nome'] ?? 'tema'), 'UTF-8'),
                'url' => (string) ($category['url'] ?? url('/blog')),
            ];
        }

        if (site_section_visible_on_home('newsletter')) {
            $links[] = [
                'label' => 'newsletter',
                'url' => site_section_href('newsletter'),
            ];
        }

        return $links;
    }

    private function buildContextLinks(array $categories): array
    {
        $links = [
            [
                'label' => 'blog completo',
                'url' => url('/blog'),
            ],
        ];

        foreach (array_slice($categories, 0, 3) as $category) {
            $links[] = [
                'label' => mb_strtolower((string) ($category['nome'] ?? 'tema'), 'UTF-8'),
                'url' => (string) ($category['url'] ?? url('/blog')),
            ];
        }

        if (site_section_visible_on_home('newsletter')) {
            $links[] = [
                'label' => 'newsletter',
                'url' => site_section_href('newsletter'),
            ];
        }

        return $links;
    }

    private function toPublicUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return preg_match('~^https?://~i', $value) ? $value : url('/' . ltrim($value, '/'));
    }

    private function normalizePosts(array $items): array
    {
        return array_map(function (array $item): array {
            $image = trim((string) ($item['imagem_capa'] ?? ''));
            if ($image === '') {
                $image = trim((string) ($item['imagem_thumb'] ?? ''));
            }

            return [
                'id' => (int) ($item['id'] ?? 0),
                'titulo' => public_text((string) ($item['titulo'] ?? '')),
                'slug' => (string) ($item['slug'] ?? ''),
                'resumo' => public_text(trim((string) ($item['resumo'] ?? ''))),
                'categoria_nome' => public_text((string) ($item['categoria_nome'] ?? 'Sem categoria')),
                'categoria_slug' => (string) ($item['categoria_slug'] ?? ''),
                'categoria_cor' => (string) ($item['categoria_cor'] ?? '#00d4ff'),
                'imagem' => $this->toPublicUrl($image),
                'views' => (int) ($item['views'] ?? 0),
                'curtidas' => (int) ($item['curtidas'] ?? 0),
                'tempo_leitura' => (int) ($item['tempo_leitura'] ?? 5),
                'comentarios_count' => (int) ($item['comentarios_count'] ?? 0),
                'destaque' => (int) ($item['destaque'] ?? 0),
                'url' => url('/post/' . (string) ($item['slug'] ?? '')),
                'data' => $this->formatDate((string) ($item['data_publicacao'] ?? '')),
            ];
        }, $items);
    }

    private function normalizeCategories(array $items): array
    {
        return array_map(static function (array $item): array {
            $slug = (string) ($item['slug'] ?? '');

            return [
                'nome' => public_text((string) ($item['nome'] ?? '')),
                'slug' => $slug,
                'cor' => (string) ($item['cor'] ?? '#00d4ff'),
                'total_posts' => (int) ($item['total_posts'] ?? 0),
                'url' => $slug !== '' ? url('/blog/' . rawurlencode($slug)) : url('/blog'),
            ];
        }, $items);
    }

    private function normalizeLinks(array $items): array
    {
        return array_map(function (array $item): array {
            return [
                'titulo' => public_text((string) ($item['titulo'] ?? '')),
                'url' => (string) ($item['url'] ?? '#'),
                'tipo' => (string) ($item['tipo'] ?? 'conteudo'),
                'descricao' => public_text(trim((string) ($item['descricao'] ?? ''))),
                'cta' => public_text(trim((string) ($item['texto_botao'] ?? '')) ?: 'Abrir link'),
                'selo' => public_text(trim((string) ($item['selo'] ?? ''))),
                'imagem' => $this->toPublicUrl((string) ($item['imagem'] ?? '')),
                'destaque' => (int) ($item['destaque'] ?? 0) === 1,
            ];
        }, $items);
    }

    private function formatDate(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }

        return date('d/m/Y', $timestamp);
    }
}
