-- Seed dummy data for services (audience-driven) and pricing_plans
-- Idempotent-ish: inserts only if slug/title not already present

START TRANSACTION;

-- SERVICES: Mahasiswa CRUD dan Mahasiswa & Skripsi Custom (tanpa hosting)
-- Paket Mahasiswa CRUD (Tugas Mata Kuliah CRUD Biasa)
INSERT INTO services (id, name, icon, short_description, description, is_active, sort_order,
                      audience_enabled, audience_slug, audience_subtitle, audience_features, audience_wa_text)
SELECT (SELECT COALESCE(MAX(id),0)+1 FROM services), 'Paket Mahasiswa CRUD', 'book',
       'Paket pengerjaan tugas CRUD dasar untuk mata kuliah pemrograman.',
       'Fokus pada aplikasi CRUD sederhana (Create, Read, Update, Delete) sesuai kebutuhan tugas mata kuliah. Tidak termasuk hosting. Jika dibutuhkan hosting untuk presentasi/penilaian, dapat dikonsultasikan.',
       1, 1,
       1, 'mahasiswa-crud', 'Selesaikan tugas CRUD dengan rapi dan tepat waktu',
       '["Aplikasi CRUD dasar (Create, Read, Update, Delete)", "Struktur projek rapi & readable", "Database schema sederhana (MySQL)", "Dokumentasi singkat cara run", "Revisi minor sesuai feedback dosen"]',
       'Halo, saya tertarik Paket Mahasiswa CRUD'
WHERE NOT EXISTS (
  SELECT 1 FROM services WHERE name = 'Paket Mahasiswa CRUD'
);

-- Paket Mahasiswa & Skripsi Custom (Sistem/Source Code Sendiri)
INSERT INTO services (id, name, icon, short_description, description, is_active, sort_order,
                      audience_enabled, audience_slug, audience_subtitle, audience_features, audience_wa_text)
SELECT (SELECT COALESCE(MAX(id),0)+1 FROM services), 'Paket Mahasiswa & Skripsi Custom', 'graduation-cap',
       'Pengembangan/penyempurnaan sistem tugas/skripsi custom sesuai kebutuhan.',
       'Bisa dari sistem/source code Anda untuk diperbaiki/ditambah fitur, atau full build dari nol hingga siap sidang. Tidak termasuk hosting. Jika ingin dihosting untuk demo/penilaian, bisa dikonsultasikan.',
       1, 2,
       1, 'skripsi-custom', 'Sistem skripsi sesuai proposal dan bimbingan',
       '["Analisis kebutuhan & rancangan fitur", "Perbaikan/penambahan fitur pada source code Anda", "Atau full build dari awal hingga selesai", "Integrasi komponen (Auth, CRUD lanjutan, laporan)", "Pendampingan setup dan demo"]',
       'Halo, saya tertarik Paket Skripsi Custom'
WHERE NOT EXISTS (
  SELECT 1 FROM services WHERE name = 'Paket Mahasiswa & Skripsi Custom'
);

-- PRICING PLANS: starting prices for student packages
-- Mahasiswa CRUD: mulai 150.000 (one-time)
INSERT INTO pricing_plans (id, service_id, name, subtitle, price, currency, billing_period, is_starting_plan, is_active, sort_order)
SELECT (SELECT COALESCE(MAX(id),0)+1 FROM pricing_plans), s.id, 'Mahasiswa CRUD Start', 'Tugas mata kuliah CRUD dasar', 150000, 'IDR', 'one_time', 1, 1, 1
FROM services s WHERE s.name = 'Paket Mahasiswa CRUD'
  AND NOT EXISTS (
    SELECT 1 FROM pricing_plans p WHERE p.service_id = s.id AND p.name = 'Mahasiswa CRUD Start'
  );

-- Mahasiswa & Skripsi Custom: mulai 2.500.000 (one-time)
INSERT INTO pricing_plans (id, service_id, name, subtitle, price, currency, billing_period, is_starting_plan, is_active, sort_order)
SELECT (SELECT COALESCE(MAX(id),0)+1 FROM pricing_plans), s.id, 'Skripsi Custom Start', 'Pengembangan/penyempurnaan sistem skripsi', 2500000, 'IDR', 'one_time', 1, 1, 1
FROM services s WHERE s.name = 'Paket Mahasiswa & Skripsi Custom'
  AND NOT EXISTS (
    SELECT 1 FROM pricing_plans p WHERE p.service_id = s.id AND p.name = 'Skripsi Custom Start'
  );

COMMIT;
