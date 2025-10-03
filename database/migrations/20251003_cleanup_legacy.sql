-- Cleanup Legacy Fields/Entries to align with Option B
-- Safe and idempotent. Run in the same DB as the app.

START TRANSACTION;

-- 1) Remove legacy settings key used by Option A
-- We no longer use `settings.setting_key = 'audience_offerings'` for public UI.
DELETE FROM settings WHERE setting_key = 'audience_offerings';

-- 2) Optionally ensure no leftover invalid audience slugs (normalize to NULL if empty string)
UPDATE services SET audience_slug = NULL WHERE audience_slug = '';

-- 3) Optionally ensure audience_features is NULL when empty string
UPDATE services SET audience_features = NULL WHERE audience_features = '';

COMMIT;
