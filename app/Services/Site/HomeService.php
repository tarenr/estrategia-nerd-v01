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
        $featuredPosts = $this->normalizePosts($this->posts->featuredPublic(2));
        $latestPosts = $this->normalizePosts($this->posts->latestPublicWithCategoria(6, array_map(static fn (array $post): int => (int) ($post['id'] ?? 0), $featuredPosts)));
        $categories = $this->normalizeCategories($this->categorias->listForHome(4));
        $links = $this->normalizeLinks($this->links->listForHome(6));

        return [
            'title' => $this->buildTitle(),
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
            'site_meta' => [
                'name' => (string) portal_config('nome_site', 'Estratégia Nerd'),
                'description' => (string) portal_config('descricao_site', 'Conteúdo, tecnologia, cultura geek e oportunidades em um só lugar.'),
                'kicker' => (string) portal_config('site_kicker', 'Portal geek estratégico'),
                'footer' => (string) portal_config('footer_texto', 'Estratégia Nerd - Conteúdo, links e ofertas geek'),
                'bio_title' => (string) portal_config('bio_titulo', portal_config('nome_site', 'Estratégia Nerd')),
                'bio_description' => (string) portal_config('bio_descricao', 'Tecnologia, games, cultura geek e oportunidades selecionadas.'),
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
            ],
        ];
    }

    private function buildTitle(): string
    {
        $seo = trim((string) portal_config('meta_title_padrao', ''));
        if ($seo !== '') {
            return $seo;
        }

        return 'Estratégia Nerd | Tecnologia, Games e Cultura Geek';
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
                'titulo' => (string) ($item['titulo'] ?? ''),
                'slug' => (string) ($item['slug'] ?? ''),
                'resumo' => trim((string) ($item['resumo'] ?? '')),
                'categoria_nome' => (string) ($item['categoria_nome'] ?? 'Sem categoria'),
                'categoria_slug' => (string) ($item['categoria_slug'] ?? ''),
                'categoria_cor' => (string) ($item['categoria_cor'] ?? '#00d4ff'),
                'imagem' => $this->toPublicUrl($image),
                'views' => (int) ($item['views'] ?? 0),
                'tempo_leitura' => (int) ($item['tempo_leitura'] ?? 5),
                'comentarios_count' => (int) ($item['comentarios_count'] ?? 0),
                'url' => url('/post/' . (string) ($item['slug'] ?? '')),
                'data' => $this->formatDate((string) ($item['data_publicacao'] ?? '')),
            ];
        }, $items);
    }

    private function normalizeCategories(array $items): array
    {
        return array_map(static function (array $item): array {
            return [
                'nome' => (string) ($item['nome'] ?? ''),
                'slug' => (string) ($item['slug'] ?? ''),
                'cor' => (string) ($item['cor'] ?? '#00d4ff'),
                'total_posts' => (int) ($item['total_posts'] ?? 0),
                'url' => url('/categoria/' . (string) ($item['slug'] ?? '')),
            ];
        }, $items);
    }

    private function normalizeLinks(array $items): array
    {
        return array_map(function (array $item): array {
            return [
                'titulo' => (string) ($item['titulo'] ?? ''),
                'url' => (string) ($item['url'] ?? '#'),
                'tipo' => (string) ($item['tipo'] ?? 'conteudo'),
                'descricao' => trim((string) ($item['descricao'] ?? '')),
                'cta' => trim((string) ($item['texto_botao'] ?? '')) ?: 'Abrir link',
                'selo' => trim((string) ($item['selo'] ?? '')),
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
