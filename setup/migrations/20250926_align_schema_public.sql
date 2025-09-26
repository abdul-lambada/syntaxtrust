-- 1) portfolio.status should include 'upcoming' (code expects it)
ALTER TABLE `portfolio`
  MODIFY `status` ENUM('completed','ongoing','upcoming') DEFAULT 'completed';

-- 2) pricing_plans.billing_period should include 'quarterly' and default to 'one_time' (UI offers 'quarterly' and prefers one_time)
ALTER TABLE `pricing_plans`
  MODIFY `billing_period` ENUM('monthly','quarterly','yearly','one_time') DEFAULT 'one_time';

-- 3) settings.setting_type should include 'image' (admin supports image-type settings)
ALTER TABLE `settings`
  MODIFY `setting_type` ENUM('text','number','boolean','json','image') DEFAULT 'text';

-- 4) orders: application writes admin_notes when present; add column for consistency (code falls back to notes if absent)
ALTER TABLE `orders`
  ADD COLUMN `admin_notes` TEXT NULL AFTER `notes`;
