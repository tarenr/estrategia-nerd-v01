-- Migration: 0010_create_newsletter_campaigns_tables.sql
-- Módulo de Campanhas e Edições de Newsletter

CREATE TABLE IF NOT EXISTS `newsletter_campaigns` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `assunto` varchar(255) NOT NULL,
  `preheader` varchar(255) DEFAULT NULL,
  `template_html` mediumtext NOT NULL,
  `conteudo_texto` text DEFAULT NULL,
  `status` enum('rascunho','agendada','enviando','enviada','cancelada') NOT NULL DEFAULT 'rascunho',
  `total_destinatarios` int unsigned NOT NULL DEFAULT 0,
  `total_enviados` int unsigned NOT NULL DEFAULT 0,
  `total_falhas` int unsigned NOT NULL DEFAULT 0,
  `agendada_para` datetime DEFAULT NULL,
  `enviada_em` datetime DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_newsletter_campaigns_status` (`status`),
  KEY `idx_newsletter_campaigns_agendada` (`agendada_para`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Campanhas e edicoes periodicas de newsletter';

CREATE TABLE IF NOT EXISTS `newsletter_campaign_posts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` int unsigned NOT NULL,
  `post_id` int NOT NULL,
  `ordem` smallint unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_campaign_post` (`campaign_id`, `post_id`),
  KEY `idx_campaign_posts_campaign` (`campaign_id`),
  KEY `idx_campaign_posts_post` (`post_id`),
  CONSTRAINT `fk_campaign_posts_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `newsletter_campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_campaign_posts_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Artigos do blog vinculados a uma edicao da newsletter';

CREATE TABLE IF NOT EXISTS `newsletter_campaign_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` int unsigned NOT NULL,
  `subscriber_id` int DEFAULT NULL,
  `email` varchar(190) NOT NULL,
  `status` enum('enviado','falha') NOT NULL DEFAULT 'enviado',
  `erro` text DEFAULT NULL,
  `enviado_em` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_campaign_logs_campaign` (`campaign_id`),
  KEY `idx_campaign_logs_email` (`email`),
  KEY `idx_campaign_logs_status` (`status`),
  CONSTRAINT `fk_campaign_logs_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `newsletter_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Logs e historico de disparo individual por assinante';
