-- MyCurator Cloud Server Database Tables
-- Updated for MySQL 5.7+ / MariaDB 10.2+ with utf8mb4 support
-- Version 3.81 - February 2026

-- ========================================================================
-- REQUIRED TABLES (needed for all installations)
-- ========================================================================

-- Validation table for API tokens and user authentication
CREATE TABLE `wp_cs_validate` (
  `token` char(32) NOT NULL,
  `user_id` int NOT NULL,
  `product` char(20) NOT NULL,
  `end_date` date DEFAULT NULL,
  `classify_calls` bigint NOT NULL,
  `run_tot` int NOT NULL,
  `this_week` int NOT NULL,
  PRIMARY KEY (`token`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Topic storage table for cloud processing
CREATE TABLE `wp_cs_topic` (
  `topic_key` int NOT NULL AUTO_INCREMENT,
  `token` char(32) NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `referer` varchar(200) DEFAULT NULL,
  `topic_id` int NOT NULL,
  `topic_name` varchar(200) NOT NULL,
  `topic_slug` varchar(200) NOT NULL,
  `topic_status` varchar(20) NOT NULL,
  `topic_type` varchar(20) NOT NULL,
  `topic_search_1` text,
  `topic_search_2` text,
  `topic_exclude` text,
  `topic_sources` longtext,
  `topic_aidbfc` longtext,
  `topic_aidbcat` longtext,
  `topic_skip_domains` longtext,
  `topic_min_length` int DEFAULT NULL,
  `topic_cat` int DEFAULT NULL,
  `topic_tag` int DEFAULT NULL,
  `topic_tag_search2` char(1) DEFAULT NULL,
  `topic_options` text,
  PRIMARY KEY (`topic_key`),
  KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================================
-- OPTIONAL TABLES (only needed for Request Mode)
-- ========================================================================
-- These tables are only needed if you run the Cloud Server in Request Mode,
-- which you won't need for a single client of the Cloud Server.
-- 
-- In the MyCurator plugin on your site go to Options menu, Admin tab and 
-- clear the option "Page Request Mode" and click Save Options.
-- You will not need these tables if that option is cleared.
--
-- Request mode is an asynchronous mode where MyCurator queues up requests 
-- for articles and the server fills them. Then MyCurator comes back and 
-- gets the articles later. This is for a central Cloud Service for 
-- multiple sites.
-- ========================================================================

-- Cache table for storing retrieved page content
-- Note: pr_url index uses prefix (255) to avoid "key too long" error with utf8mb4
CREATE TABLE `wp_cs_cache` (
  `pr_id` bigint NOT NULL AUTO_INCREMENT,
  `pr_url` varchar(1000) NOT NULL,
  `pr_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `pr_page_content` longtext,
  `pr_usage` int NOT NULL DEFAULT 0,
  `pr_rqst` int DEFAULT NULL,
  PRIMARY KEY (`pr_id`),
  KEY `pr_url` (`pr_url`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Requests queue table for asynchronous page fetching
-- Note: rq_url index uses prefix (255) to avoid "key too long" error with utf8mb4
CREATE TABLE `wp_cs_requests` (
  `rq_id` bigint NOT NULL AUTO_INCREMENT,
  `rq_url` varchar(1000) NOT NULL,
  `rq_errcnt` int DEFAULT NULL,
  `rq_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `rq_err_try` int DEFAULT NULL,
  `rq_dbkey` int DEFAULT NULL,
  PRIMARY KEY (`rq_id`),
  KEY `rq_url` (`rq_url`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================================
-- OPTIONAL TABLE (for statistics - requires tgtinfo-admin CRON)
-- ========================================================================
-- This table is for statistical data captured during a CRON process in 
-- the Admin plugin (tgtinfo-admin). You can turn off the use of this 
-- table by commenting out the CRON code in lines 52-55 of the 
-- tgtinfo-admin.php program.
-- ========================================================================

-- Daily statistics table
CREATE TABLE `wp_cs_dailytot` (
  `last_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `day_total` int NOT NULL,
  `run_total` bigint NOT NULL,
  `cache_day` int DEFAULT NULL,
  `cache_run` int DEFAULT NULL,
  `rqst_day` int DEFAULT NULL,
  `rqst_run` int DEFAULT NULL,
  PRIMARY KEY (`last_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================================
-- NOTES
-- ========================================================================
-- 1. All tables use InnoDB engine for better performance and foreign key support
-- 2. utf8mb4 character set is used for full Unicode support (including emoji)
-- 3. Index prefixes (255) on varchar(1000) columns prevent "key too long" errors
-- 4. AUTO_INCREMENT added to primary key columns for proper auto-increment behavior
-- 5. Compatible with MySQL 5.7+, MariaDB 10.2+, and later versions
