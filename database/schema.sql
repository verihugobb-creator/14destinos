-- ═══════════════════════════════════════════════════════════════════════════
--  14 Destinos — Esquema de la base de datos
--  Importar desde: phpMyAdmin → Base de datos → Importar
-- ═══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ── users ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `email`        VARCHAR(255)     NOT NULL,
  `password`     VARCHAR(255)     NOT NULL,
  `display_name` VARCHAR(100)     NOT NULL,
  `created_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── memories ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `memories` (
  `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED     NOT NULL,
  `country_code` CHAR(2)          NOT NULL,
  `country_name` VARCHAR(100)     NOT NULL,
  `city`         VARCHAR(150)     NOT NULL,
  `latitude`     DECIMAL(9,6)     NULL,
  `longitude`    DECIMAL(9,6)     NULL,
  `visit_date`   DATE             NOT NULL,
  `title`        VARCHAR(200)     NOT NULL,
  `story`        TEXT             NULL,
  `photo_path`   VARCHAR(500)     NULL,
  `created_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_country` (`user_id`, `country_code`),
  KEY `idx_country`      (`country_code`),
  CONSTRAINT `fk_mem_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── plans ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `plans` (
  `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED     NOT NULL,
  `country_code` CHAR(2)          NOT NULL,
  `city`         VARCHAR(150)     NOT NULL,
  `trip_days`    TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `interests`    TEXT             NULL,
  `ai_plan`      LONGTEXT         NOT NULL,
  `created_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_plans_user` (`user_id`),
  CONSTRAINT `fk_plans_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── rate_limits (para limitar peticiones sin Redis) ───────────────────────────
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `key_hash`  CHAR(64)      NOT NULL,
  `hits`      SMALLINT      NOT NULL DEFAULT 1,
  `window_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`key_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
