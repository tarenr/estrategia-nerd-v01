-- Additive and idempotent for databases created before post types were introduced.
-- Do not run unrelated pending migrations when repairing a remote environment.
ALTER TABLE posts ADD COLUMN IF NOT EXISTS tipo_post varchar(30) DEFAULT NULL;
