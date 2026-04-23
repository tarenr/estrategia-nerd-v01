ALTER TABLE categoria_post
  ADD COLUMN seo_title VARCHAR(160) NULL AFTER descricao,
  ADD COLUMN seo_description VARCHAR(255) NULL AFTER seo_title,
  ADD COLUMN indexar TINYINT(1) NOT NULL DEFAULT 1 AFTER ativo,
  ADD COLUMN exibir_no_menu TINYINT(1) NOT NULL DEFAULT 1 AFTER indexar;

UPDATE categoria_post
SET indexar = 1,
    exibir_no_menu = 1
WHERE indexar IS NULL
   OR exibir_no_menu IS NULL;
