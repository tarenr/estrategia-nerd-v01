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
            'summary' => $this->decorateSummary($this->comentarios->summaryFiltered($filters)),
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
            'thread_replies' => $this->decorateThreadReplies($this->comentarios->listDirectRepliesByParent($id)),
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
        $displayName = trim((string) ($adminUser['usuario'] ?? $adminUser['nome'] ?? $usuario));
        if ($displayName === '') {
            $displayName = $usuario;
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
            'nome' => $displayName,
            'email' => $email,
            'comentario' => $replyBody,
            'status' => 'aprovado',
            'admin_user_id' => (int) ($adminUser['id'] ?? 0),
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
    private function decorateThreadReplies(array $items): array
    {
        return array_map(static function (array $item): array {
            $email = trim((string) ($item['email'] ?? ''));
            $adminUserId = (int) ($item['admin_user_id'] ?? 0);
            $isAdmin = $adminUserId > 0 || ($email !== '' && str_ends_with(strtolower($email), '@admin.estrategia-nerd.local'));

            return [
                'id' => (int) ($item['id'] ?? 0),
                'nome' => trim((string) ($item['nome'] ?? 'Anonimo')) ?: 'Anonimo',
                'email' => $isAdmin ? '' : $email,
                'comentario' => (string) ($item['comentario'] ?? ''),
                'status' => (string) ($item['status'] ?? 'pendente'),
                'data' => (string) ($item['data'] ?? ''),
                'is_admin' => $isAdmin,
            ];
        }, $items);
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

    private function decorateSummary(array $summary): array
    {
        $total = (int) ($summary['total'] ?? 0);
        $pendentes = (int) ($summary['pendentes'] ?? 0);
        $aprovados = (int) ($summary['aprovados'] ?? 0);
        $reprovados = (int) ($summary['reprovados'] ?? 0);
        $spam = (int) ($summary['spam'] ?? 0);
        $comentariosRaiz = (int) ($summary['comentarios_raiz'] ?? 0);
        $respostas = (int) ($summary['respostas'] ?? 0);
        $threadsRespondidas = (int) ($summary['threads_respondidas'] ?? ($summary['respondidos'] ?? 0));
        $moderados = $aprovados + $reprovados + $spam;
        $bloqueados = $reprovados + $spam;
        $semResposta = max(0, $comentariosRaiz - $threadsRespondidas);

        $summary['moderados'] = $moderados;
        $summary['bloqueados'] = $bloqueados;
        $summary['comentarios_raiz'] = $comentariosRaiz;
        $summary['respostas'] = $respostas;
        $summary['threads_respondidas'] = $threadsRespondidas;
        $summary['respondidos'] = $threadsRespondidas;
        $summary['sem_resposta'] = $semResposta;
        $summary['fila_percentual'] = $total > 0 ? ($pendentes / $total) * 100 : 0.0;
        $summary['taxa_aprovacao'] = $moderados > 0 ? ($aprovados / $moderados) * 100 : 0.0;
        $summary['pressao_defensiva'] = $total > 0 ? ($bloqueados / $total) * 100 : 0.0;
        $summary['cobertura_resposta'] = $comentariosRaiz > 0 ? ($threadsRespondidas / $comentariosRaiz) * 100 : 0.0;

        return $summary;
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