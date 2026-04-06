<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\ComentarioRepository;

final class ComentariosService
{
    public function __construct(private ComentarioRepository $comentarios)
    {
    }

    public function getIndexViewModel(array $query = []): array
    {
        $filters = $this->normalizeFilters($query);
        $page = $this->clampInt((int) ($query['page'] ?? 1), 1, 9999);
        $perPage = $this->clampInt((int) ($query['per_page'] ?? 10), 5, 50);
        [$sort, $dir] = $this->normalizeSortDir((string) ($query['sort'] ?? 'data'), (string) ($query['dir'] ?? 'desc'));

        return [
            'title' => 'Comentarios',
            'filters' => $filters,
            'sort' => $sort,
            'dir' => $dir,
            'summary' => $this->comentarios->summaryFiltered($filters),
            'pagination' => $this->comentarios->paginateAdmin($filters, $page, $perPage, $sort, $dir),
            'posts' => $this->comentarios->listPostsForFilter(),
        ];
    }

    public function getReplyViewModel(int $id, string $returnTo, array $old = [], array $errors = []): ?array
    {
        $comment = $this->comentarios->findAdminById($id);
        if ($comment === null) {
            return null;
        }

        $form = [
            'id' => $id,
            'resposta' => trim((string) ($old['resposta'] ?? '')),
        ];

        return [
            'title' => 'Responder Comentario',
            'comment' => $comment,
            'reply_target' => $this->resolveReplyTarget($comment),
            'form' => $form,
            'errors' => $errors,
            'return_to' => $returnTo,
        ];
    }

    public function replyToComment(int $id, array $input, ?array $adminUser): array
    {
        $comment = $this->comentarios->findAdminById($id);
        if ($comment === null) {
            return ['ok' => false, 'not_found' => true];
        }

        $replyBody = trim((string) ($input['resposta'] ?? ''));
        $returnTo = (string) ($input['return_to'] ?? url('/admin/comentarios'));
        $errors = [];

        if ($replyBody === '') {
            $errors['resposta'] = 'Escreva uma resposta para o comentario.';
        } elseif (mb_strlen($replyBody) > 5000) {
            $errors['resposta'] = 'A resposta deve ter no maximo 5000 caracteres.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'viewModel' => $this->getReplyViewModel($id, $returnTo, ['resposta' => $replyBody], $errors),
            ];
        }

        $usuario = trim((string) ($adminUser['usuario'] ?? 'Equipe Estrategia Nerd'));
        if ($usuario === '') {
            $usuario = 'Equipe Estrategia Nerd';
        }
        $email = strtolower(preg_replace('/[^a-z0-9]+/i', '.', $usuario) ?? 'equipe');
        $email = trim($email, '.');
        if ($email === '') {
            $email = 'equipe';
        }
        $email .= '@admin.estrategia-nerd.local';

        $this->comentarios->insertReply([
            'post_id' => (int) ($comment['post_id'] ?? 0),
            'parent_id' => (int) ($comment['id'] ?? 0),
            'nome' => $usuario,
            'email' => $email,
            'comentario' => $replyBody,
            'status' => 'aprovado',
        ]);

        return ['ok' => true];
    }

    private function resolveReplyTarget(array $comment): array
    {
        $target = $comment;
        $parentId = (int) ($comment['parent_id'] ?? 0);
        if ($parentId > 0) {
            $parent = $this->comentarios->findAdminById($parentId);
            if (is_array($parent)) {
                $target = $parent;
            }
        }

        return [
            'id' => (int) ($comment['id'] ?? 0),
            'parent_id' => $parentId,
            'nome' => (string) ($comment['nome'] ?? 'Anonimo'),
            'comentario' => (string) ($comment['comentario'] ?? ''),
            'thread_root_id' => (int) ($target['id'] ?? 0),
            'thread_root_nome' => (string) ($target['nome'] ?? 'Anonimo'),
            'thread_root_comentario' => (string) ($target['comentario'] ?? ''),
        ];
    }

    public function moderateComment(int $id, string $action): array
    {
        $comment = $this->comentarios->findAdminById($id);
        if ($comment === null) {
            return ['ok' => false, 'not_found' => true];
        }

        $action = strtolower(trim($action));
        switch ($action) {
            case 'approve':
                $this->comentarios->updateStatus($id, 'aprovado');
                return ['ok' => true, 'mode' => 'approved'];
            case 'reject':
                $this->comentarios->updateStatus($id, 'reprovado');
                return ['ok' => true, 'mode' => 'rejected'];
            case 'spam':
                $this->comentarios->updateStatus($id, 'spam');
                return ['ok' => true, 'mode' => 'spam'];
            case 'pending':
                $this->comentarios->updateStatus($id, 'pendente');
                return ['ok' => true, 'mode' => 'pending'];
            default:
                return ['ok' => true, 'mode' => 'updated'];
        }
    }

    public function getDeleteViewModel(int $id, string $returnTo): ?array
    {
        $comment = $this->comentarios->findAdminById($id);
        if ($comment === null) {
            return null;
        }

        return [
            'title' => 'Excluir Comentario',
            'comment' => $comment,
            'return_to' => $returnTo,
        ];
    }

    public function deleteComment(int $id): array
    {
        $comment = $this->comentarios->findAdminById($id);
        if ($comment === null) {
            return ['ok' => false, 'not_found' => true];
        }

        $this->comentarios->deleteThreadById($id);
        return ['ok' => true];
    }

    private function normalizeFilters(array $query): array
    {
        return [
            'busca' => trim((string) ($query['busca'] ?? '')),
            'status' => trim((string) ($query['status'] ?? '')),
            'respondido' => trim((string) ($query['respondido'] ?? '')),
            'post' => (int) ($query['post'] ?? 0),
        ];
    }

    private function normalizeSortDir(string $sort, string $dir): array
    {
        $allowedSort = ['id', 'autor', 'email', 'post', 'status', 'respondido', 'data'];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'data';
        }

        return [$sort, strtolower(trim($dir)) === 'asc' ? 'asc' : 'desc'];
    }

    private function clampInt(int $value, int $min, int $max): int
    {
        if ($value < $min) {
            return $min;
        }

        if ($value > $max) {
            return $max;
        }

        return $value;
    }
}
