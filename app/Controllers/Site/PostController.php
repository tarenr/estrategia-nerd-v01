<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Repositories\ComentarioRepository;
use App\Repositories\EstatisticaRepository;
use App\Repositories\PostRepository;
use App\Services\Site\PostService;
use App\Support\Csrf;
use App\Support\View;

final class PostController
{
    public function show(string $slug): void
    {
        if (!$this->blogPublicActive()) {
            http_response_code(404);
            echo 'Blog indisponivel no momento.';
            return;
        }

        $state = [
            'status' => (string) ($_GET['comment_status'] ?? ''),
            'message' => (string) ($_GET['comment_message'] ?? ''),
        ];

        $viewModel = $this->service()->getViewModel($slug, $state);
        if (!is_array($viewModel)) {
            http_response_code(404);
            View::render('site/post-unavailable', $this->service()->getUnavailableViewModel($slug));
            return;
        }

        if (($viewModel['redirect'] ?? false) === true) {
            $targetSlug = trim((string) ($viewModel['redirect_slug'] ?? ''));
            if ($targetSlug !== '') {
                header('Location: ' . url('/post/' . rawurlencode($targetSlug)), true, 301);
                exit;
            }
        }

        View::render('site/post', $viewModel);
    }

    public function comment(string $slug): void
    {
        if (!$this->blogPublicActive()) {
            if ($this->isAjaxRequest()) {
                $this->json(['ok' => false, 'message' => 'Blog indisponivel no momento.'], 404);
                return;
            }

            http_response_code(404);
            echo 'Blog indisponivel no momento.';
            return;
        }

        $isAjax = $this->isAjaxRequest();
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            if ($isAjax) {
                $this->json(['ok' => false, 'message' => 'Sessao expirada. Atualize a pagina e tente novamente.'], 419);
                return;
            }
            $this->redirectWithMessage($slug, 'error', 'Sessao expirada. Atualize a pagina e tente novamente.');
            return;
        }

        $result = $this->service()->submitComment($slug, $_POST);
        $message = (string) ($result['message'] ?? 'Nao foi possivel enviar o comentario.');
        $status = (string) ($result['status'] ?? 'error');

        if (($result['ok'] ?? false) !== true) {
            if ($isAjax) {
                $this->json($result, (int) ($result['code'] ?? 422));
                return;
            }
            $this->redirectWithMessage($slug, $status, $message);
            return;
        }

        if ($isAjax) {
            if (($result['ok'] ?? false) === true && is_array($result['comment'] ?? null)) {
                $result['html'] = $this->renderCommentHtml(
                    (array) $result['comment'],
                    $slug,
                    (int) (($result['comment']['parent_id'] ?? 0) > 0 ? 1 : 0),
                    false
                );
            }
            $this->json($result, 200);
            return;
        }
        $this->redirectWithMessage($slug, 'success', $message);
    }

    public function like(string $slug): void
    {
        if (!$this->blogPublicActive()) {
            $this->json(['ok' => false, 'message' => 'Blog indisponivel no momento.'], 404);
            return;
        }

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            $this->json(['ok' => false, 'message' => 'Sessao expirada. Atualize a pagina e tente novamente.'], 419);
            return;
        }

        $result = $this->service()->likePost($slug);
        $this->json($result, (int) ($result['code'] ?? 200));
    }

    private function redirectWithMessage(string $slug, string $status, string $message): void
    {
        $query = http_build_query([
            'comment_status' => $status,
            'comment_message' => $message,
        ]);
        header('Location: ' . url('/post/' . rawurlencode($slug) . ($query !== '' ? '?' . $query : '') . '#comentarios'));
        exit;
    }

    private function isAjaxRequest(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    private function json(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    private function renderCommentHtml(array $comment, string $slug, int $level = 0, bool $isHidden = false): string
    {
        ob_start();
        View::component('site/post/comment-item', [
            'comment' => $comment,
            'level' => $level,
            'post_slug' => $slug,
            'is_hidden' => $isHidden,
        ]);

        return trim((string) ob_get_clean());
    }

    private function blogPublicActive(): bool
    {
        return site_section_public_active('blog');
    }

    private function service(): PostService
    {
        /** @var \PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        return new PostService(
            new PostRepository($pdo),
            new ComentarioRepository($pdo),
            new EstatisticaRepository($pdo),
        );
    }
}
