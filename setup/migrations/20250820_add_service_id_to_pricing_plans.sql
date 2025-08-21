-- Migration: Add service_id to pricing_plans and backfill
START TRANSACTION;

-- 1) Add column if not exists
ALTER TABLE `pricing_plans`
  ADD COLUMN `service_id` INT NULL AFTER `id`;

-- 2) Index + FK
ALTER TABLE `pricing_plans`
  ADD KEY `idx_service_id` (`service_id`);

ALTER TABLE `pricing_plans`
  ADD CONSTRAINT `fk_pricing_plans_service`
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`)
  ON DELETE SET NULL;

-- 3) Backfill: assign first active service as default for plans without service
UPDATE `pricing_plans` SET `service_id` = (
  SELECT `id` FROM `services` WHERE `is_active` = 1 ORDER BY `sort_order` ASC, `name` ASC LIMIT 1
) WHERE `service_id` IS NULL OR `service_id` = 0;

-- 4) Ensure plans with a valid service are active
UPDATE `pricing_plans` SET `is_active` = 1
WHERE (`service_id` IS NOT NULL AND `service_id` <> 0)
  AND (`is_active` = 0 OR `is_active` IS NULL);

COMMIT;
