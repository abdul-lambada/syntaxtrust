-- Drop legacy columns to keep schema consistent with new UI (Option B)
-- Idempotent for MariaDB/MySQL where IF EXISTS is supported.

START TRANSACTION;

-- PRICING PLANS: drop unused index and columns
DROP INDEX IF EXISTS idx_is_popular ON pricing_plans;

ALTER TABLE pricing_plans
  DROP COLUMN IF EXISTS description,
  DROP COLUMN IF EXISTS features,
  DROP COLUMN IF EXISTS delivery_time,
  DROP COLUMN IF EXISTS technologies,
  DROP COLUMN IF EXISTS color,
  DROP COLUMN IF EXISTS icon,
  DROP COLUMN IF EXISTS is_popular;

-- SERVICES: drop unused index and columns
DROP INDEX IF EXISTS idx_is_featured ON services;

ALTER TABLE services
  DROP COLUMN IF EXISTS image,
  DROP COLUMN IF EXISTS price,
  DROP COLUMN IF EXISTS duration,
  DROP COLUMN IF EXISTS features,
  DROP COLUMN IF EXISTS is_featured;

COMMIT;
