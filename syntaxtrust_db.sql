-- phpMyAdmin SQL Dump
-- version 5.0.4
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 26 Sep 2025 pada 09.21
-- Versi server: 10.4.17-MariaDB
-- Versi PHP: 8.0.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `syntaxtrust_db`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `blog_posts`
--

CREATE TABLE `blog_posts` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `excerpt` varchar(500) DEFAULT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `author_id` int(11) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `published_at` timestamp NULL DEFAULT NULL,
  `view_count` int(11) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `meta_title` varchar(200) DEFAULT NULL,
  `meta_description` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `blog_posts`
--

INSERT INTO `blog_posts` (`id`, `title`, `slug`, `content`, `excerpt`, `featured_image`, `author_id`, `category`, `tags`, `status`, `published_at`, `view_count`, `is_featured`, `meta_title`, `meta_description`, `created_at`, `updated_at`) VALUES
(1, '5 Tips Membangun Website yang Efektif untuk Bisnis Kecil', 'tips-membangun-website-bisnis-kecil', 'Konten artikel lengkap tentang tips membangun website...', 'Panduan praktis untuk membangun website yang efektif untuk bisnis kecil Anda.', 'uploads/blog/post_20250818_093339_556b4e8e.jpg', 1, 'Web Development', '[\"website\",\"bisnis\",\"tips\"]', 'published', '2025-08-03 08:17:08', 2, 1, 'Tips Membangun Website Bisnis Kecil', 'Panduan praktis untuk membangun website yang efektif untuk bisnis kecil Anda.', '2025-08-03 08:17:08', '2025-08-21 16:42:48'),
(2, 'Tren Digital Marketing 2024 untuk Mahasiswa Wirausaha', 'tren-digital-marketing-2024-mahasiswa', 'Konten artikel lengkap tentang tren digital marketing...', 'Mengenal tren digital marketing terbaru yang perlu diketahui mahasiswa wirausaha.', 'uploads/blog/post_20250818_093402_a111b9c7.png', 1, 'Digital Marketing', '[\"marketing\",\"mahasiswa\",\"tren\"]', 'published', '2025-08-03 08:17:08', 6, 0, 'Tren Digital Marketing 2024', 'Mengenal tren digital marketing terbaru yang perlu diketahui mahasiswa wirausaha.', '2025-08-03 08:17:08', '2025-08-20 11:51:11');

-- --------------------------------------------------------

--
-- Struktur dari tabel `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `website_url` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `testimonial` text DEFAULT NULL,
  `rating` decimal(2,1) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `clients`
--

INSERT INTO `clients` (`id`, `name`, `logo`, `website_url`, `description`, `testimonial`, `rating`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'PJM', 'uploads/clients\\client_68a2768f921819.96671969_1755477647.png', 'https://pjm.com', 'Perusahaan kontruksi yang berfokus pada alat berat dan bahan bangunan.', 'SyntaxTrust membantu kami membangun website company profile yang luar biasa dengan sistem yang  sangat responsive dan modern.', '5.0', 1, 2, '2025-08-03 08:17:08', '2025-08-18 08:20:01'),
(3, 'Sistem Manajemen Bengkel & Suku Cadang', 'uploads/clients\\client_68a276b5774d83.80587018_1755477685.png', 'https://sukucadang.com', 'Sistem Manajemen Bengkel & Suku Cadang', 'Sistem Manajemen Bengkel & Suku Cadang yang sangat lengkap dan mudah digunakan', '5.0', 1, 3, '2025-08-03 08:17:08', '2025-08-18 08:19:49');

-- --------------------------------------------------------

--
-- Struktur dari tabel `contact_inquiries`
--

CREATE TABLE `contact_inquiries` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `message` text NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `budget_range` varchar(50) DEFAULT NULL,
  `timeline` varchar(50) DEFAULT NULL,
  `status` enum('new','read','replied','closed') DEFAULT 'new',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `contact_inquiries`
--

INSERT INTO `contact_inquiries` (`id`, `name`, `email`, `phone`, `subject`, `message`, `service_id`, `budget_range`, `timeline`, `status`, `ip_address`, `user_agent`, `created_at`, `updated_at`) VALUES
(1, 'Abdul Kholik', 'engineertekno@gmail.com', '085156553226', 'Hubungi', 'Contact Inquiry', NULL, NULL, NULL, 'replied', NULL, NULL, '2025-08-14 02:33:25', '2025-08-14 02:37:54');

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `related_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `related_url`, `created_at`) VALUES
(1, 1, 'Order status updated', 'Order ORD-20250821-3817 status pending -> completed.', 'success', 1, 'manage_orders.php?search=ORD-20250821-3817', '2025-08-21 11:30:44'),
(2, 1, 'Order status updated', 'Order ORD-20250821-3817 status completed -> completed.', 'success', 1, 'manage_orders.php?search=ORD-20250821-3817', '2025-08-21 12:47:03'),
(3, 1, 'Order status updated', 'Order ORD-20250821-3817 status completed -> completed.', 'success', 1, 'manage_orders.php?search=ORD-20250821-3817', '2025-08-21 12:47:07'),
(4, 1, 'Order status updated', 'Order ORD-20250821-6665 status pending -> confirmed.', 'info', 1, 'manage_orders.php?search=ORD-20250821-6665', '2025-08-21 12:48:38'),
(5, 1, 'Order status updated', 'Order ORD-20250821-6665 status confirmed -> confirmed.', 'info', 1, 'manage_orders.php?search=ORD-20250821-6665', '2025-08-21 12:48:54'),
(6, 1, 'Order status updated', 'Order ORD-20250821-6665 status confirmed -> completed.', 'success', 1, 'manage_orders.php?search=ORD-20250821-6665', '2025-08-21 13:22:39');

-- --------------------------------------------------------

--
-- Struktur dari tabel `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `pricing_plan_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_email` varchar(100) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `project_description` text DEFAULT NULL,
  `requirements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`requirements`)),
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','in_progress','completed','cancelled') DEFAULT 'pending',
  `payment_status` enum('unpaid','paid','refunded') DEFAULT 'unpaid',
  `payment_method` varchar(50) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `estimated_completion` date DEFAULT NULL,
  `actual_completion` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `user_id`, `service_id`, `pricing_plan_id`, `customer_name`, `customer_email`, `customer_phone`, `project_description`, `requirements`, `total_amount`, `status`, `payment_status`, `payment_method`, `start_date`, `estimated_completion`, `actual_completion`, `notes`, `admin_notes`, `created_at`, `updated_at`) VALUES
(3, 'ORD-20250821-3817', NULL, 1, NULL, 'Abdul Kholik', 'engineertekno@gmail.com', '085156553226', 'contihhh', '\"\\\"\\\\\\\"contoh saja\\\\\\\"\\\"\"', '299000.00', 'completed', 'paid', NULL, NULL, NULL, NULL, NULL, '', '2025-08-21 11:23:12', '2025-08-21 13:19:49'),
(4, 'ORD-20250821-6665', NULL, 1, NULL, 'Abdul Kholik', 'engineertekno@gmail.com', '085156553226', 'contohhh', '[]', '2500000.00', 'completed', 'paid', NULL, NULL, NULL, NULL, NULL, '', '2025-08-21 11:58:47', '2025-08-21 13:22:39'),
(5, 'ORD-20250821-1903', NULL, 1, NULL, 'Abdul Kholik', 'engineertekno@gmail.com', '085156553226', 'contoh lagi', '\"\\\"contoh aja\\\"\"', '999000.00', 'in_progress', 'paid', NULL, NULL, NULL, NULL, NULL, '', '2025-08-21 14:00:07', '2025-09-26 06:22:15'),
(6, 'ORD-20250926-0981', NULL, 1, 1, 'Abdul Kholik', 'engineertekno@gmail.com', '085156553226', 'Test', '\"Test\"', '299000.00', 'pending', 'unpaid', NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 05:56:31', '2025-09-26 05:56:31');

-- --------------------------------------------------------

--
-- Struktur dari tabel `payment_intents`
--

CREATE TABLE `payment_intents` (
  `id` int(11) NOT NULL,
  `intent_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `pricing_plan_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_proof_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('submitted','reviewed','approved','rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'submitted',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `portfolio`
--

CREATE TABLE `portfolio` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `short_description` varchar(255) DEFAULT NULL,
  `client_name` varchar(100) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `technologies` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`technologies`)),
  `project_url` varchar(255) DEFAULT NULL,
  `github_url` varchar(255) DEFAULT NULL,
  `image_main` varchar(255) DEFAULT NULL,
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`images`)),
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('completed','ongoing','upcoming') DEFAULT 'completed',
  `is_featured` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `portfolio`
--

INSERT INTO `portfolio` (`id`, `title`, `description`, `short_description`, `client_name`, `category`, `technologies`, `project_url`, `github_url`, `image_main`, `images`, `start_date`, `end_date`, `status`, `is_featured`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'E-commerce Website', 'Website e-commerce modern dengan payment gateway lokal dan sistem manajemen produk yang lengkap.', '', 'Toko Online ABC', 'E-commerce', '[\"Laravel\",\"MySQL\",\"Midtrans\"]', 'https://example.com', '', 'uploads/portofolio\\portfolio_68a202c859eed1.67628315_1755448008.jpg', '[\"uploads\\/portofolio\\/portfolio_68a06ba7896b29.03366533_gallery_1755343783.jpg\",\"uploads\\/portofolio\\/portfolio_68a2df1608ace6.24990459_gallery_1755504406.jpg\"]', NULL, NULL, 'completed', 1, 1, '2025-08-03 08:17:08', '2025-08-18 08:06:46'),
(2, 'Mobile App Food Delivery', 'Aplikasi mobile untuk layanan food delivery dengan fitur real-time tracking.', '', 'FoodCorp', 'Mobile App', '[\"React Native\",\"Firebase\",\"Google Maps API\"]', 'https://play.google.com', '', 'uploads/portofolio\\portfolio_68a276293c16c8.50463381_1755477545.jpg', '[\"uploads\\/portofolio\\\\portfolio_68a276293e80f5.55023062_gallery_1755477545.jpg\"]', NULL, NULL, 'completed', 1, 1, '2025-08-03 08:17:08', '2025-08-18 00:39:05'),
(3, 'Company Profile Website', 'Website company profile dengan desain modern dan SEO yang baik.', '', 'TechStartup', 'Website', '[\"Next.js\",\"Tailwind CSS\",\"Vercel\"]', 'https://techstartup.com', '', 'uploads/portofolio\\portfolio_68a276497d91c1.30716777_1755477577.png', '[\"uploads\\/portofolio\\\\portfolio_68a276498073c8.66600800_gallery_1755477577.png\"]', NULL, NULL, 'completed', 0, 1, '2025-08-03 08:17:08', '2025-08-18 00:39:37');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pricing_plans`
--

CREATE TABLE `pricing_plans` (
  `id` int(11) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtitle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT 'IDR',
  `billing_period` enum('monthly','quarterly','yearly','one_time') COLLATE utf8mb4_unicode_ci DEFAULT 'one_time',
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `features` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL CHECK (json_valid(`features`)),
  `delivery_time` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `technologies` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL CHECK (json_valid(`technologies`)),
  `color` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_popular` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `pricing_plans`
--

INSERT INTO `pricing_plans` (`id`, `service_id`, `name`, `subtitle`, `price`, `currency`, `billing_period`, `description`, `features`, `delivery_time`, `technologies`, `color`, `icon`, `is_popular`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 1, 'Student Basic', 'Perfect untuk tugas kuliah', '299000.00', 'IDR', 'one_time', 'Website sederhana untuk tugas mata kuliah, portfolio pribadi, dan project kecil', '[\"Desain responsive (mobile-friendly)\",\"3-5 halaman website\",\"Optimasi SEO dasar\",\"Kontak form sederhana\",\"Training penggunaan\",\"Support WhatsApp 1 bulan\"]', '3-5 hari kerja', '[\"HTML5\",\"CSS3\",\"JavaScript\",\"Bootstrap\"]', '#000000', 'zap', 0, 1, 1, '2025-08-03 08:17:08', '2025-08-20 12:33:51'),
(2, 1, 'Student Pro', 'Untuk portfolio & bisnis kecil', '599000.00', 'IDR', 'one_time', 'Website lengkap untuk portfolio profesional, bisnis kecil, dan UMKM', '[\"Semua fitur Student Basic\",\"5-8 halaman website\",\"CMS untuk update konten\",\"Galeri foto & portfolio\",\"Blog\\/artikel system\",\"Social media integration\",\"WhatsApp Business API\",\"Google Analytics\",\"Backup otomatis\",\"3x revisi desain\",\"Support WhatsApp 3 bulan\"]', '5-7 hari kerja', '[\"Laravel\",\"MySQL\",\"Bootstrap\",\"jQuery\"]', '#000000', 'star', 1, 1, 2, '2025-08-03 08:17:08', '2025-08-20 12:34:20'),
(3, 1, 'Business Starter', 'Untuk UMKM & startup', '999000.00', 'IDR', 'one_time', 'Website bisnis lengkap dengan fitur e-commerce dan sistem manajemen', '[\"Semua fitur Student Pro\", \"8-12 halaman website\", \"E-commerce sederhana\", \"Payment gateway (DANA/OVO)\", \"User management system\", \"Order management\", \"Inventory system\", \"Email marketing\", \"Advanced SEO\", \"5x revisi desain\", \"Support WhatsApp 6 bulan\"]', '7-10 hari kerja', '[\"Laravel\", \"Vue.js\", \"MySQL\", \"Redis\"]', 'bg-orange-600', 'crown', 0, 1, 3, '2025-08-03 08:17:08', '2025-08-20 12:23:12'),
(4, 1, 'Custom Project', 'Solusi khusus mahasiswa', '0.00', 'IDR', 'one_time', 'Project khusus untuk tugas akhir, penelitian, atau kebutuhan spesifik', '[\"Analisis kebutuhan mendalam\", \"Custom design & development\", \"Integrasi dengan sistem kampus\", \"API development\", \"Database design\", \"Testing & debugging\", \"Deployment & maintenance\", \"Dokumentasi lengkap\", \"Training penggunaan\", \"Support 3 bulan\"]', 'Sesuai kebutuhan', '[\"Custom\"]', 'bg-gradient-to-r from-indigo-600 to-purple-600', 'rocket', 0, 1, 4, '2025-08-03 08:17:08', '2025-08-20 12:23:12');

-- --------------------------------------------------------

--
-- Struktur dari tabel `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `selector` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `validator_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `duration` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `features` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL CHECK (json_valid(`features`)),
  `is_featured` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `services`
--

INSERT INTO `services` (`id`, `name`, `description`, `short_description`, `icon`, `image`, `price`, `duration`, `features`, `is_featured`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Website Development', 'Pengembangan website profesional untuk mahasiswa dan UMKM dengan teknologi modern dan desain responsif.', 'Website modern dan responsif untuk kebutuhan Anda', 'code', NULL, '299000.00', '2-4 minggu', '[\"Desain Responsif\", \"SEO Dasar\", \"Integrasi CMS\", \"Dukungan 1 Tahun\"]', 1, 1, 1, '2025-08-03 08:17:08', '2025-08-13 13:08:17'),
(3, 'E-commerce Solution', 'Platform e-commerce lengkap dengan payment gateway lokal dan sistem manajemen produk.', 'Platform e-commerce lengkap', 'shopping-cart', NULL, '799000.00', '6-10 minggu', '[\"Payment Gateway Lokal\", \"Manajemen Inventori\", \"Pelacakan Pesanan\", \"Dashboard Analitik\"]', 1, 1, 3, '2025-08-03 08:17:08', '2025-08-03 08:17:08'),
(4, 'Digital Marketing', 'Strategi digital marketing komprehensif untuk meningkatkan brand awareness dan penjualan.', 'Strategi marketing digital yang efektif', 'chart-line', NULL, '199000.00', 'Berkelanjutan', '[\"Optimasi SEO\",\"Manajemen Social Media\",\"Content Marketing\",\"Laporan Analitik\"]', 0, 1, 4, '2025-08-03 08:17:08', '2025-08-18 07:53:42');

-- --------------------------------------------------------

--
-- Struktur dari tabel `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','number','boolean','json','image') DEFAULT 'text',
  `description` varchar(255) DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `is_public`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'SyntaxTrust', 'text', 'Nama website', 1, '2025-08-03 08:17:08', '2025-08-03 08:17:08'),
(2, 'site_description', 'Jasa pembuatan website profesional, cepat, dan terjangkau.', '', 'Deskripsi singkat situs untuk SEO.', 1, '2025-08-03 08:17:08', '2025-09-26 06:01:40'),
(3, 'contact_email', 'engineertekno@gmail.com', 'text', 'Email kontak utama', 1, '2025-08-03 08:17:08', '2025-08-16 13:29:05'),
(4, 'contact_phone', '+6285156553226', 'text', 'Nomor telepon kontak', 1, '2025-08-03 08:17:08', '2025-08-16 13:29:24'),
(5, 'address', 'Jl. Cibiru No.01, Sangkanhurip, Kec. Sindang, Kabupaten Majalengka, Jawa Barat 45471', 'text', 'Alamat perusahaan', 1, '2025-08-03 08:17:08', '2025-08-18 00:42:38'),
(6, 'social_media_facebook', 'https://facebook.com/syntaxtrust', 'text', 'Link Facebook', 1, '2025-08-03 08:17:08', '2025-08-03 08:17:08'),
(7, 'social_media_twitter', 'https://twitter.com/syntaxtrust', 'text', 'Link Twitter', 1, '2025-08-03 08:17:08', '2025-08-03 08:17:08'),
(8, 'social_media_instagram', 'https://instagram.com/syntaxtrust', 'text', 'Link Instagram', 1, '2025-08-03 08:17:08', '2025-08-03 08:17:08'),
(9, 'social_media_linkedin', 'https://www.linkedin.com/in/abdul-kholik-lambada/', 'text', 'Link LinkedIn', 1, '2025-08-03 08:17:08', '2025-08-16 13:31:13'),
(10, 'hero_students_count', '200+', 'text', 'Jumlah siswa/mahasiswa yang dilayani (teks tampilan).', 1, '2025-08-14 11:05:58', '2025-09-26 06:01:39'),
(11, 'hero_businesses_count', '80+', 'text', 'Jumlah bisnis/UMKM yang dibantu (teks tampilan).', 1, '2025-08-14 11:05:58', '2025-09-26 06:01:39'),
(12, 'hero_price_text', 'Mulai Rp 299K', 'text', 'Teks harga pada hero.', 1, '2025-08-14 11:05:58', '2025-09-26 06:01:39'),
(13, 'hero_delivery_time', '3-7 Hari Selesai', 'text', 'Estimasi waktu pengerjaan pada hero.', 1, '2025-08-14 11:05:58', '2025-09-26 06:01:39'),
(14, 'site_url', 'http://localhost/company_profile_syntaxtrust', 'text', 'Base URL situs untuk canonical/og:url/sitemap.', 1, '2025-08-14 11:06:32', '2025-09-26 06:01:39'),
(15, 'site_logo_url', '', 'text', 'URL logo situs untuk header/SEO.', 1, '2025-08-14 11:06:32', '2025-09-26 06:01:39'),
(16, 'company_whatsapp', '+6285156553226', 'text', 'Nomor WhatsApp perusahaan untuk kontak/redirect', 1, '2025-08-15 10:50:53', '2025-08-16 13:32:05'),
(17, 'company_name', 'SyntaxTrust', 'text', 'Nama perusahaan yang ditampilkan di UI.', 1, '2025-09-26 06:01:39', '2025-09-26 06:01:39'),
(18, 'company_address', '', '', 'Alamat perusahaan untuk footer/kontak.', 1, '2025-09-26 06:01:39', '2025-09-26 06:01:39'),
(19, 'company_email', '', 'text', 'Email kontak perusahaan.', 1, '2025-09-26 06:01:39', '2025-09-26 06:01:39'),
(20, 'company_phone', '', 'text', 'Nomor telepon/WA perusahaan.', 1, '2025-09-26 06:01:39', '2025-09-26 06:01:39'),
(21, 'social_facebook', '', 'text', 'URL Facebook resmi.', 1, '2025-09-26 06:01:40', '2025-09-26 06:01:40'),
(22, 'social_twitter', '', 'text', 'URL Twitter/X resmi.', 1, '2025-09-26 06:01:40', '2025-09-26 06:01:40'),
(23, 'social_instagram', '', 'text', 'URL Instagram resmi.', 1, '2025-09-26 06:01:40', '2025-09-26 06:01:40'),
(24, 'social_linkedin', '', 'text', 'URL LinkedIn resmi.', 1, '2025-09-26 06:01:40', '2025-09-26 06:01:40'),
(25, 'analytics_script_url', '', 'text', 'URL script analytics (contoh: Plausible/GA).', 0, '2025-09-26 06:01:40', '2025-09-26 06:01:40'),
(26, 'analytics_script_inline', '', '', 'Script analytics inline (opsional).', 0, '2025-09-26 06:01:40', '2025-09-26 06:01:40'),
(27, 'fonnte_token', '', 'text', 'Fonnte API Token (PRIVATE) untuk pengiriman WhatsApp otomatis.', 0, '2025-09-26 06:01:40', '2025-09-26 06:01:40');

-- --------------------------------------------------------

--
-- Struktur dari tabel `team`
--

CREATE TABLE `team` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `position` varchar(100) NOT NULL,
  `bio` text DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `social_links` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`social_links`)),
  `skills` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`skills`)),
  `experience_years` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `team`
--

INSERT INTO `team` (`id`, `name`, `position`, `bio`, `email`, `phone`, `profile_image`, `social_links`, `skills`, `experience_years`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Abdul Kholik', 'Fullstack Web Developer', 'Full-stack developer dengan pengalaman 3 tahun dalam pengembangan web.', 'engineertekno@gmail.com', '085156553226', 'uploads/team\\team_68a1fc1c1af652.99212769_1755446300.jpg', '{\"linkedin\":\"https://www.linkedin.com/in/abdul-kholik-lambada/\",\"github\":\"https://github.com/abdul-lambada\"}', '[\"React\",\"Node.js\",\"Laravel\",\"Tailwindcss\",\"PHP\",\"Vue\",\"Nuxt\"]', 3, 1, 3, '2025-08-03 08:17:08', '2025-08-18 07:42:58');

-- --------------------------------------------------------

--
-- Struktur dari tabel `testimonials`
--

CREATE TABLE `testimonials` (
  `id` int(11) NOT NULL,
  `client_name` varchar(100) NOT NULL,
  `client_position` varchar(100) DEFAULT NULL,
  `client_company` varchar(100) DEFAULT NULL,
  `client_image` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `rating` decimal(2,1) DEFAULT NULL,
  `project_name` varchar(200) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `testimonials`
--

INSERT INTO `testimonials` (`id`, `client_name`, `client_position`, `client_company`, `client_image`, `content`, `rating`, `project_name`, `service_id`, `is_featured`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Cahya', 'Karyawan', 'PJM', 'uploads/testimonials\\testimonial_68a1fa1e6c1f57.32180623_1755445790.png', 'SyntaxTrust telah membantu kami membangun website company profile yang luar biasa. Prosesnya cepat dan hasilnya memuaskan.', '5.0', 'PJM Website', 1, 1, 1, 1, '2025-08-03 08:17:08', '2025-08-18 08:12:47'),
(2, 'Aldi', 'Mahasiswa', 'Sistem Manajemen Bengkel & Suku Cadang', 'uploads/testimonials\\testimonial_68a1fa69246be4.59384002_1755445865.png', 'webiste sangat responsif dan mudah digunakan oleh kami dan dosen kami karena webiste ini untuk kebutuhan tugas mata kuliah. Tim support sangat membantu.', '5.0', 'Tugas Mata Kuliah', NULL, 1, 1, 2, '2025-08-03 08:17:08', '2025-08-18 08:15:54');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `user_type` enum('mahasiswa','bisnis','admin') DEFAULT 'mahasiswa',
  `profile_image` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `email_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `phone`, `user_type`, `profile_image`, `bio`, `is_active`, `email_verified`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@syntaxtrust.com', '$2y$10$Wb4CBT4YWeo0BhXdV3KSGO6NtlqtfTFEi7/SNaXmUP6dzcnvdNmGu', 'Administrator', '085156553226', 'admin', 'uploads/users\\user_68a276c4b16831.06118569_1755477700.png', '', 1, 1, '2025-08-03 08:17:08', '2025-08-18 00:41:40');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `author_id` (`author_id`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_published_at` (`published_at`);

--
-- Indeks untuk tabel `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indeks untuk tabel `contact_inquiries`
--
ALTER TABLE `contact_inquiries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indeks untuk tabel `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_read` (`is_read`);

--
-- Indeks untuk tabel `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `pricing_plan_id` (`pricing_plan_id`),
  ADD KEY `idx_order_number` (`order_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_payment_status` (`payment_status`);

--
-- Indeks untuk tabel `payment_intents`
--
ALTER TABLE `payment_intents`
  ADD KEY `idx_payment_intents_order_id` (`order_id`);

--
-- Indeks untuk tabel `portfolio`
--
ALTER TABLE `portfolio`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_is_featured` (`is_featured`);

--
-- Indeks untuk tabel `pricing_plans`
--
ALTER TABLE `pricing_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_is_popular` (`is_popular`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_service_id` (`service_id`);

--
-- Indeks untuk tabel `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `selector` (`selector`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indeks untuk tabel `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_is_featured` (`is_featured`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indeks untuk tabel `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_setting_key` (`setting_key`);

--
-- Indeks untuk tabel `team`
--
ALTER TABLE `team`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indeks untuk tabel `testimonials`
--
ALTER TABLE `testimonials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `idx_is_featured` (`is_featured`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_user_type` (`user_type`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `blog_posts`
--
ALTER TABLE `blog_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `contact_inquiries`
--
ALTER TABLE `contact_inquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `portfolio`
--
ALTER TABLE `portfolio`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT untuk tabel `team`
--
ALTER TABLE `team`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD CONSTRAINT `blog_posts_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `contact_inquiries`
--
ALTER TABLE `contact_inquiries`
  ADD CONSTRAINT `contact_inquiries_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`pricing_plan_id`) REFERENCES `pricing_plans` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `payment_intents`
--
ALTER TABLE `payment_intents`
  ADD CONSTRAINT `fk_payment_intents_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pi_order_id` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pricing_plans`
--
ALTER TABLE `pricing_plans`
  ADD CONSTRAINT `fk_pricing_plans_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD CONSTRAINT `fk_rt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `testimonials`
--
ALTER TABLE `testimonials`
  ADD CONSTRAINT `testimonials_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
