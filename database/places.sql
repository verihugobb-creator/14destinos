-- ═══════════════════════════════════════════════════════════════════
--  14 Destinos — Places table
--  Stores custom/favourite places added by users
-- ═══════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `places` (
  `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(300)     NOT NULL,
  `country_code` CHAR(2)          NOT NULL,
  `country_name` VARCHAR(100)     NOT NULL,
  `region`       VARCHAR(200)     NULL,
  `latitude`     DECIMAL(9,6)     NOT NULL,
  `longitude`    DECIMAL(9,6)     NOT NULL,
  `osm_id`       VARCHAR(50)      NULL COMMENT 'OpenStreetMap ID',
  `place_type`   VARCHAR(50)      NULL COMMENT 'city/town/village/hamlet/etc',
  `population`   INT UNSIGNED     NULL,
  `created_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_country` (`country_code`),
  KEY `idx_name`    (`name`(100)),
  KEY `idx_coords`  (`latitude`, `longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

