-- Migration: Add order_id to payment_intents and backfill
-- Created: 2025-08-15 19:31:00+07:00

-- 1) Add nullable order_id + index (safe to re-run with IF NOT EXISTS guards where supported)
ALTER TABLE payment_intents
  ADD COLUMN IF NOT EXISTS order_id INT UNSIGNED NULL AFTER pricing_plan_id,
  ADD INDEX IF NOT EXISTS idx_payment_intents_order_id (order_id);

-- 2) Add FK (may fail if engine/signedness mismatched; re-run after aligning)
ALTER TABLE payment_intents
  ADD CONSTRAINT fk_payment_intents_order
  FOREIGN KEY (order_id) REFERENCES orders(id)
  ON UPDATE CASCADE ON DELETE SET NULL;

-- 3) Backfill order_id using intent_number in orders.project_description
UPDATE payment_intents pi
JOIN orders o ON o.project_description LIKE CONCAT('%', pi.intent_number, '%')
SET pi.order_id = o.id
WHERE pi.order_id IS NULL;

-- 4) Optionally normalize status for those already linked
UPDATE payment_intents
SET status = 'approved'
WHERE order_id IS NOT NULL AND status <> 'approved';
