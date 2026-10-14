<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Repositories\LinkRepository;

/**
 * Escolhe ofertas da Central Nerd ligadas ao assunto de um post.
 * Lista da categoria = produtos das prateleiras (subgrupo_publico) ligadas a categoria do post
 * + produtos com palavra em comum com titulo/tags; a cada visita sorteia ate 3 dessa lista.
 */
final class RelatedOffersService
{
    /** @var array<string, list<string>> */
    private const CATEGORY_SHELVES = [
        'hardware' => ['PC Gamer Inicial', 'PC Gamer Intermediário', 'PC Gamer Topo', 'Upgrade Monstro'],
        'games' => ['Setup Gamer', 'Action Figures'],
        'cultura-geek' => ['Action Figures', 'Gadgets'],
        'gadgets' => ['Gadgets', 'Setup Gamer'],
        'dicas' => ['Setup Estratégia Nerd', 'Setup Gamer'],
    ];

    private const MIN_WORD_LENGTH = 4;

    /** Palavras comuns demais para indicar assunto. */
    private const STOPWORDS = [
        'para', 'como', 'mais', 'sobre', 'entre', 'qual', 'quais', 'quando', 'você', 'voce', 'sem', 'com',
        'jogo', 'jogos', 'gamer', 'gaming', 'melhor', 'melhores', 'guia', 'dicas', 'review', 'vale', 'pena',
        'novo', 'nova', 'anos', 'ainda', 'depois', 'antes', 'pode', 'esse', 'essa', 'isso', 'pelo', 'pela',
    ];

    public function __construct(private LinkRepository $links)
    {
    }

    /**
     * @param array<string, mixed> $post Post normalizado (id, titulo, categoria_slug, tags)
     * @return list<array<string, mixed>>
     */
    public function forPost(array $post, int $limit = 3): array
    {
        $postId = (int) ($post['id'] ?? 0);
        if ($postId <= 0) {
            return [];
        }

        try {
            $candidates = $this->links->listActiveProductsForPosts();
        } catch (\Throwable $e) {
            error_log('[RelatedOffersService] ' . $e->getMessage());
            return [];
        }

        if ($candidates === []) {
            return [];
        }

        $shelves = self::CATEGORY_SHELVES[(string) ($post['categoria_slug'] ?? '')] ?? [];
        $postWords = $this->words((string) ($post['titulo'] ?? '') . ' ' . implode(' ', (array) ($post['tags'] ?? [])));

        $pool = [];
        foreach ($candidates as $link) {
            $inShelf = in_array((string) ($link['subgrupo_publico'] ?? ''), $shelves, true);
            if ($inShelf || array_intersect($postWords, $this->words((string) ($link['titulo'] ?? '') . ' ' . (string) ($link['descricao'] ?? ''))) !== []) {
                $pool[] = $link;
            }
        }

        // Sorteio a cada visita (a pagina do post sai com Cache-Control: no-store).
        shuffle($pool);

        $offers = [];
        foreach (array_slice($pool, 0, max(1, $limit)) as $link) {
            $offers[] = [
                'titulo' => (string) ($link['titulo'] ?? ''),
                'descricao' => (string) ($link['descricao'] ?? ''),
                'selo' => (string) ($link['selo'] ?? ''),
                'imagem' => $this->toPublicUrl((string) ($link['imagem'] ?? '')),
                'tracked_url' => url('/link/' . rawurlencode((string) ($link['slug'] ?? '')) . '?origem=post-' . $postId),
            ];
        }

        return $offers;
    }

    private function toPublicUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return preg_match('~^https?://~i', $value) ? $value : url('/' . ltrim($value, '/'));
    }

    /**
     * @return list<string>
     */
    private function words(string $text): array
    {
        $text = mb_strtolower(strip_tags($text));
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $text) ?: [];

        $words = [];
        foreach ($parts as $part) {
            $length = mb_strlen($part);
            if (in_array($part, self::STOPWORDS, true)) {
                continue;
            }
            if ($length >= self::MIN_WORD_LENGTH || ($length >= 2 && preg_match('/\d/', $part) === 1)) {
                $words[$part] = true;
            }
        }

        return array_map('strval', array_keys($words));
    }
}
