<?php

declare(strict_types=1);

namespace Scripts\Operations;

final class OperationLogger
{
    private const CATEGORY_DATA = 'dados';
    private const CATEGORY_TECHNICAL = 'tecnico';
    private const CATEGORY_CONTENT = 'conteudo';

    private const ROOT_DIRECTORIES = [
        '01-local',
        '02-stage',
        '03-prod',
        '04-logs',
    ];

    private const DATA_DIRECTORIES = [
        '01-local' => ['dados', 'tecnico', 'conteudo', 'codigo'],
        '02-stage' => ['dados', 'tecnico', 'conteudo'],
        '03-prod' => ['dados', 'tecnico', 'conteudo'],
    ];

    private const TYPE_CATEGORY_MAP = [
        'backup_dados' => self::CATEGORY_DATA,
        'backup_verificacao' => self::CATEGORY_DATA,
        'backup_registro_nuvem' => self::CATEGORY_DATA,
        'restore_dados' => self::CATEGORY_DATA,
        'backup_tecnico' => self::CATEGORY_TECHNICAL,
        'pacote_tecnico' => self::CATEGORY_TECHNICAL,
        'deploy_tecnico' => self::CATEGORY_TECHNICAL,
        'deploy_tecnico_stage' => self::CATEGORY_TECHNICAL,
        'rollback_tecnico' => self::CATEGORY_TECHNICAL,
        'pacote_conteudo' => self::CATEGORY_CONTENT,
        'verificacao_conteudo' => self::CATEGORY_CONTENT,
        'aplicacao_conteudo' => self::CATEGORY_CONTENT,
        'promocao_conteudo' => self::CATEGORY_CONTENT,
    ];

    public function __construct(private string $rootPath)
    {
        $this->ensureStructure();
    }

    public function ensureStructure(): void
    {
        $directories = [
            $this->rootPath,
        ];

        foreach (self::ROOT_DIRECTORIES as $directoryName) {
            $directories[] = $this->rootPath . DIRECTORY_SEPARATOR . $directoryName;
        }

        foreach (self::DATA_DIRECTORIES as $rootDirectory => $children) {
            foreach ($children as $childDirectory) {
                $directories[] = $this->rootPath . DIRECTORY_SEPARATOR . $rootDirectory . DIRECTORY_SEPARATOR . $childDirectory;
            }
        }

        foreach ($directories as $directory) {
            if (is_dir($directory)) {
                continue;
            }

            if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new \RuntimeException('Nao foi possivel criar a pasta operacional: ' . $directory);
            }
        }
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public function write(
        string $type,
        string $origin,
        string $destination,
        string $id,
        string $status,
        string $message,
        array $context = []
    ): void {
        $line = sprintf(
            '[%s] tipo=%s origem=%s destino=%s id=%s status=%s msg="%s"',
            date('Y-m-d H:i:s'),
            $this->normalizeToken($type),
            $this->normalizeToken($origin),
            $this->normalizeToken($destination),
            $this->normalizeToken($id !== '' ? $id : '-'),
            $this->normalizeToken($status),
            $this->sanitizeMessage($message)
        );

        foreach ($context as $key => $value) {
            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            $line .= ' ' . $this->normalizeToken((string) $key) . '=' . $this->normalizeToken((string) ($value ?? ''));
        }

        $line .= PHP_EOL;

        $logPath = $this->buildCategoryLogPath($this->resolveCategory($type));
        if (file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException('Nao foi possivel gravar o log operacional.');
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentEntries(int $limit = 20, ?string $category = null): array
    {
        $limit = max(1, $limit);
        $entries = [];

        foreach ($this->logFiles($category) as $logFile) {
            $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines) || $lines === []) {
                continue;
            }

            for ($index = count($lines) - 1; $index >= 0; $index--) {
                $entry = $this->parseLine($lines[$index], basename($logFile), $category);
                if ($entry === null) {
                    continue;
                }

                $entries[] = $entry;
                if (count($entries) >= $limit) {
                    return $entries;
                }
            }
        }

        return $entries;
    }

    public function latestLogFile(?string $category = null): ?string
    {
        $files = $this->logFiles($category);
        if ($files === []) {
            return null;
        }

        return $files[0];
    }

    private function normalizeToken(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '-';
        }

        $value = preg_replace('/\s+/', '_', $value) ?? $value;
        $value = str_replace(['"', "'"], '', $value);

        return $value !== '' ? $value : '-';
    }

    private function sanitizeMessage(string $message): string
    {
        $message = trim($message);
        $message = str_replace(["\r", "\n"], ' ', $message);
        $message = str_replace('"', "'", $message);

        return $message !== '' ? $message : '-';
    }

    /**
     * @return array<int, string>
     */
    private function logFiles(?string $category = null): array
    {
        if ($category !== null) {
            $files = array_merge(
                $this->categoryLogFiles($category),
                $this->legacyLogFilesForCategory($category)
            );

            $files = array_values(array_unique($files));
            rsort($files, SORT_STRING);

            return $files;
        }

        $pattern = $this->logsDirectory() . DIRECTORY_SEPARATOR . '*.log';
        $files = glob($pattern);
        if ($files === false || $files === []) {
            return [];
        }

        rsort($files, SORT_STRING);

        return array_values(array_filter($files, 'is_string'));
    }

    /**
     * @return array<int, string>
     */
    private function categoryLogFiles(string $category): array
    {
        $files = glob($this->logsDirectory() . DIRECTORY_SEPARATOR . $category . '-*.log');
        if ($files === false || $files === []) {
            return [];
        }

        return array_values(array_filter($files, 'is_string'));
    }

    /**
     * @return array<int, string>
     */
    private function legacyLogFilesForCategory(string $category): array
    {
        if (!in_array($category, [self::CATEGORY_DATA, self::CATEGORY_TECHNICAL, self::CATEGORY_CONTENT], true)) {
            return [];
        }

        $files = glob($this->logsDirectory() . DIRECTORY_SEPARATOR . '*.log');
        if ($files === false || $files === []) {
            return [];
        }

        return array_values(array_filter($files, static function ($path): bool {
            if (!is_string($path)) {
                return false;
            }

            return preg_match('/^\d{4}-\d{2}\.log$/', basename($path)) === 1;
        }));
    }

    private function logsDirectory(): string
    {
        return $this->rootPath . DIRECTORY_SEPARATOR . '04-logs';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseLine(string $line, string $sourceFile, ?string $requiredCategory = null): ?array
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        if (!preg_match('/^\[(?<timestamp>[^\]]+)\]\s+(?<payload>.+)$/', $line, $matches)) {
            return null;
        }

        $payload = (string) ($matches['payload'] ?? '');
        preg_match_all('/([a-z_]+)=(".*?"|[^ ]+)/i', $payload, $tokens, PREG_SET_ORDER);
        if ($tokens === []) {
            return null;
        }

        $parsed = [
            'timestamp' => (string) ($matches['timestamp'] ?? ''),
            'source_file' => $sourceFile,
            'raw' => $line,
            'context' => [],
        ];

        foreach ($tokens as $token) {
            $key = strtolower((string) ($token[1] ?? ''));
            $value = trim((string) ($token[2] ?? ''), '"');

            if ($key === '') {
                continue;
            }

            if (in_array($key, ['tipo', 'origem', 'destino', 'id', 'status', 'msg'], true)) {
                $parsed[$key] = $value;
                continue;
            }

            $parsed['context'][$key] = $value;
        }

        $category = $this->resolveCategory((string) ($parsed['tipo'] ?? ''));
        if ($requiredCategory !== null && $category !== $requiredCategory) {
            return null;
        }

        $parsed['categoria'] = $category;

        return $parsed;
    }

    private function resolveCategory(string $type): string
    {
        $normalizedType = strtolower(trim($type));

        return self::TYPE_CATEGORY_MAP[$normalizedType] ?? self::CATEGORY_TECHNICAL;
    }

    private function buildCategoryLogPath(string $category): string
    {
        return $this->logsDirectory() . DIRECTORY_SEPARATOR . $category . '-' . date('Y-m') . '.log';
    }
}
