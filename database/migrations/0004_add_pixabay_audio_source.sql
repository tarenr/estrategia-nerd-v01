-- Migration: 0004_add_pixabay_audio_source.sql
-- Descrição: Faixas do Pixabay na biblioteca de trilhas dos Reels (IMP-032).
-- Acrescenta a origem 'pixabay' e o link da página da faixa (comprovante da licença).

ALTER TABLE `instagram_audio_tracks`
  MODIFY COLUMN `origem` enum('local','audius','custom','pixabay') NOT NULL DEFAULT 'local',
  ADD COLUMN `source_url` varchar(500) DEFAULT NULL COMMENT 'Página da faixa na origem (ex.: Pixabay)' AFTER `origem_id`;
