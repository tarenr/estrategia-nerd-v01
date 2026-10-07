-- Additive, local-only. Existing posts retain their status and media references.
ALTER TABLE instagram_posts
  ADD COLUMN publish_phase varchar(32) NOT NULL DEFAULT 'idle' AFTER creation_id,
  ADD COLUMN publish_attempted_at datetime DEFAULT NULL AFTER publish_phase;
