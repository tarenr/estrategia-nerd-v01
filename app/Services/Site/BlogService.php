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

        return [
            'title' => 'Blog | ' . (string) portal_config('nome_site', 'Estrategia Nerd'),
            'site_chrome' => false,
            'blog_page' => true,
            'blog_featured' => $featuredRow ? $this->normalizePost($featuredRow) : null,
            'blog_posts' => $this->normalizePosts($pagination['items'] ?? []),
            'blog_categories' => $this->normalizeCategories($this->categorias->listForBlog()),
            'blog_filters' => [
                'q' => $search,
                'categoria' => $category,
            ],
            'blog_pagination' => [
                'page' => (int) ($pagination['page'] ?? 1),
                'pages' => (int) ($pagination['pages'] ?? 1),
                'total' => (int) ($pagination['total'] ?? 0),
            ],
            'site_meta' => $this->siteMeta(),
        ];
    }

    private function siteMeta(): array
    {
        return [
            'name' => (string) portal_config('nome_site', 'Estrategia Nerd'),
            'description' => (string) portal_config('descricao_site', 'Conteudo, tecnologia, cultura geek e oportunidades em um so lugar.'),
            'kicker' => (string) portal_config('site_kicker', 'Portal geek estrategico'),
            'footer' => (string) portal_config('footer_texto', 'Estrategia Nerd - Conteudo, links e ofertas geek'),
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
            'titulo' => (string) ($item['titulo'] ?? ''),
            'resumo' => trim((string) ($item['resumo'] ?? '')),
            'categoria_nome' => (string) ($item['categoria_nome'] ?? 'Sem categoria'),
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
                'nome' => (string) ($item['nome'] ?? ''),
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
}
