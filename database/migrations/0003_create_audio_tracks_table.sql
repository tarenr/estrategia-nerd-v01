-- Migration: 0003_create_audio_tracks_table.sql
-- Descrição: Criação da tabela de trilhas sonoras e novos campos de áudio em posts do Instagram (FEAT-012)

CREATE TABLE IF NOT EXISTS `instagram_audio_tracks` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `origem` enum('local','audius','custom') NOT NULL DEFAULT 'local',
  `origem_id` varchar(100) DEFAULT NULL COMMENT 'ID da faixa no Audius ou identificador externo',
  `titulo` varchar(255) NOT NULL,
  `artista` varchar(255) NOT NULL DEFAULT 'Desconhecido',
  `genero` varchar(100) DEFAULT NULL,
  `arquivo_path` varchar(500) NOT NULL COMMENT 'Caminho relativo do arquivo mp3/m4a no storage/uploads',
  `duracao_s` int(11) NOT NULL DEFAULT 0 COMMENT 'Duração total do arquivo em segundos',
  `file_hash` varchar(64) DEFAULT NULL COMMENT 'Hash SHA-256 para evitar duplicidade de downloads',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_origem_id` (`origem`, `origem_id`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Faixas de áudio disponíveis para Reels/Posts com trilha sonora (FEAT-012)';

ALTER TABLE `instagram_posts`
  ADD COLUMN `audio_track_id` int(11) unsigned DEFAULT NULL AFTER `post_blog_id`,
  ADD COLUMN `audio_start_seconds` int(11) NOT NULL DEFAULT 0 AFTER `audio_track_id`,
  ADD COLUMN `audio_duration_seconds` int(11) NOT NULL DEFAULT 0 AFTER `audio_start_seconds`,
  ADD COLUMN `video_rendered_path` varchar(500) DEFAULT NULL AFTER `audio_duration_seconds`,
  ADD COLUMN `render_status` enum('idle','rendering','ready','failed') NOT NULL DEFAULT 'idle' AFTER `video_rendered_path`,
  ADD CONSTRAINT `fk_igpost_audio_track` FOREIGN KEY (`audio_track_id`) REFERENCES `instagram_audio_tracks` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
