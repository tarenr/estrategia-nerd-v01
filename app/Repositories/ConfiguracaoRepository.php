<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ConfiguracaoRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT chave, valor FROM configuracoes ORDER BY chave ASC');
        $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $data = [];
        foreach ($rows as $row) {
            $key = (string) ($row['chave'] ?? '');
            if ($key === '') {
                continue;
            }

            $data[$key] = (string) ($row['valor'] ?? '');
        }

        return $data;
    }

    /**
     * @param array<string, string> $values
     */
    public function saveMany(array $values): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO configuracoes (chave, valor, updated_at)
             VALUES (:chave, :valor, NOW())
             ON DUPLICATE KEY UPDATE valor = VALUES(valor), updated_at = NOW()'
        );

        foreach ($values as $key => $value) {
            $stmt->execute([
                'chave' => $key,
                'valor' => $value,
            ]);
        }
    }
}
