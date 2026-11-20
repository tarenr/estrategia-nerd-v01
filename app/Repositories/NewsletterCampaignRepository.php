<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class NewsletterCampaignRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array{
     *     assunto: string,
     *     preheader?: ?string,
     *     template_html: string,
     *     conteudo_texto?: ?string,
     *     status?: string,
     *     total_destinatarios?: int,
     *     agendada_para?: ?string
     * } $data
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO newsletter_campaigns (assunto, preheader, template_html, conteudo_texto, status, total_destinatarios, agendada_para, criado_em)
             VALUES (:assunto, :preheader, :template_html, :conteudo_texto, :status, :total_destinatarios, :agendada_para, NOW())'
        );

        $stmt->execute([
            ':assunto' => (string) $data['assunto'],
            ':preheader' => $data['preheader'] ?? null,
            ':template_html' => (string) $data['template_html'],
            ':conteudo_texto' => $data['conteudo_texto'] ?? null,
            ':status' => (string) ($data['status'] ?? 'rascunho'),
            ':total_destinatarios' => (int) ($data['total_destinatarios'] ?? 0),
            ':agendada_para' => $data['agendada_para'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param int $id
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $allowed = [
            'assunto', 'preheader', 'template_html', 'conteudo_texto',
            'status', 'total_destinatarios', 'total_enviados', 'total_falhas',
            'agendada_para', 'enviada_em'
        ];

        $fields = [];
        $params = [':id' => $id];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "`{$field}` = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE newsletter_campaigns SET ' . implode(', ', $fields) . ', atualizado_em = NOW() WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM newsletter_campaigns WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array{status?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function all(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $sql = 'SELECT * FROM newsletter_campaigns';
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= ' WHERE status = :status';
            $params[':status'] = $filters['status'];
        }

        $sql .= ' ORDER BY id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    /**
     * @param array{status?: string} $filters
     */
    public function count(array $filters = []): int
    {
        $sql = 'SELECT COUNT(*) AS total FROM newsletter_campaigns';
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= ' WHERE status = :status';
            $params[':status'] = $filters['status'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($res['total'] ?? 0);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM newsletter_campaigns WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Associa artigos do blog à campanha na ordem informada.
     *
     * @param int $campaignId
     * @param list<int> $postIds
     */
    public function syncPosts(int $campaignId, array $postIds): void
    {
        $this->pdo->beginTransaction();

        try {
            $stmtDel = $this->pdo->prepare('DELETE FROM newsletter_campaign_posts WHERE campaign_id = :campaign_id');
            $stmtDel->execute([':campaign_id' => $campaignId]);

            if (!empty($postIds)) {
                $stmtIns = $this->pdo->prepare(
                    'INSERT INTO newsletter_campaign_posts (campaign_id, post_id, ordem) VALUES (:campaign_id, :post_id, :ordem)'
                );

                $ordem = 0;
                foreach ($postIds as $postId) {
                    $stmtIns->execute([
                        ':campaign_id' => $campaignId,
                        ':post_id' => (int) $postId,
                        ':ordem' => $ordem++,
                    ]);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw new RuntimeException('Falha ao sincronizar posts da campanha: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Retorna os posts vinculados à campanha com título, slug e capa.
     *
     * @return list<array<string, mixed>>
     */
    public function getCampaignPosts(int $campaignId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT cp.ordem, p.id, p.titulo, p.slug, p.imagem_capa, p.data_publicacao
             FROM newsletter_campaign_posts cp
             INNER JOIN posts p ON p.id = cp.post_id
             WHERE cp.campaign_id = :campaign_id
             ORDER BY cp.ordem ASC'
        );
        $stmt->execute([':campaign_id' => $campaignId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    /**
     * Registra o log individual de disparo para um assinante.
     */
    public function logSend(int $campaignId, ?int $subscriberId, string $email, string $status, ?string $erro = null): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO newsletter_campaign_logs (campaign_id, subscriber_id, email, status, erro, enviado_em)
             VALUES (:campaign_id, :subscriber_id, :email, :status, :erro, NOW())'
        );

        $stmt->execute([
            ':campaign_id' => $campaignId,
            ':subscriber_id' => $subscriberId,
            ':email' => $email,
            ':status' => $status,
            ':erro' => $erro,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getLogs(int $campaignId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM newsletter_campaign_logs WHERE campaign_id = :campaign_id ORDER BY id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
        $stmt->execute([':campaign_id' => $campaignId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }
}
