<?php
/**
 * -----------------------------------------------------------------------------
 * @file        config/database.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Centralizar as configurações de conexão com o banco de dados.
 * @description Define os dados necessários para conexão PDO com MySQL.
 * @usage       Este arquivo é carregado pelo bootstrap antes da conexão.
 * @notes       Em ambiente XAMPP, normalmente o usuário é root e a senha é vazia.
 * -----------------------------------------------------------------------------
 */

return [
    'driver' => $_ENV['DB_DRIVER'] ?? 'mysql',
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port' => $_ENV['DB_PORT'] ?? '3306',
    'database' => $_ENV['DB_DATABASE'] ?? 'estrategia-nerd',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
];