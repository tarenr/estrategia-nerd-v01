-- Additive, local-only. Cache do alcance (reach) da Meta por bloco de datas de até 30 dias,
-- usado pela linha de Alcance do gráfico de /admin/instagram (FEAT-010 2.14.0).
-- Rollback: DROP TABLE instagram_reach_blocks (exige autorização específica).
CREATE TABLE IF NOT EXISTS `instagram_reach_blocks` (
  `id`            int(11) unsigned NOT NULL AUTO_INCREMENT,
  `account_id`    int(11) unsigned NOT NULL,
  `data_inicio`   date             NOT NULL,
  `data_fim`      date             NOT NULL,
  `alcance`       int(11)          NOT NULL DEFAULT 0,
  `consultado_em` datetime         NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_account_bloco` (`account_id`, `data_inicio`, `data_fim`),
  CONSTRAINT `fk_igreach_account`
    FOREIGN KEY (`account_id`) REFERENCES `instagram_accounts` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Alcance da Meta por bloco de até 30 dias (FEAT-010)';
