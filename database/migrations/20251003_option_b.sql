-- Option B Migration: Services Audience fields + Pricing Plans Starting Plan
-- Safe, idempotent SQL. Run in MySQL/MariaDB connected to your app database.
-- It conditionally adds columns and indexes only if missing.

-- Transaction for safety (DDL may cause implicit commit on some engines; still okay)
START TRANSACTION;

-- Helpers: conditional add column / index
DELIMITER //
CREATE PROCEDURE add_column_if_not_exists(IN dbName VARCHAR(64), IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = dbName AND TABLE_NAME = tbl AND COLUMN_NAME = col
    ) THEN
        SET @s = ddl;
        PREPARE stmt FROM @s;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //

CREATE PROCEDURE add_index_if_not_exists(IN dbName VARCHAR(64), IN tbl VARCHAR(64), IN idx VARCHAR(64), IN ddl TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = dbName AND TABLE_NAME = tbl AND INDEX_NAME = idx
    ) THEN
        SET @s = ddl;
        PREPARE stmt FROM @s;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //
DELIMITER ;

-- Resolve current database name
SET @DB := DATABASE();

-- SERVICES: audience_* fields
CALL add_column_if_not_exists(@DB, 'services', 'audience_enabled',
  'ALTER TABLE services ADD COLUMN audience_enabled TINYINT(1) DEFAULT 0 AFTER sort_order');

CALL add_column_if_not_exists(@DB, 'services', 'audience_slug',
  'ALTER TABLE services ADD COLUMN audience_slug VARCHAR(100) NULL UNIQUE AFTER audience_enabled');

CALL add_column_if_not_exists(@DB, 'services', 'audience_subtitle',
  'ALTER TABLE services ADD COLUMN audience_subtitle VARCHAR(255) NULL AFTER audience_slug');

CALL add_column_if_not_exists(@DB, 'services', 'audience_features',
  'ALTER TABLE services ADD COLUMN audience_features LONGTEXT NULL AFTER audience_subtitle');

CALL add_column_if_not_exists(@DB, 'services', 'audience_wa_text',
  'ALTER TABLE services ADD COLUMN audience_wa_text VARCHAR(255) NULL AFTER audience_features');

-- Helpful index for queries in public pages
CALL add_index_if_not_exists(@DB, 'services', 'idx_services_audience',
  'CREATE INDEX idx_services_audience ON services (is_active, audience_enabled, sort_order)');

-- PRICING_PLANS: is_starting_plan field
CALL add_column_if_not_exists(@DB, 'pricing_plans', 'is_starting_plan',
  'ALTER TABLE pricing_plans ADD COLUMN is_starting_plan TINYINT(1) DEFAULT 0 AFTER is_popular');

-- Helpful index for starting plan price lookups
CALL add_index_if_not_exists(@DB, 'pricing_plans', 'idx_pricing_starting',
  'CREATE INDEX idx_pricing_starting ON pricing_plans (service_id, is_active, is_starting_plan, price)');

-- Cleanup helper procedures
DROP PROCEDURE IF EXISTS add_column_if_not_exists;
DROP PROCEDURE IF EXISTS add_index_if_not_exists;

COMMIT;

-- OPTIONAL SEEDING (uncomment and edit IDs as needed)
-- The following statements are examples. Review before running.
--
-- -- Enable audience on first few active services (by sort_order, name) and set defaults if empty
-- UPDATE services s
-- JOIN (
--   SELECT id, name FROM services WHERE is_active = 1 ORDER BY sort_order ASC, name ASC LIMIT 3
-- ) x ON x.id = s.id
-- SET s.audience_enabled = 1,
--     s.audience_slug = COALESCE(NULLIF(s.audience_slug, ''), LOWER(REPLACE(REPLACE(REPLACE(x.name, '  ', ' '), ' ', '-'), '&', 'and'))),
--     s.audience_subtitle = COALESCE(NULLIF(s.audience_subtitle, ''), 'Paket sesuai kebutuhan Anda'),
--     s.audience_wa_text = COALESCE(NULLIF(s.audience_wa_text, ''), CONCAT('Halo, saya tertarik paket ', x.name)),
--     s.audience_features = COALESCE(NULLIF(s.audience_features, ''), '["Kualitas baik","Proses cepat","Harga fleksibel"]');
--
-- -- Clear starting plan flags then mark the cheapest active plan per service
-- UPDATE pricing_plans SET is_starting_plan = 0;
-- UPDATE pricing_plans p
-- JOIN (
--   SELECT service_id, MIN(price) AS min_price
--   FROM pricing_plans
--   WHERE is_active = 1 AND price > 0
--   GROUP BY service_id
-- ) m ON p.service_id = m.service_id AND p.price = m.min_price
-- SET p.is_starting_plan = 1;
