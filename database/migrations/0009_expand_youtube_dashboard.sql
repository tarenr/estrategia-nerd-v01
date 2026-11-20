-- Amplia o módulo YouTube com acervo remoto, classificação e histórico diário.
ALTER TABLE `youtube_accounts`
  ADD COLUMN `description` text DEFAULT NULL AFTER `title`,
  ADD COLUMN `published_at` datetime DEFAULT NULL AFTER `thumbnail_url`,
  ADD COLUMN `uploads_playlist_id` varchar(64) DEFAULT NULL AFTER `published_at`;

ALTER TABLE `youtube_videos`
  MODIFY COLUMN `video_path` varchar(2048) DEFAULT NULL,
  ADD COLUMN `origin` enum('local','youtube') NOT NULL DEFAULT 'local' AFTER `account_id`,
  ADD COLUMN `content_kind` enum('video','short') NOT NULL DEFAULT 'video' AFTER `origin`,
  ADD COLUMN `duration_seconds` int unsigned NOT NULL DEFAULT 0 AFTER `content_kind`,
  ADD COLUMN `thumbnail_url` varchar(2048) DEFAULT NULL AFTER `thumbnail_path`,
  ADD COLUMN `views_count` bigint unsigned NOT NULL DEFAULT 0 AFTER `permalink`,
  ADD COLUMN `likes_count` bigint unsigned NOT NULL DEFAULT 0 AFTER `views_count`,
  ADD COLUMN `comments_count` bigint unsigned NOT NULL DEFAULT 0 AFTER `likes_count`,
  ADD COLUMN `last_synced_at` datetime DEFAULT NULL AFTER `comments_count`,
  ADD KEY `idx_youtube_origin_kind` (`origin`,`content_kind`),
  ADD KEY `idx_youtube_published` (`published_at`);

ALTER TABLE `youtube_metrics_cache`
  ADD COLUMN `metric_date` date DEFAULT NULL AFTER `snapshot_at`,
  ADD COLUMN `subscribers_gained` bigint unsigned NOT NULL DEFAULT 0 AFTER `subscribers`,
  ADD COLUMN `subscribers_lost` bigint unsigned NOT NULL DEFAULT 0 AFTER `subscribers_gained`,
  ADD COLUMN `source` enum('snapshot','analytics') NOT NULL DEFAULT 'snapshot' AFTER `subscribers_lost`,
  ADD UNIQUE KEY `uq_youtube_daily_metric` (`account_id`,`video_id`,`metric_date`,`source`);
