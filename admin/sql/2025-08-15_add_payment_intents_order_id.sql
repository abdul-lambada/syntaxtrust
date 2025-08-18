-- Migration: Add order_id to payment_intents and backfill
-- Created: 2025-08-15 19:31:00+07:00

-- 1) Add nullable order_id + index (safe to re-run with IF NOT EXISTS guards where supported)
ALTER TABLE payment_intents
  ADD COLUMN IF NOT EXISTS order_id INT NULL AFTER pricing_plan_id,
  ADD INDEX IF NOT EXISTS idx_payment_intents_order_id (order_id);

-- Ensure signedness matches orders.id (signed INT)
ALTER TABLE payment_intents
  MODIFY COLUMN order_id INT NULL;

-- 2) Add FK (may fail if engine/signedness mismatched; re-run after aligning)
-- Recreate FK to ensure no mismatch (ignore error if not exists when dropping)
-- Note: Some MySQL versions don't support IF EXISTS on DROP FOREIGN KEY; run drop only if present
-- ALTER TABLE payment_intents DROP FOREIGN KEY fk_payment_intents_order;
ALTER TABLE payment_intents
  ADD CONSTRAINT fk_pi_order_id
  FOREIGN KEY (order_id) REFERENCES orders(id)
  ON UPDATE CASCADE ON DELETE SET NULL;

-- 3) Backfill order_id using intent_number in orders.project_description
UPDATE payment_intents pi
JOIN orders o 
  ON CONVERT(o.project_description USING utf8mb4) COLLATE utf8mb4_unicode_ci 
     LIKE CONCAT('%', CONVERT(pi.intent_number USING utf8mb4) COLLATE utf8mb4_unicode_ci, '%')
SET pi.order_id = o.id
WHERE pi.order_id IS NULL;

-- 4) Optionally normalize status for those already linked
UPDATE payment_intents
SET status = 'approved'
WHERE order_id IS NOT NULL AND status <> 'approved';
