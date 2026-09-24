-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: estrategia-nerd
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `auth_tokens`
--

DROP TABLE IF EXISTS `auth_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auth_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `selector` varchar(24) NOT NULL,
  `hash_validator` varchar(64) NOT NULL,
  `expira` datetime NOT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_selector` (`selector`),
  KEY `idx_expira` (`expira`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `autores`
--

DROP TABLE IF EXISTS `autores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `autores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `categoria_post`
--

DROP TABLE IF EXISTS `categoria_post`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categoria_post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `descricao` text DEFAULT NULL,
  `seo_title` varchar(160) DEFAULT NULL,
  `seo_description` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `indexar` tinyint(1) NOT NULL DEFAULT 1,
  `exibir_no_menu` tinyint(1) NOT NULL DEFAULT 1,
  `ordem` int(10) unsigned NOT NULL DEFAULT 0,
  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),
  `cor` varchar(7) NOT NULL DEFAULT '#00d4ff',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `categorias`
--

DROP TABLE IF EXISTS `categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `cor` varchar(7) DEFAULT '#00d4ff',
  `descricao` text DEFAULT NULL,
  `ordem` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comentarios`
--

DROP TABLE IF EXISTS `comentarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comentarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `nome` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `comentario` text NOT NULL,
  `status` enum('pendente','aprovado','reprovado','spam') DEFAULT 'pendente',
  `parent_id` int(11) DEFAULT NULL,
  `admin_user_id` int(11) DEFAULT NULL,
  `respondido` tinyint(1) DEFAULT 0,
  `data` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`),
  KEY `idx_status` (`status`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_respondido` (`respondido`),
  KEY `idx_comentarios_admin_user_id` (`admin_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comentarios_log`
--

DROP TABLE IF EXISTS `comentarios_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comentarios_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comentario_id` int(11) NOT NULL,
  `acao` enum('aprovar','reprovar','spam','excluir','responder') NOT NULL,
  `admin_id` int(11) NOT NULL,
  `admin_nome` varchar(100) NOT NULL,
  `data` timestamp NULL DEFAULT current_timestamp(),
  `ip` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_comentario` (`comentario_id`),
  KEY `idx_data` (`data`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `configuracoes`
--

DROP TABLE IF EXISTS `configuracoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `configuracoes` (
  `chave` varchar(100) NOT NULL,
  `valor` longtext DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `editorial_automation_categories`
--

DROP TABLE IF EXISTS `editorial_automation_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `editorial_automation_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria_post_id` int(11) NOT NULL,
  `source_theme` varchar(180) NOT NULL,
  `suggested_name` varchar(120) NOT NULL,
  `suggested_slug` varchar(140) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_editorial_automation_category` (`categoria_post_id`),
  KEY `idx_editorial_automation_categories_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `estatisticas`
--

DROP TABLE IF EXISTS `estatisticas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estatisticas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `data` date DEFAULT NULL,
  `views` int(11) DEFAULT 0,
  `posts_novos` int(11) DEFAULT 0,
  `inscricoes` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `data` (`data`)
) ENGINE=InnoDB AUTO_INCREMENT=357 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `likes`
--

DROP TABLE IF EXISTS `likes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `link_clicks`
--

DROP TABLE IF EXISTS `link_clicks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `link_clicks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `link_id` int(11) NOT NULL,
  `origem` varchar(60) DEFAULT NULL,
  `referer` varchar(2048) DEFAULT NULL,
  `session_hash` char(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `clicked_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_link_clicks_link_id` (`link_id`),
  KEY `idx_link_clicks_clicked_at` (`clicked_at`),
  KEY `idx_link_clicks_session_hash` (`session_hash`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `links`
--

DROP TABLE IF EXISTS `links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(150) NOT NULL,
  `slug` varchar(160) NOT NULL,
  `url` varchar(2048) NOT NULL,
  `tipo` enum('produto','cupom','conteudo','rede_social','servico') NOT NULL DEFAULT 'produto',
  `promocao` tinyint(1) NOT NULL DEFAULT 0,
  `desconto_percentual` varchar(20) DEFAULT NULL,
  `desconto_contexto` varchar(160) DEFAULT NULL,
  `codigo_cupom` varchar(80) DEFAULT NULL,
  `secao_publica` varchar(30) NOT NULL DEFAULT 'produtos',
  `subgrupo_publico` varchar(80) DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `cta_curto` varchar(120) DEFAULT NULL,
  `texto_botao` varchar(80) DEFAULT NULL,
  `selo` varchar(60) DEFAULT NULL,
  `imagem` varchar(255) DEFAULT NULL,
  `posicao` int(11) NOT NULL DEFAULT 0,
  `status` enum('ativo','oculto','expirado','quebrado') NOT NULL DEFAULT 'ativo',
  `destaque` tinyint(1) NOT NULL DEFAULT 0,
  `expira_em` datetime DEFAULT NULL,
  `ultima_verificacao` datetime DEFAULT NULL,
  `codigo_http` smallint(6) DEFAULT NULL,
  `url_final` varchar(2048) DEFAULT NULL,
  `observacao_status` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_links_slug` (`slug`),
  KEY `idx_links_tipo` (`tipo`),
  KEY `idx_links_status` (`status`),
  KEY `idx_links_posicao` (`posicao`),
  KEY `idx_links_expira_em` (`expira_em`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `login_tentativas`
--

DROP TABLE IF EXISTS `login_tentativas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_tentativas` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `usuario_tentado` varchar(50) DEFAULT NULL,
  `sucesso` tinyint(1) DEFAULT 0,
  `data_hora` datetime DEFAULT current_timestamp(),
  `user_agent` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip_hora` (`ip`,`data_hora`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `newsletter`
--

DROP TABLE IF EXISTS `newsletter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `newsletter` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `nome` varchar(100) DEFAULT NULL,
  `data_cadastro` datetime DEFAULT current_timestamp(),
  `status` enum('ativo','inativo','desinscreve') NOT NULL DEFAULT 'ativo',
  `ip` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `post_slug_history`
--

DROP TABLE IF EXISTS `post_slug_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `post_slug_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_post_slug_history_slug` (`slug`),
  KEY `idx_post_slug_history_post_id` (`post_id`),
  CONSTRAINT `fk_post_slug_history_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `posts`
--

DROP TABLE IF EXISTS `posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `resumo` text DEFAULT NULL,
  `conteudo` longtext NOT NULL,
  `categoria` enum('gadgets','hardware','games','cultura','dicas','lifestyle') NOT NULL DEFAULT 'gadgets',
  `categoria_id` int(11) DEFAULT NULL,
  `categoria_post_id` int(10) unsigned DEFAULT NULL,
  `tipo_post` varchar(30) DEFAULT NULL,
  `imagem_capa` varchar(255) DEFAULT NULL,
  `imagem_thumb` varchar(255) DEFAULT NULL,
  `autor_id` int(11) DEFAULT 1,
  `data_publicacao` datetime DEFAULT current_timestamp(),
  `data_atualizacao` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tempo_leitura` int(11) DEFAULT 5,
  `views` int(11) DEFAULT 0,
  `curtidas` int(11) DEFAULT 0,
  `seo_title` varchar(200) DEFAULT NULL,
  `seo_description` varchar(300) DEFAULT NULL,
  `seo_keywords` varchar(300) DEFAULT NULL,
  `tags` varchar(500) DEFAULT NULL,
  `status` enum('publicado','rascunho','agendado') DEFAULT 'rascunho',
  `destaque` tinyint(1) DEFAULT 0,
  `proximo_post_id` int(11) DEFAULT NULL,
  `comentarios_count` int(11) DEFAULT 0,
  `likes_count` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_categoria` (`categoria`),
  KEY `idx_status` (`status`),
  KEY `idx_destaque` (`destaque`),
  KEY `idx_data` (`data_publicacao`),
  KEY `idx_status_destaque_data` (`status`,`destaque`,`data_publicacao`),
  KEY `idx_autor_status_data` (`autor_id`,`status`,`data_publicacao`),
  KEY `fk_posts_categoria_post` (`categoria_post_id`),
  KEY `fk_posts_categorias` (`categoria_id`),
  KEY `idx_posts_status` (`status`),
  KEY `idx_posts_stats` (`views`,`curtidas`,`comentarios_count`,`data_publicacao`),
  KEY `idx_posts_proximo_post_id` (`proximo_post_id`),
  FULLTEXT KEY `idx_busca` (`titulo`,`resumo`,`conteudo`),
  CONSTRAINT `fk_posts_proximo_post` FOREIGN KEY (`proximo_post_id`) REFERENCES `posts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `nome` varchar(120) NOT NULL DEFAULT '',
  `email` varchar(160) NOT NULL DEFAULT '',
  `papel` varchar(20) NOT NULL DEFAULT 'admin',
  `status` varchar(20) NOT NULL DEFAULT 'ativo',
  `avatar_tipo` varchar(20) NOT NULL DEFAULT 'icone',
  `avatar_icone` varchar(80) NOT NULL DEFAULT 'fa-solid fa-user',
  `avatar_cor` varchar(20) NOT NULL DEFAULT '#38bdf8',
  `avatar_imagem` varchar(255) NOT NULL DEFAULT '',
  `avatar_focal_x` decimal(5,2) NOT NULL DEFAULT 50.00,
  `avatar_focal_y` decimal(5,2) NOT NULL DEFAULT 50.00,
  `ultimo_acesso` datetime DEFAULT NULL,
  `senha` varchar(255) NOT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`),
  UNIQUE KEY `uq_usuarios_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `v_stats_dashboard`
--

DROP TABLE IF EXISTS `v_stats_dashboard`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `v_stats_dashboard` (
  `total_posts` bigint(21) DEFAULT NULL,
  `publicados` bigint(21) DEFAULT NULL,
  `rascunhos` bigint(21) DEFAULT NULL,
  `total_views` decimal(32,0) DEFAULT NULL,
  `comentarios_pendentes` bigint(21) DEFAULT NULL,
  `inscritos` bigint(21) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `visitantes`
--

DROP TABLE IF EXISTS `visitantes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `visitantes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` varchar(255) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `data` date NOT NULL,
  `hora` time NOT NULL,
  `pagina` varchar(255) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_session_data` (`session_id`,`data`),
  KEY `idx_data` (`data`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-24  3:10:50
