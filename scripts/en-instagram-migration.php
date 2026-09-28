<?php
/**
 * -----------------------------------------------------------------------------
 * @file        scripts/en-instagram-migration.php
 * @project     Estrategia Nerd
 * @purpose     Migration: criar tabelas do módulo Instagram (FEAT-010)
 * @usage       C:\xampp\php\php.exe scripts/en-instagram-migration.php
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

/** @var \PDO $pdo */
$pdo = $GLOBALS['pdo'];

$migrations = [];

// ── 1. instagram_accounts ─────────────────────────────────────────────────────
$migrations['instagram_accounts'] = <<<SQL
CREATE TABLE IF NOT EXISTS `instagram_accounts` (
  `id`                int(11) unsigned NOT NULL AUTO_INCREMENT,
  `ig_user_id`        varchar(30)      NOT NULL COMMENT 'ID numérico da conta no Meta',
  `username`          varchar(60)      NOT NULL,
  `access_token`      text             NOT NULL COMMENT 'Token permanente de acesso',
  `token_expires_at`  datetime         DEFAULT NULL,
  `permissions`       json             DEFAULT NULL COMMENT 'Escopos autorizados',
  `profile_picture`   varchar(500)     DEFAULT NULL,
  `bio`               text             DEFAULT NULL,
  `website`           varchar(255)     DEFAULT NULL,
  `followers_count`   int(11)          DEFAULT 0,
  `follows_count`     int(11)          DEFAULT 0,
  `media_count`       int(11)          DEFAULT 0,
  `synced_at`         datetime         DEFAULT NULL,
  `ativo`             tinyint(1)       NOT NULL DEFAULT 1,
  `criado_em`         datetime         NOT NULL DEFAULT current_timestamp(),
  `atualizado_em`     datetime         NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ig_user_id` (`ig_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Contas do Instagram conectadas ao painel (FEAT-010)';
SQL;

// ── 2. instagram_posts ────────────────────────────────────────────────────────
$migrations['instagram_posts'] = <<<SQL
CREATE TABLE IF NOT EXISTS `instagram_posts` (
  `id`               int(11) unsigned NOT NULL AUTO_INCREMENT,
  `account_id`       int(11) unsigned NOT NULL,
  `status`           enum('rascunho','agendado','publicando','publicado','erro')
                       NOT NULL DEFAULT 'rascunho',
  `tipo`             enum('imagem','carrossel','reels','story')
                       NOT NULL DEFAULT 'imagem',
  `legenda`          text             DEFAULT NULL,
  `hashtags_count`   tinyint(3)       NOT NULL DEFAULT 0,
  `agendado_para`    datetime         DEFAULT NULL,
  `publicado_em`     datetime         DEFAULT NULL,
  `post_blog_id`     int(11)          DEFAULT NULL COMMENT 'Referência a posts.id (opcional)',
  `creation_id`      varchar(50)      DEFAULT NULL COMMENT 'ID do container na Meta antes da publicação',
  `ig_media_id`      varchar(50)      DEFAULT NULL COMMENT 'ID da mídia publicada no Instagram',
  `permalink`        varchar(500)     DEFAULT NULL,
  `idempotency_key`  varchar(36)      DEFAULT NULL COMMENT 'UUID para evitar duplicação',
  `error_log`        text             DEFAULT NULL,
  `origin`           enum('local','instagram') NOT NULL DEFAULT 'local',
  `curtidas`         int(11)          NOT NULL DEFAULT 0,
  `comentarios_count` int(11)         NOT NULL DEFAULT 0,
  `criado_por`       int(11)          DEFAULT NULL COMMENT 'usuario_id',
  `criado_em`        datetime         NOT NULL DEFAULT current_timestamp(),
  `atualizado_em`    datetime         NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ig_media_id`     (`ig_media_id`),
  UNIQUE KEY `uq_idempotency_key` (`idempotency_key`),
  KEY `idx_status_agendado`       (`status`, `agendado_para`),
  KEY `idx_account_id`            (`account_id`),
  CONSTRAINT `fk_igpost_account`
    FOREIGN KEY (`account_id`) REFERENCES `instagram_accounts` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Posts do Instagram: rascunhos, agendados e publicados (FEAT-010)';
SQL;

// ── 3. instagram_post_media ───────────────────────────────────────────────────
$migrations['instagram_post_media'] = <<<SQL
CREATE TABLE IF NOT EXISTS `instagram_post_media` (
  `id`          int(11) unsigned NOT NULL AUTO_INCREMENT,
  `post_id`     int(11) unsigned NOT NULL,
  `ordem`       tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Posição no carrossel (0-based)',
  `tipo_arquivo` enum('imagem','video') NOT NULL DEFAULT 'imagem',
  `caminho`     varchar(500)     NOT NULL COMMENT 'Caminho relativo em uploads/ ou URL absoluta',
  `url_publica` varchar(500)     DEFAULT NULL COMMENT 'URL pública para API da Meta',
  `largura`     smallint(5)      DEFAULT NULL,
  `altura`      smallint(5)      DEFAULT NULL,
  `duracao_s`   smallint(5)      DEFAULT NULL COMMENT 'Duração em segundos (vídeos)',
  `criado_em`   datetime         NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_post_id_ordem` (`post_id`, `ordem`),
  CONSTRAINT `fk_igmedia_post`
    FOREIGN KEY (`post_id`) REFERENCES `instagram_posts` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Arquivos de mídia de cada post do Instagram (FEAT-010)';
SQL;

// ── 4. instagram_insights_cache ───────────────────────────────────────────────
$migrations['instagram_insights_cache'] = <<<SQL
CREATE TABLE IF NOT EXISTS `instagram_insights_cache` (
  `id`                 int(11) unsigned NOT NULL AUTO_INCREMENT,
  `account_id`         int(11) unsigned NOT NULL,
  `periodo`            varchar(30)      NOT NULL DEFAULT '7d',
  `data_referencia`    date             NOT NULL COMMENT 'Data de início do período',
  `data_inicio`        date             DEFAULT NULL,
  `data_fim`           date             DEFAULT NULL,
  `visualizacoes`      int(11)          DEFAULT 0,
  `alcance`            int(11)          DEFAULT 0,
  `impressoes`         int(11)          DEFAULT 0,
  `visitas_perfil`     int(11)          DEFAULT 0,
  `interacoes`         int(11)          DEFAULT 0,
  `seguidores`         int(11)          DEFAULT 0 COMMENT 'Snapshot de seguidores naquela data',
  `variacao_seguidores` int(11)         DEFAULT 0 COMMENT 'Delta em relação ao snapshot anterior',
  `payload_raw`        json             DEFAULT NULL COMMENT 'Resposta bruta da API para auditoria',
  `criado_em`          datetime         NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_account_periodo_data` (`account_id`, `periodo`, `data_referencia`),
  KEY `idx_account_data` (`account_id`, `data_referencia`),
  CONSTRAINT `fk_iginsights_account`
    FOREIGN KEY (`account_id`) REFERENCES `instagram_accounts` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cache de métricas e histórico de seguidores do Instagram (FEAT-010)';
SQL;

// ── Executar ──────────────────────────────────────────────────────────────────
$errors = 0;

foreach ($migrations as $table => $sql) {
    try {
        $pdo->exec($sql);
        echo "[OK] Tabela '{$table}' criada (ou já existia)." . PHP_EOL;
    } catch (PDOException $e) {
        echo "[ERRO] Tabela '{$table}': " . $e->getMessage() . PHP_EOL;
        $errors++;
    }
}

echo PHP_EOL;

if ($errors === 0) {
    echo "Migration concluída com sucesso. 4 tabelas prontas." . PHP_EOL;
} else {
    echo "Migration concluída com {$errors} erro(s). Verifique acima." . PHP_EOL;
    exit(1);
}
