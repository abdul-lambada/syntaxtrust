-- Seed dummy data for services (audience-driven) and pricing_plans
-- Idempotent-ish: inserts only if slug/title not already present

START TRANSACTION;

-- SERVICES: Personal, UKM, Profesional
-- Personal (Landing Page)
INSERT INTO services (name, slug, icon, short_description, description, is_active, sort_order,
                      audience_enabled, audience_slug, audience_subtitle, audience_features, audience_wa_text)
SELECT 'Paket Personal (Landing Page)', 'paket-personal-landing-page', 'id-card',
       'Landing page ringkas untuk portofolio, CV, produk, atau undangan digital.',
       'Sangat cocok untuk portofolio online, CV digital, halaman perkenalan produk, atau undangan digital. Satu halaman ringkas yang memuat semua informasi penting. Catatan: Harga belum termasuk domain custom (jika diperlukan).',
       1, 1,
       1, 'personal', 'Mulai online dengan satu halaman ringkas',
       JSON_ARRAY('1 Halaman Website (One Page Website)', 'Desain Template Premium', 'Mobile-Friendly (Responsif)', 'Tombol Kontak (WhatsApp/Email)', 'Maks 3 Revisi Minor', 'Pengerjaan 1-2 Hari', 'Platform: Carrd/Bio.link/Sejenisnya'),
       'Halo, saya tertarik Paket Personal'
WHERE NOT EXISTS (
  SELECT 1 FROM services WHERE name = 'Paket Personal (Landing Page)'
);

-- UKM (Bisnis & Profil)
INSERT INTO services (name, slug, icon, short_description, description, is_active, sort_order,
                      audience_enabled, audience_slug, audience_subtitle, audience_features, audience_wa_text)
SELECT 'Paket UKM (Bisnis & Profil)', 'paket-ukm-bisnis-profil', 'store',
       'Website profil bisnis profesional 3-5 halaman.',
       'Pilihan terbaik untuk bisnis rintisan, UMKM, profil perusahaan, atau organisasi yang ingin menampilkan informasi lebih detail dan profesional. Catatan: Harga belum termasuk domain & hosting tahunan.',
       1, 2,
       1, 'ukm', 'Profil bisnis profesional dan informatif',
       JSON_ARRAY('3-5 Halaman (Beranda, Tentang, Layanan, Galeri, Kontak)', 'Template Premium (WordPress/Blogger)', 'Mobile-Friendly', 'Integrasi Media Sosial', 'Formulir Kontak', 'SEO Dasar', 'Maks 5 Revisi', 'Pengerjaan 5-7 Hari', 'Training Update Konten'),
       'Halo, saya tertarik Paket UKM'
WHERE NOT EXISTS (
  SELECT 1 FROM services WHERE name = 'Paket UKM (Bisnis & Profil)'
);

-- Profesional (Toko Online Basic)
INSERT INTO services (name, slug, icon, short_description, description, is_active, sort_order,
                      audience_enabled, audience_slug, audience_subtitle, audience_features, audience_wa_text)
SELECT 'Paket Profesional (Toko Online Basic)', 'paket-profesional-toko-online-basic', 'shopping-cart',
       'Toko online sederhana dengan katalog produk.',
       'Solusi lengkap untuk mulai berjualan online atau katalog produk profesional. Catatan: Harga belum termasuk domain & hosting tahunan, dan belum termasuk payment gateway otomatis.',
       1, 3,
       1, 'profesional', 'Mulai jualan online dengan katalog produk',
       JSON_ARRAY('Semua fitur Paket UKM', 'Hingga 8 Halaman', 'Katalog/Toko Online Sederhana', 'Upload hingga 20 produk awal', 'Beli via WhatsApp', 'Google Maps', 'Google Analytics', 'Revisi Tanpa Batas (selama pengerjaan)', 'Support 1 bulan', 'Pengerjaan 7-10 Hari'),
       'Halo, saya tertarik Paket Profesional'
WHERE NOT EXISTS (
  SELECT 1 FROM services WHERE name = 'Paket Profesional (Toko Online Basic)'
);

-- PRICING PLANS: set starting prices per service
-- Personal: 90.000 (starting)
INSERT INTO pricing_plans (service_id, name, subtitle, price, currency, billing_period, is_starting_plan, is_active, sort_order)
SELECT s.id, 'Personal Start', 'Landing Page satu halaman', 90000, 'IDR', 'one_time', 1, 1, 1
FROM services s WHERE s.name = 'Paket Personal (Landing Page)'
  AND NOT EXISTS (
    SELECT 1 FROM pricing_plans p WHERE p.service_id = s.id AND p.name = 'Personal Start'
  );

-- UKM: 750.000 (starting)
INSERT INTO pricing_plans (service_id, name, subtitle, price, currency, billing_period, is_starting_plan, is_active, sort_order)
SELECT s.id, 'UKM Start', 'Profil bisnis 3-5 halaman', 750000, 'IDR', 'one_time', 1, 1, 1
FROM services s WHERE s.name = 'Paket UKM (Bisnis & Profil)'
  AND NOT EXISTS (
    SELECT 1 FROM pricing_plans p WHERE p.service_id = s.id AND p.name = 'UKM Start'
  );

-- Profesional: 1.850.000 (starting)
INSERT INTO pricing_plans (service_id, name, subtitle, price, currency, billing_period, is_starting_plan, is_active, sort_order)
SELECT s.id, 'Profesional Start', 'Toko online basic', 1850000, 'IDR', 'one_time', 1, 1, 1
FROM services s WHERE s.name = 'Paket Profesional (Toko Online Basic)'
  AND NOT EXISTS (
    SELECT 1 FROM pricing_plans p WHERE p.service_id = s.id AND p.name = 'Profesional Start'
  );

COMMIT;
