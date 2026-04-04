<?php
declare(strict_types=1);

namespace App\Services\Site;

use App\Repositories\PostRepository;

final class BlogService
{
    public function __construct(private PostRepository $posts)
    {
    }

    public function getViewModel(): array
    {
        return [
            'title' => 'Blog | ' . (string) portal_config('nome_site', 'Estrategia Nerd'),
            'site_chrome' => false,
            'blog_posts' => $this->normalizePosts($this->posts->latestPublicWithCategoria(24)),
            'site_meta' => [
                'name' => (string) portal_config('nome_site', 'Estrategia Nerd'),
                'description' => (string) portal_config('descricao_site', 'Conteudo, tecnologia, cultura geek e oportunidades em um so lugar.'),
                'kicker' => (string) portal_config('site_kicker', 'Portal geek estrategico'),
                'brand_symbol' => $this->toPublicUrl((string) portal_config('brand_symbol_url', '')),
                'email' => (string) portal_config('email_contato', ''),
            ],
        ];
    }

    private function normalizePosts(array $items): array
    {
        return array_map(function (array $item): array {
            $image = trim((string) ($item['imagem_capa'] ?? ''));
            if ($image === '') {
                $image = trim((string) ($item['imagem_thumb'] ?? ''));
            }

            return [
                'titulo' => (string) ($item['titulo'] ?? ''),
                'resumo' => trim((string) ($item['resumo'] ?? '')),
                'categoria_nome' => (string) ($item['categoria_nome'] ?? 'Sem categoria'),
                'imagem' => $this->toPublicUrl($image),
                'tempo_leitura' => (int) ($item['tempo_leitura'] ?? 5),
                'url' => url('/post/' . (string) ($item['slug'] ?? '')),
                'data' => $this->formatDate((string) ($item['data_publicacao'] ?? '')),
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
