-- Migration: 0008_create_youtube_tables.sql
-- Módulo YouTube local-only: conta, fila de vídeos e snapshots de métricas.

CREATE TABLE IF NOT EXISTS `youtube_accounts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `channel_id` varchar(32) NOT NULL,
  `title` varchar(255) NOT NULL,
  `handle` varchar(100) DEFAULT NULL,
  `custom_url` varchar(255) DEFAULT NULL,
  `thumbnail_url` varchar(2048) DEFAULT NULL,
  `scopes` json DEFAULT NULL,
  `subscribers_count` bigint unsigned NOT NULL DEFAULT 0,
  `video_count` bigint unsigned NOT NULL DEFAULT 0,
  `view_count` bigint unsigned NOT NULL DEFAULT 0,
  `synced_at` datetime DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_youtube_channel_id` (`channel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Canais do YouTube conectados ao painel local';

CREATE TABLE IF NOT EXISTS `youtube_videos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int unsigned NOT NULL,
  `status` enum('rascunho','agendado','enviando','processando','agendado_remoto','publicado','erro','resultado_incerto','cancelado') NOT NULL DEFAULT 'rascunho',
  `publish_phase` varchar(40) NOT NULL DEFAULT 'idle',
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `tags` json DEFAULT NULL,
  `category_id` varchar(8) NOT NULL DEFAULT '28',
  `privacy_status` enum('private','unlisted','public') NOT NULL DEFAULT 'private',
  `made_for_kids` tinyint(1) NOT NULL DEFAULT 0,
  `contains_synthetic_media` tinyint(1) NOT NULL DEFAULT 0,
  `scheduled_at` datetime DEFAULT NULL,
  `remote_upload_due_at` datetime DEFAULT NULL,
  `uploaded_at` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `post_blog_id` int DEFAULT NULL,
  `video_path` varchar(2048) NOT NULL,
  `thumbnail_path` varchar(2048) DEFAULT NULL,
  `source_size` bigint unsigned DEFAULT NULL,
  `source_sha256` char(64) DEFAULT NULL,
  `youtube_video_id` varchar(32) DEFAULT NULL,
  `permalink` varchar(500) DEFAULT NULL,
  `resumable_session_uri` text DEFAULT NULL COMMENT 'Sensível: nunca registrar em logs',
  `upload_offset` bigint unsigned NOT NULL DEFAULT 0,
  `upload_attempts` smallint unsigned NOT NULL DEFAULT 0,
  `processing_status` varchar(32) DEFAULT NULL,
  `idempotency_key` varchar(64) NOT NULL,
  `error_log` text DEFAULT NULL,
  `criado_por` int DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_youtube_video_id` (`youtube_video_id`),
  UNIQUE KEY `uq_youtube_idempotency` (`idempotency_key`),
  KEY `idx_youtube_due` (`status`, `remote_upload_due_at`),
  KEY `idx_youtube_scheduled` (`scheduled_at`),
  CONSTRAINT `fk_youtube_video_account` FOREIGN KEY (`account_id`) REFERENCES `youtube_accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Fila local de vídeos e Shorts do YouTube';

CREATE TABLE IF NOT EXISTS `youtube_metrics_cache` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int unsigned NOT NULL,
  `video_id` int unsigned DEFAULT NULL,
  `snapshot_at` datetime NOT NULL,
  `views` bigint unsigned NOT NULL DEFAULT 0,
  `likes` bigint unsigned NOT NULL DEFAULT 0,
  `comments` bigint unsigned NOT NULL DEFAULT 0,
  `subscribers` bigint unsigned DEFAULT NULL,
  `payload_raw` json DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_youtube_metrics_account_date` (`account_id`, `snapshot_at`),
  KEY `idx_youtube_metrics_video_date` (`video_id`, `snapshot_at`),
  CONSTRAINT `fk_youtube_metrics_account` FOREIGN KEY (`account_id`) REFERENCES `youtube_accounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_youtube_metrics_video` FOREIGN KEY (`video_id`) REFERENCES `youtube_videos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Snapshots básicos do canal e vídeos do YouTube';
