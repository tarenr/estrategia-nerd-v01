<?php

/**
 * -----------------------------------------------------------------------------
 * @file        scripts/migrate.php
 * @project     Estrategia Nerd
 * @purpose     Runner de migrations SQL idempotente e sequencial
 * @usage       C:\xampp\php\php.exe scripts/migrate.php [--status] [--target=local|stage|production]
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Acesso negado: execute via CLI.\n";
    exit(1);
}

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Support\TargetEnvironmentDatabase;

// 1. Processar argumentos CLI
$options = getopt('', ['status', 'target::', 'help']);
if (isset($options['help'])) {
    echo <<<HELP
Uso: php scripts/migrate.php [opcoes]

Opcoes:
  --status              Exibe o status de cada migration sem aplicar pendentes.
  --target=<ambiente>   Ambiente alvo: local (padrao), stage ou production.
  --help                Exibe esta ajuda.

HELP;
    exit(0);
}

$isStatusOnly = isset($options['status']);
$targetEnv = is_string($options['target'] ?? null) ? trim((string) $options['target']) : 'local';
if (!in_array($targetEnv, ['local', 'stage', 'production'], true)) {
    echo "[ERRO] Ambiente alvo invalido: '{$targetEnv}'. Use local, stage ou production.\n";
    exit(1);
}

// 2. Conectar ao banco correto
try {
    if ($targetEnv === 'local') {
        /** @var \PDO $pdo */
        $pdo = $GLOBALS['pdo'];
    } else {
        $pdo = TargetEnvironmentDatabase::pdo($targetEnv);
    }
} catch (\Throwable $e) {
    echo "[ERRO] Falha de conexao com o banco ({$targetEnv}): " . $e->getMessage() . "\n";
    exit(1);
}

echo "=======================================================\n";
echo "   ESTRATÉGIA NERD — RUNNER DE MIGRATIONS ({$targetEnv})\n";
echo "=======================================================\n\n";

// 3. Verificação/criação idempotente da tabela `migrations`
try {
    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `migration` VARCHAR(255) NOT NULL UNIQUE,
  `batch` INT UNSIGNED NOT NULL,
  `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
} catch (\Throwable $e) {
    echo "[ERRO] Falha ao verificar/criar tabela 'migrations': " . $e->getMessage() . "\n";
    exit(1);
}

// 4. Obter migrations ja executadas
try {
    $stmt = $pdo->query('SELECT migration, batch, executed_at FROM migrations ORDER BY id ASC');
    $executedRows = $stmt !== false ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
} catch (\Throwable $e) {
    echo "[ERRO] Falha ao consultar tabela 'migrations': " . $e->getMessage() . "\n";
    exit(1);
}

/** @var array<string, array{batch: int, executed_at: string}> $executed */
$executed = [];
foreach ($executedRows as $row) {
    $migrationName = (string) ($row['migration'] ?? '');
    if ($migrationName !== '') {
        $executed[$migrationName] = [
            'batch' => (int) ($row['batch'] ?? 1),
            'executed_at' => (string) ($row['executed_at'] ?? ''),
        ];
    }
}

// 5. Escanear arquivos SQL em database/migrations/
$migrationsDir = dirname(__DIR__) . '/database/migrations';
if (!is_dir($migrationsDir)) {
    mkdir($migrationsDir, 0755, true);
}

$files = glob($migrationsDir . '/*.sql');
$files = is_array($files) ? $files : [];
sort($files, SORT_STRING);

if ($files === []) {
    echo "Nenhuma migration encontrada em database/migrations/.\n";
    exit(0);
}

// 6. Modo --status
if ($isStatusOnly) {
    echo "Status das migrations:\n";
    foreach ($files as $filePath) {
        $filename = basename($filePath);
        if (isset($executed[$filename])) {
            $info = $executed[$filename];
            echo "  [APLICADA]  {$filename} (Batch {$info['batch']} em {$info['executed_at']})\n";
        } else {
            echo "  [PENDENTE]  {$filename}\n";
        }
    }
    echo "\n";
    exit(0);
}

// 7. Identificar pendentes
$pendingFiles = [];
foreach ($files as $filePath) {
    $filename = basename($filePath);
    if (!isset($executed[$filename])) {
        $pendingFiles[] = $filePath;
    }
}

if ($pendingFiles === []) {
    echo "Nenhuma migration pendente. O banco de dados esta atualizado!\n";
    exit(0);
}

// 8. Determinar proximo lote (batch)
$currentMaxBatch = 0;
foreach ($executed as $info) {
    if ($info['batch'] > $currentMaxBatch) {
        $currentMaxBatch = $info['batch'];
    }
}
$nextBatch = $currentMaxBatch + 1;

echo "Executando " . count($pendingFiles) . " migration(s) pendente(s) no lote {$nextBatch}...\n\n";

/**
 * Divide o conteudo SQL em comandos individuais executaveis,
 * ignorando comentarios e preservando literais de strings.
 *
 * @return list<string>
 */
function split_sql_statements(string $sql): array
{
    $statements = [];
    $current = '';
    $inSingleQuote = false;
    $inDoubleQuote = false;
    $inBacktick = false;
    $length = strlen($sql);
    $i = 0;

    while ($i < $length) {
        $char = $sql[$i];
        $nextChar = $i + 1 < $length ? $sql[$i + 1] : '';

        // Ignorar comentarios em linha (-- ou #)
        if (!$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
            if (($char === '-' && $nextChar === '-') || $char === '#') {
                $endOfLine = strpos($sql, "\n", $i);
                if ($endOfLine === false) {
                    break;
                }
                $i = $endOfLine + 1;
                continue;
            }

            // Ignorar comentarios de bloco (/* ... */)
            if ($char === '/' && $nextChar === '*') {
                $endOfBlock = strpos($sql, '*/', $i + 2);
                if ($endOfBlock === false) {
                    break;
                }
                $i = $endOfBlock + 2;
                continue;
            }
        }

        // Controle de aspas e backticks
        if ($char === "'" && !$inDoubleQuote && !$inBacktick) {
            $isEscaped = ($i > 0 && $sql[$i - 1] === '\\');
            if (!$isEscaped) {
                $inSingleQuote = !$inSingleQuote;
            }
        } elseif ($char === '"' && !$inSingleQuote && !$inBacktick) {
            $isEscaped = ($i > 0 && $sql[$i - 1] === '\\');
            if (!$isEscaped) {
                $inDoubleQuote = !$inDoubleQuote;
            }
        } elseif ($char === '`' && !$inSingleQuote && !$inDoubleQuote) {
            $inBacktick = !$inBacktick;
        }

        // Fim de comando
        if ($char === ';' && !$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
            $trimmed = trim($current);
            if ($trimmed !== '') {
                $statements[] = $trimmed;
            }
            $current = '';
            $i++;
            continue;
        }

        $current .= $char;
        $i++;
    }

    $trimmed = trim($current);
    if ($trimmed !== '') {
        $statements[] = $trimmed;
    }

    return $statements;
}

// 9. Executar cada migration pendente
$successCount = 0;
foreach ($pendingFiles as $filePath) {
    $filename = basename($filePath);
    $rawSql = (string) file_get_contents($filePath);
    if (str_starts_with($rawSql, "\xEF\xBB\xBF")) {
        $rawSql = substr($rawSql, 3);
    }
    $statements = split_sql_statements($rawSql);

    $startTime = microtime(true);
    echo ">> Aplicando: {$filename} (" . count($statements) . " comandos)... ";

    try {
        foreach ($statements as $statement) {
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }

        // Registrar na tabela `migrations`
        $insertStmt = $pdo->prepare('INSERT INTO `migrations` (`migration`, `batch`) VALUES (?, ?)');
        $insertStmt->execute([$filename, $nextBatch]);

        $durationMs = round((microtime(true) - $startTime) * 1000, 1);
        echo "OK ({$durationMs}ms)\n";
        $successCount++;
    } catch (\Throwable $e) {
        echo "FALHA!\n";
        echo "[ERRO] Erro ao executar {$filename}: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\nConcluido com sucesso: {$successCount} migration(s) executada(s) no lote {$nextBatch}.\n";
exit(0);
