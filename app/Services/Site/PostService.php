<?php
declare(strict_types=1);

namespace App\Services\Site;

use App\Repositories\ComentarioRepository;
use App\Repositories\EstatisticaRepository;
use App\Repositories\PostRepository;

final class PostService
{
    public function __construct(
        private PostRepository $posts,
        private ComentarioRepository $comentarios,
        private EstatisticaRepository $estatisticas,
    ) {
    }

    public function getViewModel(string $slug, array $state = []): ?array
    {
        $row = $this->posts->findPublicBySlug($slug);
        if (!is_array($row)) {
            $redirect = $this->posts->findPublicByHistoricalSlug($slug);
            if (is_array($redirect) && trim((string) ($redirect['slug'] ?? '')) !== '') {
                return [
                    'redirect' => true,
                    'redirect_slug' => (string) $redirect['slug'],
                ];
            }

            return null;
        }

        $postId = (int) ($row['id'] ?? 0);
        if ($postId > 0) {
            $this->posts->incrementViews($postId);
            $this->estatisticas->incrementViewsOnDate(date('Y-m-d'));
            $row['views'] = (int) ($row['views'] ?? 0) + 1;
        }

        $post = $this->normalizePost($row);
        $approvedComments = $this->comentarios->listApprovedByPost($postId);
        $comments = $this->normalizeComments($approvedComments);
        $related = $this->loadRelatedPosts($row);
        $previous = $this->normalizeAdjacent($this->posts->previousPublicPost((string) ($row['data_publicacao'] ?? ''), $postId), 'anterior');
        $next = $this->normalizeAdjacent($this->posts->nextPublicPost((string) ($row['data_publicacao'] ?? ''), $postId), 'proximo');

        return [
            'title' => $post['seo_title'] !== '' ? $post['seo_title'] : ($post['titulo'] . ' | ' . (string) portal_config('nome_site', 'Estrategia Nerd')),
            'meta_description' => $post['seo_description'] !== '' ? $post['seo_description'] : ($post['resumo'] !== '' ? $post['resumo'] : (string) portal_config('meta_description_padrao', portal_config('descricao_site', 'Estrategia Nerd'))),
            'site_chrome' => false,
            'post_page' => true,
            'post' => $post,
            'post_comments' => $comments,
            'post_comments_total' => $this->countPublicDiscussionComments($approvedComments),
            'post_related' => $related,
            'post_previous' => $previous,
            'post_next' => $next,
            'comment_state' => [
                'status' => (string) ($state['status'] ?? ''),
                'message' => (string) ($state['message'] ?? ''),
                'old' => is_array($state['old'] ?? null) ? $state['old'] : [],
            ],
            'site_meta' => $this->siteMeta(),
        ];
    }

    public function getUnavailableViewModel(string $slug): array
    {
        $matched = $this->posts->findAnyBySlug($slug);
        $matchedViaHistory = false;

        if (!is_array($matched)) {
            $matched = $this->posts->findAnyByHistoricalSlug($slug);
            $matchedViaHistory = is_array($matched);
        }

        $reason = 'not_found';
        $headline = 'Post nao encontrado';
        $message = 'Talvez esse conteudo tenha mudado de endereco, sido removido ou ainda nao esteja disponivel.';
        $note = 'Voce pode voltar para o blog ou continuar explorando os artigos mais recentes.';

        if (is_array($matched)) {
            $status = trim((string) ($matched['status'] ?? ''));
            $publishedAt = trim((string) ($matched['data_publicacao'] ?? ''));
            $publishedLabel = $publishedAt !== '' ? $this->formatDate($publishedAt) : '';

            if ($status === 'agendado') {
                $reason = 'scheduled';
                $headline = 'Este post ainda nao foi publicado';
                $message = $publishedLabel !== ''
                    ? 'O conteudo existe e esta agendado para ' . $publishedLabel . '.'
                    : 'O conteudo existe, mas ainda esta agendado para publicacao.';
                $note = 'Quando a publicacao for liberada, esse endereco abrira normalmente.';
            } elseif ($status !== 'publicado') {
                $reason = 'unavailable';
                $headline = 'Este post nao esta disponivel no momento';
                $message = 'O conteudo existe, mas ainda nao esta publico.';
                $note = 'Isso pode acontecer quando o post esta em rascunho ou temporariamente fora do ar.';
            }

            if ($matchedViaHistory && $reason !== 'not_found') {
                $note .= ' Esse endereco tambem pode ser um slug antigo desse post.';
            }
        }

        $recent = array_map(
            fn (array $item): array => $this->normalizeRelatedPost($item),
            $this->posts->latestPublicWithCategoria(3)
        );

        return [
            'title' => $headline . ' | ' . (string) portal_config('nome_site', 'Estrategia Nerd'),
            'meta_description' => $message,
            'site_chrome' => false,
            'post_unavailable' => true,
            'reason' => $reason,
            'headline' => $headline,
            'message' => $message,
            'note' => $note,
            'requested_slug' => $slug,
            'matched_post' => is_array($matched) ? [
                'titulo' => (string) ($matched['titulo'] ?? ''),
                'slug' => (string) ($matched['slug'] ?? ''),
                'status' => (string) ($matched['status'] ?? ''),
                'data' => isset($publishedLabel) ? $publishedLabel : '',
            ] : null,
            'recent_posts' => $recent,
            'site_meta' => $this->siteMeta(),
        ];
    }
    public function submitComment(string $slug, array $payload): array
    {
        $row = $this->posts->findPublicBySlug($slug);
        if (!is_array($row)) {
            return [
                'ok' => false,
                'status' => 'error',
                'message' => 'Post nao encontrado.',
                'code' => 404,
            ];
        }

        $nome = trim((string) ($payload['nome'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $comentario = trim((string) ($payload['comentario'] ?? ''));
        $parentId = (int) ($payload['parent_id'] ?? 0);

        if ($nome === '' || mb_strlen($nome) < 2) {
            return [
                'ok' => false,
                'status' => 'error',
                'message' => 'Informe seu nome para comentar.',
                'code' => 422,
                'old' => ['nome' => $nome, 'email' => $email, 'comentario' => $comentario],
            ];
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'ok' => false,
                'status' => 'error',
                'message' => 'Informe um e-mail valido.',
                'code' => 422,
                'old' => ['nome' => $nome, 'email' => $email, 'comentario' => $comentario],
            ];
        }

        if ($comentario === '' || mb_strlen($comentario) < 8) {
            return [
                'ok' => false,
                'status' => 'error',
                'message' => 'Escreva um comentario um pouco mais completo.',
                'code' => 422,
                'old' => ['nome' => $nome, 'email' => $email, 'comentario' => $comentario],
            ];
        }

        if ($parentId > 0) {
            $parent = $this->comentarios->findApprovedById($parentId);
            if (!is_array($parent) || (int) ($parent['post_id'] ?? 0) !== (int) ($row['id'] ?? 0)) {
                return [
                    'ok' => false,
                    'status' => 'error',
                    'message' => 'Nao foi possivel localizar o comentario que voce quer responder.',
                    'code' => 422,
                    'old' => ['nome' => $nome, 'email' => $email, 'comentario' => $comentario],
                ];
            }
        }

        $commentId = $this->comentarios->insertPublic([
            'post_id' => (int) ($row['id'] ?? 0),
            'nome' => $nome,
            'email' => $email,
            'comentario' => $comentario,
            'status' => 'pendente',
            'parent_id' => $parentId,
        ]);
        $created = $this->comentarios->findApprovedById($commentId);
        $normalized = null;
        if (is_array($created)) {
            $normalizedSet = $this->normalizeComments([$created]);
            $normalized = $normalizedSet[0] ?? null;
        }

        $approvedComments = $this->comentarios->listApprovedByPost((int) ($row['id'] ?? 0));

        return [
            'ok' => true,
            'status' => 'success',
            'message' => $parentId > 0
                ? 'Resposta enviada com sucesso. Ela ficara visivel apos aprovacao.'
                : 'Comentario enviado com sucesso. Ele ficara visivel apos aprovacao.',
            'code' => 200,
            'comment' => $normalized,
            'comment_total' => $this->countPublicDiscussionComments($approvedComments),
        ];
    }

    public function likePost(string $slug): array
    {
        $row = $this->posts->findPublicBySlug($slug);
        if (!is_array($row)) {
            return [
                'ok' => false,
                'status' => 'error',
                'message' => 'Post nao encontrado.',
                'code' => 404,
            ];
        }

        $count = $this->posts->incrementLikes((int) ($row['id'] ?? 0));

        return [
            'ok' => true,
            'status' => 'success',
            'message' => 'Curtida registrada.',
            'code' => 200,
            'likes' => $count,
        ];
    }

    private function normalizePost(array $row): array
    {
        $image = trim((string) ($row['imagem_capa'] ?? ''));
        if ($image === '') {
            $image = trim((string) ($row['imagem_thumb'] ?? ''));
        }

        $content = $this->prepareContent((string) ($row['conteudo'] ?? ''));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'titulo' => (string) ($row['titulo'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'resumo' => trim((string) ($row['resumo'] ?? '')),
            'conteudo_html' => $content['html'],
            'toc' => $content['toc'],
            'categoria_id' => (int) ($row['categoria_id'] ?? 0),
            'categoria_nome' => (string) ($row['categoria_nome'] ?? 'Sem categoria'),
            'categoria_slug' => (string) ($row['categoria_slug'] ?? ''),
            'categoria_cor' => (string) ($row['categoria_cor'] ?? '#00d4ff'),
            'imagem' => $this->toPublicUrl($image),
            'tempo_leitura' => (int) ($row['tempo_leitura'] ?? 5),
            'views' => (int) ($row['views'] ?? 0),
            'curtidas' => (int) ($row['curtidas'] ?? 0),
            'comentarios_count' => (int) ($row['comentarios_count'] ?? 0),
            'seo_title' => trim((string) ($row['seo_title'] ?? '')),
            'seo_description' => trim((string) ($row['seo_description'] ?? '')),
            'tags' => $this->normalizeTags((string) ($row['tags'] ?? '')),
            'data' => $this->formatDate((string) ($row['data_publicacao'] ?? '')),
            'data_iso' => (string) ($row['data_publicacao'] ?? ''),
            'url' => url('/post/' . (string) ($row['slug'] ?? '')),
        ];
    }

    private function prepareContent(string $html): array
    {
        $toc = [];
        $usedIds = [];

        $html = preg_replace_callback(
            '/<h2([^>]*)>(.*?)<\/h2>/is',
            function (array $matches) use (&$toc, &$usedIds): string {
                $attrs = (string) ($matches[1] ?? '');
                $inner = (string) ($matches[2] ?? '');
                $text = trim(strip_tags($inner));
                if ($text === '') {
                    return $matches[0];
                }

                $id = '';
                if (preg_match('/\sid=("|\')([^"\']+)\\1/i', $attrs, $idMatch)) {
                    $id = (string) ($idMatch[2] ?? '');
                }
                if ($id === '') {
                    $id = $this->uniqueSlug($this->slugify($text), $usedIds);
                    $attrs .= ' id="' . htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
                } else {
                    $usedIds[] = $id;
                }

                $toc[] = ['id' => $id, 'label' => $text];
                return '<h2' . $attrs . '>' . $inner . '</h2>';
            },
            $html
        ) ?? $html;

        $html = preg_replace('/<iframe\b/i', '<div class="article-embed"><iframe', $html) ?? $html;
        $html = preg_replace('/<\/iframe>/i', '</iframe></div>', $html) ?? $html;
        $html = preg_replace('/<table\b/i', '<div class="article-table-wrap"><table', $html) ?? $html;
        $html = preg_replace('/<\/table>/i', '</table></div>', $html) ?? $html;
        $html = preg_replace('/<figure\b/i', '<figure class="article-figure"', $html) ?? $html;

        $html = $this->normalizeAssetPaths($html);
        $html = $this->removeMissingLocalImages($html);

        return [
            'html' => $html,
            'toc' => $toc,
        ];
    }

    private function normalizeComments(array $items): array
    {
        $indexed = [];
        $roots = [];

        foreach ($items as $item) {
            $storedName = trim((string) ($item['nome'] ?? ''));
            $adminUserId = (int) ($item['admin_user_id'] ?? 0);
            $adminDisplayName = trim((string) ($item['admin_usuario'] ?? ''));
            if ($adminDisplayName === '') {
                $adminDisplayName = trim((string) ($item['admin_nome'] ?? ''));
            }

            $isAdmin = $adminUserId > 0 || $this->isAdminComment((string) ($item['email'] ?? ''), (string) ($item['nome'] ?? ''));
            $displayName = $isAdmin && $adminDisplayName !== ''
                ? $adminDisplayName
                : ($storedName !== '' ? $storedName : 'Leitor');

            $comment = [
                'id' => (int) ($item['id'] ?? 0),
                'nome' => $displayName,
                'email' => (string) ($item['email'] ?? ''),
                'parent_id' => (int) ($item['parent_id'] ?? 0),
                'is_admin' => $isAdmin,
                'admin_user_id' => $adminUserId,
                'admin_avatar_type' => trim((string) ($item['admin_avatar_tipo'] ?? '')),
                'admin_avatar_icon' => trim((string) ($item['admin_avatar_icone'] ?? 'fa-solid fa-user')),
                'admin_avatar_color' => trim((string) ($item['admin_avatar_cor'] ?? '#38bdf8')),
                'admin_avatar_image' => $this->toPublicUrl((string) ($item['admin_avatar_imagem'] ?? '')),
                'admin_avatar_focal_x' => max(0.0, min(100.0, (float) ($item['admin_avatar_focal_x'] ?? 50.0))),
                'admin_avatar_focal_y' => max(0.0, min(100.0, (float) ($item['admin_avatar_focal_y'] ?? 50.0))),
                'comentario' => nl2br(htmlspecialchars((string) ($item['comentario'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
                'data' => $this->formatCommentDate((string) ($item['data'] ?? '')),
                'children' => [],
            ];
            $indexed[$comment['id']] = $comment;
        }

        foreach ($indexed as $id => $comment) {
            $parentId = (int) ($comment['parent_id'] ?? 0);
            if ($parentId > 0 && isset($indexed[$parentId])) {
                $indexed[$parentId]['children'][] = &$indexed[$id];
                continue;
            }

            $roots[] = &$indexed[$id];
        }

        return array_values($roots);
    }

    private function loadRelatedPosts(array $row): array
    {
        $excludeId = (int) ($row['id'] ?? 0);
        $categoriaId = (int) ($row['categoria_id'] ?? 0);
        $items = $categoriaId > 0
            ? $this->posts->relatedPublicByCategoria($categoriaId, $excludeId, 3)
            : [];

        if (count($items) < 3) {
            $fill = $this->posts->latestPublicExcluding(array_merge([$excludeId], array_map(static fn (array $item): int => (int) ($item['id'] ?? 0), $items)), 3 - count($items));
            $items = array_merge($items, $fill);
        }

        return array_map(fn (array $item): array => $this->normalizeRelatedPost($item), $items);
    }

    private function normalizeRelatedPost(array $row): array
    {
        $image = trim((string) ($row['imagem_capa'] ?? ''));
        if ($image === '') {
            $image = trim((string) ($row['imagem_thumb'] ?? ''));
        }

        return [
            'titulo' => (string) ($row['titulo'] ?? ''),
            'resumo' => trim((string) ($row['resumo'] ?? '')),
            'categoria_nome' => (string) ($row['categoria_nome'] ?? 'Blog'),
            'imagem' => $this->toPublicUrl($image),
            'url' => url('/post/' . (string) ($row['slug'] ?? '')),
        ];
    }

    private function normalizeAdjacent(?array $row, string $direction): ?array
    {
        if (!is_array($row)) {
            return null;
        }

        return [
            'titulo' => (string) ($row['titulo'] ?? ''),
            'url' => url('/post/' . (string) ($row['slug'] ?? '')),
            'direction' => $direction,
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

    private function normalizeTags(string $value): array
    {
        $parts = array_filter(array_map(static fn (string $tag): string => trim($tag), explode(',', $value)));
        return array_values($parts);
    }

    private function formatDate(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }

        return date('d/m/Y', $timestamp);
    }

    private function formatCommentDate(string $value): string
    {
        try {
            $commentDate = new \DateTimeImmutable($value);
            $now = new \DateTimeImmutable('now');
        } catch (\Throwable) {
            return '';
        }

        if ($commentDate > $now) {
            return 'agora';
        }

        $diff = $now->diff($commentDate);
        if ((int) $diff->y > 0) {
            return (int) $diff->y === 1 ? '1 ano' : $diff->y . ' anos';
        }

        if ((int) $diff->m > 0) {
            return (int) $diff->m === 1 ? '1 mes' : $diff->m . ' meses';
        }

        if ((int) $diff->d > 0) {
            return (int) $diff->d === 1 ? '1 dia' : $diff->d . ' dias';
        }

        if ((int) $diff->h > 0) {
            return (int) $diff->h === 1 ? '1 hora' : $diff->h . ' horas';
        }

        if ((int) $diff->i > 0) {
            return (int) $diff->i === 1 ? '1 minuto' : $diff->i . ' minutos';
        }

        return 'agora';
    }

    private function normalizeAssetPaths(string $html): string
    {
        return preg_replace_callback(
            '/\b(src|href)=("|\')([^"\']+)\\2/i',
            static function (array $matches): string {
                $attr = (string) ($matches[1] ?? '');
                $quote = (string) ($matches[2] ?? '"');
                $value = trim((string) ($matches[3] ?? ''));
                if ($value === '' || preg_match('~^(https?:)?//|^data:|^#|^mailto:|^tel:~i', $value)) {
                    return $matches[0];
                }

                return $attr . '=' . $quote . htmlspecialchars(url('/' . ltrim($value, '/')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . $quote;
            },
            $html
        ) ?? $html;
    }

    private function removeMissingLocalImages(string $html): string
    {
        if (trim($html) === '' || !class_exists(\DOMDocument::class)) {
            return $html;
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="post-content-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        if ($loaded !== true) {
            return $html;
        }

        $images = iterator_to_array($document->getElementsByTagName('img'));
        foreach ($images as $image) {
            if (!$image instanceof \DOMElement) {
                continue;
            }

            $src = trim((string) $image->getAttribute('src'));
            if ($src === '' || $this->localImageExists($src)) {
                continue;
            }

            $parent = $image->parentNode;
            if ($parent instanceof \DOMElement && strtolower($parent->tagName) === 'figure') {
                $parent->parentNode?->removeChild($parent);
                continue;
            }

            $parent?->removeChild($image);
        }

        $root = $document->getElementById('post-content-root');
        if (!$root instanceof \DOMElement) {
            return $html;
        }

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return $output !== '' ? $output : $html;
    }

    private function localImageExists(string $src): bool
    {
        if (preg_match('~^data:~i', $src)) {
            return true;
        }

        $path = parse_url($src, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return true;
        }

        $base = parse_url(app_url(), PHP_URL_PATH);
        if (is_string($base) && $base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        $path = ltrim($path, '/');
        if ($path === '' || str_starts_with($path, 'http')) {
            return true;
        }

        $fullPath = base_path('public/' . $path);
        return is_file($fullPath);
    }

    private function toPublicUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return preg_match('~^https?://~i', $value) ? $value : url('/' . ltrim($value, '/'));
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $map = [
            'Ã¡' => 'a', 'Ã ' => 'a', 'Ã£' => 'a', 'Ã¢' => 'a', 'Ã¤' => 'a',
            'Ã©' => 'e', 'Ã¨' => 'e', 'Ãª' => 'e', 'Ã«' => 'e',
            'Ã­' => 'i', 'Ã¬' => 'i', 'Ã®' => 'i', 'Ã¯' => 'i',
            'Ã³' => 'o', 'Ã²' => 'o', 'Ãµ' => 'o', 'Ã´' => 'o', 'Ã¶' => 'o',
            'Ãº' => 'u', 'Ã¹' => 'u', 'Ã»' => 'u', 'Ã¼' => 'u',
            'Ã§' => 'c',
        ];
        $value = strtr($value, $map);
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? $value;
        $value = trim($value, '-');

        return $value !== '' ? $value : 'secao';
    }

    private function uniqueSlug(string $slug, array &$usedIds): string
    {
        $base = $slug;
        $suffix = 2;
        while (in_array($slug, $usedIds, true)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }
        $usedIds[] = $slug;

        return $slug;
    }

    private function isAdminComment(string $email, string $name): bool
    {
        $email = strtolower(trim($email));
        $name = strtolower(trim($name));

        return str_ends_with($email, '@admin.estrategia-nerd.local')
            || $name === 'admin'
            || $name === 'equipe estrategia nerd';
    }

    private function countPublicDiscussionComments(array $items): int
    {
        $total = 0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $adminUserId = (int) ($item['admin_user_id'] ?? 0);
            if ($adminUserId > 0 || $this->isAdminComment((string) ($item['email'] ?? ''), (string) ($item['nome'] ?? ''))) {
                continue;
            }

            $total++;
        }

        return $total;
    }
}
