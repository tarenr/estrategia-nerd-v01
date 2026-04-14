<?php
declare(strict_types=1);

namespace App\Services\Site;

use App\Repositories\CategoriaPostRepository;
use App\Repositories\PostRepository;

final class BlogService
{
    public function __construct(
        private PostRepository $posts,
        private CategoriaPostRepository $categorias,
    ) {
    }

    public function getViewModel(array $filters = []): array
    {
        $search = trim((string) ($filters['busca'] ?? ''));
        $category = trim((string) ($filters['categoria'] ?? ''));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $siteName = (string) portal_config('nome_site', 'Estratégia Nerd');
        $categories = $this->categorias->listForBlog();
        $selectedCategoryName = $this->resolveCategoryName($category, $categories);

        $featuredRow = $this->posts->featuredPublicOne([
            'busca' => $search,
            'categoria' => $category,
        ]);
        $featuredId = (int) ($featuredRow['id'] ?? 0);

        $pagination = $this->posts->paginatePublic([
            'busca' => $search,
            'categoria' => $category,
            'exclude_id' => $featuredId,
        ], $page, 9);

        $title = $this->buildTitle($siteName, $selectedCategoryName, $search);
        $metaDescription = $this->buildMetaDescription($selectedCategoryName, $search);
        $canonicalUrl = $this->buildCanonicalUrl($search, $category, $page);
        $siteMeta = $this->siteMeta();
        $metaImage = (string) ($siteMeta['brand_symbol'] ?? '');

        return [
            'title' => $title,
            'meta_description' => $metaDescription,
            'canonical_url' => $canonicalUrl,
            'meta_image' => $metaImage,
            'og_type' => 'website',
            'structured_data' => $this->buildStructuredData($title, $metaDescription, $canonicalUrl, $siteName),
            'site_chrome' => false,
            'blog_page' => true,
            'blog_featured' => $featuredRow ? $this->normalizePost($featuredRow) : null,
            'blog_posts' => $this->normalizePosts($pagination['items'] ?? []),
            'blog_categories' => $this->normalizeCategories($categories),
            'blog_filters' => [
                'q' => $search,
                'categoria' => $category,
            ],
            'blog_pagination' => [
                'page' => (int) ($pagination['page'] ?? 1),
                'pages' => (int) ($pagination['pages'] ?? 1),
                'total' => (int) ($pagination['total'] ?? 0),
            ],
            'site_meta' => $siteMeta,
            'blog_context_links' => $this->buildContextLinks(),
        ];
    }

    private function siteMeta(): array
    {
        return [
            'name' => (string) portal_config('nome_site', 'Estratégia Nerd'),
            'description' => (string) portal_config('descricao_site', 'Conteúdo, tecnologia, cultura geek e oportunidades em um só lugar.'),
            'kicker' => (string) portal_config('site_kicker', 'Portal geek estratégico'),
            'footer' => (string) portal_config('footer_texto', 'Estratégia Nerd - Conteúdo, links e ofertas geek'),
            'email' => (string) portal_config('email_contato', ''),
            'instagram' => (string) portal_config('instagram_url', ''),
            'tiktok' => (string) portal_config('tiktok_url', ''),
            'kwai' => (string) portal_config('kwai_url', ''),
            'youtube' => (string) portal_config('youtube_url', ''),
            'telegram' => (string) portal_config('telegram_url', ''),
            'whatsapp' => (string) portal_config('whatsapp_url', ''),
            'brand_symbol' => $this->toPublicUrl((string) portal_config('brand_symbol_url', '')),
        ];
    }

    private function normalizePosts(array $items): array
    {
        return array_map(fn (array $item): array => $this->normalizePost($item), $items);
    }

    private function normalizePost(array $item): array
    {
        $image = trim((string) ($item['imagem_capa'] ?? ''));
        if ($image === '') {
            $image = trim((string) ($item['imagem_thumb'] ?? ''));
        }

        return [
            'id' => (int) ($item['id'] ?? 0),
            'titulo' => public_text((string) ($item['titulo'] ?? '')),
            'resumo' => public_text(trim((string) ($item['resumo'] ?? ''))),
            'categoria_nome' => public_text((string) ($item['categoria_nome'] ?? 'Sem categoria')),
            'categoria_slug' => (string) ($item['categoria_slug'] ?? ''),
            'categoria_cor' => (string) ($item['categoria_cor'] ?? '#00d4ff'),
            'imagem' => $this->toPublicUrl($image),
            'tempo_leitura' => (int) ($item['tempo_leitura'] ?? 5),
            'views' => (int) ($item['views'] ?? 0),
            'url' => url('/post/' . (string) ($item['slug'] ?? '')),
            'data' => $this->formatDate((string) ($item['data_publicacao'] ?? '')),
        ];
    }

    private function normalizeCategories(array $items): array
    {
        return array_map(static function (array $item): array {
            return [
                'nome' => public_text((string) ($item['nome'] ?? '')),
                'slug' => (string) ($item['slug'] ?? ''),
                'cor' => (string) ($item['cor'] ?? '#00d4ff'),
                'total_posts' => (int) ($item['total_posts'] ?? 0),
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

    private function toPublicUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return preg_match('~^https?://~i', $value) ? $value : url('/' . ltrim($value, '/'));
    }

    private function buildTitle(string $siteName, string $categoryName, string $search): string
    {
        if ($search !== '') {
            return 'Busca: ' . $search . ' | Blog ' . $siteName;
        }

        if ($categoryName !== '') {
            return 'Blog ' . $siteName . ' | ' . $categoryName;
        }

        return 'Blog ' . $siteName . ' | Reviews, Comparativos e Guias de Tecnologia, Games e Gadgets';
    }

    private function buildMetaDescription(string $categoryName, string $search): string
    {
        if ($search !== '') {
            return 'Resultados da busca no blog do Estratégia Nerd com reviews, comparativos, listas e guias sobre tecnologia, games e gadgets.';
        }

        if ($categoryName !== '') {
            return 'Posts da categoria ' . $categoryName . ' no blog do Estratégia Nerd com conteúdo prático para comparar melhor e decidir com mais contexto.';
        }

        return 'Blog do Estratégia Nerd com reviews, comparativos, guias, listas e dicas de tecnologia, games, gadgets e cultura geek para decidir melhor.';
    }

    private function buildCanonicalUrl(string $search, string $category, int $page): string
    {
        $params = [];
        if ($search !== '') {
            $params['q'] = $search;
        }
        if ($category !== '' && $category !== 'all') {
            $params['categoria'] = $category;
        }
        if ($page > 1) {
            $params['page'] = $page;
        }

        $query = http_build_query($params);
        return url('/blog' . ($query !== '' ? '?' . $query : ''));
    }

    private function resolveCategoryName(string $slug, array $categories): string
    {
        if ($slug === '' || $slug === 'all') {
            return '';
        }

        foreach ($categories as $category) {
            if ((string) ($category['slug'] ?? '') === $slug) {
                return public_text((string) ($category['nome'] ?? ''));
            }
        }

        return '';
    }

    private function buildStructuredData(string $title, string $metaDescription, string $canonicalUrl, string $siteName): array
    {
        return [[
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $title,
            'url' => $canonicalUrl,
            'description' => $metaDescription,
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => url('/'),
            ],
            'about' => [
                ['@type' => 'Thing', 'name' => 'Tecnologia'],
                ['@type' => 'Thing', 'name' => 'Games'],
                ['@type' => 'Thing', 'name' => 'Gadgets'],
                ['@type' => 'Thing', 'name' => 'Cultura geek'],
            ],
        ]];
    }

    private function buildContextLinks(): array
    {
        $links = [[
            'label' => 'home',
            'url' => url('/'),
        ]];

        if (site_section_public_active('central_nerd')) {
            $links[] = [
                'label' => 'central nerd',
                'url' => site_section_href('central_nerd'),
            ];
        }

        if (site_section_public_active('newsletter')) {
            $links[] = [
                'label' => 'newsletter',
                'url' => site_section_href('newsletter'),
            ];
        }

        return $links;
    }
}
