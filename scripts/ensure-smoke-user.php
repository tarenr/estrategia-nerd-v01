<?php

declare(strict_types=1);

$sessionPath = dirname(__DIR__) . '/storage/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0777, true);
}
ini_set('session.save_path', $sessionPath);

require dirname(__DIR__) . '/bootstrap.php';

/**
 * @param array<string,mixed> $database
 */
function smoke_pdo(array $database): PDO
{
    $host = (string) ($database['host'] ?? '');
    $port = (string) ($database['port'] ?? '3306');
    $name = (string) ($database['database'] ?? '');
    $username = (string) ($database['username'] ?? '');
    $password = (string) ($database['password'] ?? '');

    if ($host === '' || $name === '' || $username === '') {
        throw new RuntimeException('Configuracao de banco incompleta.');
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function smoke_next_id(PDO $pdo): int
{
    $stmt = $pdo->query('SELECT COALESCE(MAX(id), 0) + 1 FROM usuarios');

    return max(1, (int) ($stmt !== false ? $stmt->fetchColumn() : 1));
}

/**
 * @param array<string,string> $values
 */
function smoke_update_env(array $values): void
{
    $path = dirname(__DIR__) . '/.env';
    $content = is_file($path) ? (string) file_get_contents($path) : '';

    foreach ($values as $key => $value) {
        $line = $key . '=' . $value;
        if (preg_match('/^' . preg_quote($key, '/') . '=/m', $content)) {
            $content = preg_replace('/^' . preg_quote($key, '/') . '=.*/m', $line, $content) ?? $content;
            continue;
        }

        $content = rtrim($content) . PHP_EOL . $line . PHP_EOL;
    }

    file_put_contents($path, $content);
}

$config = require base_path('config/backup.php');
$profiles = (array) ($config['profiles'] ?? []);
$user = trim((string) ($_ENV['SMOKE_ADMIN_USER'] ?? 'smoke_test'));
$email = trim((string) ($_ENV['SMOKE_ADMIN_EMAIL'] ?? 'smoke@estrategianerd.com.br'));
$password = (string) ($_ENV['SMOKE_ADMIN_PASSWORD'] ?? '');
if ($password === '') {
    $password = bin2hex(random_bytes(18));
    smoke_update_env([
        'SMOKE_ADMIN_USER' => $user,
        'SMOKE_ADMIN_EMAIL' => $email,
        'SMOKE_ADMIN_PASSWORD' => $password,
    ]);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$results = [];

foreach (['local', 'stage', 'production'] as $environment) {
    $profile = (array) ($profiles[$environment] ?? []);
    $database = (array) ($profile['database'] ?? []);

    try {
        $pdo = smoke_pdo($database);
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE usuario = :usuario OR email = :email LIMIT 1');
        $stmt->execute([
            'usuario' => $user,
            'email' => $email,
        ]);
        $existingId = (int) ($stmt->fetchColumn() ?: 0);

        if ($existingId > 0) {
            $update = $pdo->prepare(
                'UPDATE usuarios
                 SET usuario = :usuario,
                     nome = :nome,
                     email = :email,
                     papel = :papel,
                     status = :status,
                     avatar_tipo = :avatar_tipo,
                     avatar_icone = :avatar_icone,
                     avatar_cor = :avatar_cor,
                     avatar_imagem = :avatar_imagem,
                     avatar_focal_x = :avatar_focal_x,
                     avatar_focal_y = :avatar_focal_y,
                     senha = :senha
                 WHERE id = :id
                 LIMIT 1'
            );
            $update->execute([
                'id' => $existingId,
                'usuario' => $user,
                'nome' => 'Smoke Test',
                'email' => $email,
                'papel' => 'editor',
                'status' => 'ativo',
                'avatar_tipo' => 'icone',
                'avatar_icone' => 'fa-solid fa-vial-circle-check',
                'avatar_cor' => '#22d3ee',
                'avatar_imagem' => '',
                'avatar_focal_x' => 50,
                'avatar_focal_y' => 50,
                'senha' => $hash,
            ]);
            $results[] = [$environment, 'atualizado', $existingId];
            continue;
        }

        $insert = $pdo->prepare(
            'INSERT INTO usuarios (id, usuario, nome, email, papel, status, avatar_tipo, avatar_icone, avatar_cor, avatar_imagem, avatar_focal_x, avatar_focal_y, senha)
             VALUES (:id, :usuario, :nome, :email, :papel, :status, :avatar_tipo, :avatar_icone, :avatar_cor, :avatar_imagem, :avatar_focal_x, :avatar_focal_y, :senha)'
        );
        $id = smoke_next_id($pdo);
        $insert->execute([
            'id' => $id,
            'usuario' => $user,
            'nome' => 'Smoke Test',
            'email' => $email,
            'papel' => 'editor',
            'status' => 'ativo',
            'avatar_tipo' => 'icone',
            'avatar_icone' => 'fa-solid fa-vial-circle-check',
            'avatar_cor' => '#22d3ee',
            'avatar_imagem' => '',
            'avatar_focal_x' => 50,
            'avatar_focal_y' => 50,
            'senha' => $hash,
        ]);
        $results[] = [$environment, 'criado', $id];
    } catch (Throwable $exception) {
        $results[] = [$environment, 'falhou: ' . $exception->getMessage(), 0];
    }
}

echo 'Usuario tecnico de smoke test: ' . $user . PHP_EOL;
foreach ($results as [$environment, $status, $id]) {
    echo sprintf('- %s: %s%s', strtoupper((string) $environment), (string) $status, $id > 0 ? ' (id ' . $id . ')' : '') . PHP_EOL;
}
