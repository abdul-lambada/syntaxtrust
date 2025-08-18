-- phpMyAdmin SQL Dump
-- version 5.0.4
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 17 Agu 2025 pada 14.36
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
(1, '5 Tips Membangun Website yang Efektif untuk Bisnis Kecil', 'tips-membangun-website-bisnis-kecil', 'Konten artikel lengkap tentang tips membangun website...', 'Panduan praktis untuk membangun website yang efektif untuk bisnis kecil Anda.', 'uploads/689c927a80dd9_1755091578.jpg', 1, 'Web Development', '[\"website\", \"bisnis\", \"tips\"]', 'published', '2025-08-03 08:17:08', 0, 1, 'Tips Membangun Website Bisnis Kecil', 'Panduan praktis untuk membangun website yang efektif untuk bisnis kecil Anda.', '2025-08-03 08:17:08', '2025-08-13 13:26:18'),
(2, 'Tren Digital Marketing 2024 untuk Mahasiswa Wirausaha', 'tren-digital-marketing-2024-mahasiswa', 'Konten artikel lengkap tentang tren digital marketing...', 'Mengenal tren digital marketing terbaru yang perlu diketahui mahasiswa wirausaha.', 'uploads/689c928f99bef_1755091599.png', 1, 'Digital Marketing', '[\"marketing\", \"mahasiswa\", \"tren\"]', 'published', '2025-08-03 08:17:08', 0, 0, 'Tren Digital Marketing 2024', 'Mengenal tren digital marketing terbaru yang perlu diketahui mahasiswa wirausaha.', '2025-08-03 08:17:08', '2025-08-13 13:26:39');

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
(1, 'Toko Online ABC', 'uploads/clients/client_68a06c8426d399.08594561_1755344004.png', 'https://tokoabc.com', 'Perusahaan e-commerce yang berfokus pada produk lokal.', 'SyntaxTrust membantu kami membangun website e-commerce yang luar biasa dengan sistem pembayaran yang aman.', '4.8', 1, 1, '2025-08-03 08:17:08', '2025-08-16 11:33:24'),
(3, 'TechStartup', 'uploads/clients/client_68a06ccb5e4534.12426819_1755344075.png', 'https://techstartup.com', 'Startup teknologi dengan fokus pada solusi bisnis.', 'Desain website company profile mereka sangat modern dan profesional.', '4.7', 1, 3, '2025-08-03 08:17:08', '2025-08-16 11:34:35');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `user_id`, `service_id`, `pricing_plan_id`, `customer_name`, `customer_email`, `customer_phone`, `project_description`, `requirements`, `total_amount`, `status`, `payment_status`, `payment_method`, `start_date`, `estimated_completion`, `actual_completion`, `notes`, `created_at`, `updated_at`) VALUES
(2, 'ORD-20250814-2199', NULL, NULL, NULL, 'Abdul Kholik', 'engineertekno@gmail.com', '085156553226', 'contoh', '[]', '10000.00', 'confirmed', 'unpaid', NULL, NULL, NULL, NULL, NULL, '2025-08-14 02:01:55', '2025-08-14 02:07:15');

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
  `status` enum('completed','ongoing','planned') DEFAULT 'completed',
  `is_featured` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `portfolio`
--

INSERT INTO `portfolio` (`id`, `title`, `description`, `short_description`, `client_name`, `category`, `technologies`, `project_url`, `github_url`, `image_main`, `images`, `start_date`, `end_date`, `status`, `is_featured`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'E-commerce Website', 'Website e-commerce modern dengan payment gateway lokal dan sistem manajemen produk yang lengkap.', '', 'Toko Online ABC', 'E-commerce', '["Laravel","MySQL","Midtrans"]', 'https://example.com', '', 'uploads/portofolio/portfolio_68a06bc1e7b5c8.29379726_1755343809.jpg', '["uploads/portofolio/portfolio_68a06ba7896b29.03366533_gallery_1755343783.jpg"]', NULL, NULL, 'completed', 1, 1, '2025-08-03 08:17:08', '2025-08-16 11:30:09'),
(2, 'Mobile App Food Delivery', 'Aplikasi mobile untuk layanan food delivery dengan fitur real-time tracking.', '', 'FoodCorp', 'Mobile App', '["React Native","Firebase","Google Maps API"]', 'https://play.google.com', '', 'uploads/689c90eb584b0_1755091179.jpg', '[]', NULL, NULL, 'completed', 1, 1, '2025-08-03 08:17:08', '2025-08-13 13:19:39'),
(3, 'Company Profile Website', 'Website company profile dengan desain modern dan SEO yang baik.', '', 'TechStartup', 'Website', '["Next.js","Tailwind CSS","Vercel"]', 'https://techstartup.com', '', 'uploads/689c90f9aff00_1755091193.png', '[]', NULL, NULL, 'completed', 0, 1, '2025-08-03 08:17:08', '2025-08-13 13:19:53');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pricing_plans`
--

CREATE TABLE `pricing_plans` (
  `id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtitle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT 'IDR',
  `billing_period` enum('monthly','yearly','one_time') COLLATE utf8mb4_unicode_ci DEFAULT 'monthly',
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

INSERT INTO `pricing_plans` (`id`, `name`, `subtitle`, `price`, `currency`, `billing_period`, `description`, `features`, `delivery_time`, `technologies`, `color`, `icon`, `is_popular`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Student Basic', 'Perfect untuk tugas kuliah', '299000.00', 'IDR', 'one_time', 'Website sederhana untuk tugas mata kuliah, portfolio pribadi, dan project kecil', '[\"Desain responsive (mobile-friendly)\", \"3-5 halaman website\", \"Optimasi SEO dasar\", \"Kontak form sederhana\", \"Hosting gratis 6 bulan\", \"Domain .com gratis 1 tahun\", \"SSL Certificate\", \"1x revisi desain\", \"Training penggunaan\", \"Support WhatsApp 1 bulan\"]', '3-5 hari kerja', '[\"HTML5\", \"CSS3\", \"JavaScript\", \"Bootstrap\"]', 'bg-blue-600', 'zap', 0, 1, 1, '2025-08-03 08:17:08', '2025-08-13 13:08:58'),
(2, 'Student Pro', 'Untuk portfolio & bisnis kecil', '599000.00', 'IDR', 'one_time', 'Website lengkap untuk portfolio profesional, bisnis kecil, dan UMKM', '[\"Semua fitur Student Basic\", \"5-8 halaman website\", \"CMS untuk update konten\", \"Galeri foto & portfolio\", \"Blog/artikel system\", \"Social media integration\", \"WhatsApp Business API\", \"Google Analytics\", \"Backup otomatis\", \"3x revisi desain\", \"Support WhatsApp 3 bulan\"]', '5-7 hari kerja', '[\"Laravel\", \"MySQL\", \"Bootstrap\", \"jQuery\"]', 'bg-purple-600', 'star', 1, 1, 2, '2025-08-03 08:17:08', '2025-08-03 08:17:08'),
(3, 'Business Starter', 'Untuk UMKM & startup', '999000.00', 'IDR', 'one_time', 'Website bisnis lengkap dengan fitur e-commerce dan sistem manajemen', '[\"Semua fitur Student Pro\", \"8-12 halaman website\", \"E-commerce sederhana\", \"Payment gateway (DANA/OVO)\", \"User management system\", \"Order management\", \"Inventory system\", \"Email marketing\", \"Advanced SEO\", \"5x revisi desain\", \"Support WhatsApp 6 bulan\"]', '7-10 hari kerja', '[\"Laravel\", \"Vue.js\", \"MySQL\", \"Redis\"]', 'bg-orange-600', 'crown', 0, 1, 3, '2025-08-03 08:17:08', '2025-08-03 08:17:08'),
(4, 'Custom Project', 'Solusi khusus mahasiswa', '0.00', 'IDR', 'one_time', 'Project khusus untuk tugas akhir, penelitian, atau kebutuhan spesifik', '[\"Analisis kebutuhan mendalam\", \"Custom design & development\", \"Integrasi dengan sistem kampus\", \"API development\", \"Database design\", \"Testing & debugging\", \"Deployment & maintenance\", \"Dokumentasi lengkap\", \"Training penggunaan\", \"Support 3 bulan\"]', 'Sesuai kebutuhan', '[\"Custom\"]', 'bg-gradient-to-r from-indigo-600 to-purple-600', 'rocket', 0, 1, 4, '2025-08-03 08:17:08', '2025-08-03 08:17:08');

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
(1, 'Website Development', 'Pengembangan website profesional untuk mahasiswa dan UMKM dengan teknologi modern dan desain responsif.', 'Website modern dan responsif untuk kebutuhan Anda', 'code', NULL, '299000.00', '2-4 minggu', '["Desain Responsif", "SEO Dasar", "Integrasi CMS", "Dukungan 1 Tahun"]', 1, 1, 1, '2025-08-03 08:17:08', '2025-08-13 13:08:17'),
(2, 'Mobile App Development', 'Aplikasi mobile native dan cross-platform untuk iOS dan Android dengan performa optimal.', 'Aplikasi mobile untuk iOS dan Android', 'smartphone', NULL, '599000.00', '4-8 minggu', '["Performa Native", "Cross Platform", "Push Notification", "Publikasi App Store"]', 1, 1, 2, '2025-08-03 08:17:08', '2025-08-03 08:17:08'),
(3, 'E-commerce Solution', 'Platform e-commerce lengkap dengan payment gateway lokal dan sistem manajemen produk.', 'Platform e-commerce lengkap', 'shopping-cart', NULL, '799000.00', '6-10 minggu', '["Payment Gateway Lokal", "Manajemen Inventori", "Pelacakan Pesanan", "Dashboard Analitik"]', 1, 1, 3, '2025-08-03 08:17:08', '2025-08-03 08:17:08'),
(4, 'Digital Marketing', 'Strategi digital marketing komprehensif untuk meningkatkan brand awareness dan penjualan.', 'Strategi marketing digital yang efektif', 'trending-up', NULL, '199000.00', 'Berkelanjutan', '["Optimasi SEO", "Manajemen Social Media", "Content Marketing", "Laporan Analitik"]', 0, 1, 4, '2025-08-03 08:17:08', '2025-08-03 08:17:08');

-- --------------------------------------------------------

--
-- Struktur dari tabel `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','number','boolean','json') DEFAULT 'text',
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
(2, 'site_description', 'Layanan • Pembuatan Website untuk Mahasiswa & UMKM', 'text', 'Deskripsi website', 1, '2025-08-03 08:17:08', '2025-08-03 08:17:08'),
(3, 'contact_email', 'engineertekno@gmail.com', 'text', 'Email kontak utama', 1, '2025-08-03 08:17:08', '2025-08-16 13:29:05'),
(4, 'contact_phone', '+6285156553226', 'text', 'Nomor telepon kontak', 1, '2025-08-03 08:17:08', '2025-08-16 13:29:24'),
(5, 'address', 'Jl. Teknologi No. 123, Jakarta', 'text', 'Alamat perusahaan', 1, '2025-08-03 08:17:08', '2025-08-03 08:17:08'),
(6, 'social_media_facebook', 'https://facebook.com/syntaxtrust', 'text', 'Link Facebook', 1, '2025-08-03 08:17:08', '2025-08-03 08:17:08'),
(7, 'social_media_twitter', 'https://twitter.com/syntaxtrust', 'text', 'Link Twitter', 1, '2025-08-03 08:17:08', '2025-08-03 08:17:08'),
(8, 'social_media_instagram', 'https://instagram.com/syntaxtrust', 'text', 'Link Instagram', 1, '2025-08-03 08:17:08', '2025-08-03 08:17:08'),
(9, 'social_media_linkedin', 'https://www.linkedin.com/in/abdul-kholik-lambada/', 'text', 'Link LinkedIn', 1, '2025-08-03 08:17:08', '2025-08-16 13:31:13'),
(10, 'hero_students_count', '2', 'text', 'Jumlah mahasiswa puas di hero section', 1, '2025-08-14 11:05:58', '2025-08-14 11:05:58'),
(11, 'hero_businesses_count', '1', 'text', 'Jumlah bisnis kecil di hero section', 1, '2025-08-14 11:05:58', '2025-08-16 13:31:24'),
(12, 'hero_price_text', 'Mulai Rp 299K', 'text', 'Teks harga di hero section', 1, '2025-08-14 11:05:58', '2025-08-14 11:05:58'),
(13, 'hero_delivery_time', '3-7 Hari', 'text', 'Waktu pengerjaan di hero section', 1, '2025-08-14 11:05:58', '2025-08-14 11:05:58'),
(14, 'site_url', 'https://syntaxtrust.com', 'text', 'URL situs', 1, '2025-08-14 11:06:32', '2025-08-14 11:06:32'),
(15, 'site_logo_url', '/og-image.png', 'text', 'URL logo/OG image', 1, '2025-08-14 11:06:32', '2025-08-14 11:06:32'),
(16, 'company_whatsapp', '+6285156553226', 'text', 'Nomor WhatsApp perusahaan untuk kontak/redirect', 1, '2025-08-15 10:50:53', '2025-08-16 13:32:05');

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
(1, 'Abdul Kholik', 'Fullstack Web Developer', 'Full-stack developer dengan pengalaman 3 tahun dalam pengembangan web.', 'engineertekno@gmail.com', '085156553226', 'uploads/team/3e7c6c78606001a6_1755343956.jpg', '{"linkedin":"https://www.linkedin.com/in/abdul-kholik-lambada/","github":"https://github.com/abdul-lambada"}', '["React","Node.js","Laravel","Tailwindcss","PHP","Vue","Next"]', 3, 1, 3, '2025-08-03 08:17:08', '2025-08-16 11:32:36');

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
(1, 'Dina Marlina', 'CEO', 'Toko Online ABC', 'uploads/testimonials/testimonial_81301da66efdfe0d_1755343862.jpg', 'SyntaxTrust telah membantu kami membangun website e-commerce yang luar biasa. Prosesnya cepat dan hasilnya memuaskan.', '5.0', 'E-commerce Website', 1, 1, 1, 1, '2025-08-03 08:17:08', '2025-08-16 11:31:02'),
(2, 'Rudi Hartono', 'Marketing Director', 'FoodCorp', 'uploads/testimonials/testimonial_76ff637cf12e43f1_1755343879.png', 'Aplikasi mobile mereka sangat responsif dan mudah digunakan oleh pelanggan kami. Tim support sangat membantu.', '5.0', 'Mobile App Food Delivery', 2, 1, 1, 2, '2025-08-03 08:17:08', '2025-08-16 11:31:19'),
(3, 'Lina Kusuma', 'Founder', 'TechStartup', 'uploads/testimonials/testimonial_5a8bb5cc622cdbde_1755343893.png', 'Desain website company profile mereka sangat modern dan profesional. Sangat merekomendasikan!', '5.0', 'Company Profile Website', 1, 1, 1, 3, '2025-08-03 08:17:08', '2025-08-16 11:31:33');

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
(1, 'admin', 'admin@syntaxtrust.com', '$2y$10$Wb4CBT4YWeo0BhXdV3KSGO6NtlqtfTFEi7/SNaXmUP6dzcnvdNmGu', 'Administrator', '085156553226', 'admin', 'uploads/689c963c7371a_1755092540.jpg', '', 1, 1, '2025-08-03 08:17:08', '2025-08-13 13:42:20');

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
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `intent_number` (`intent_number`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `pricing_plan_id` (`pricing_plan_id`),
  ADD KEY `idx_intent_number` (`intent_number`),
  ADD KEY `idx_payment_intents_order_id` (`order_id`),
  ADD KEY `idx_status_pi` (`status`);

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
  ADD KEY `idx_is_active` (`is_active`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `payment_intents`
--
ALTER TABLE `payment_intents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `portfolio`
--
ALTER TABLE `portfolio`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

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
  ADD CONSTRAINT `payment_intents_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payment_intents_ibfk_2` FOREIGN KEY (`pricing_plan_id`) REFERENCES `pricing_plans` (`id`),
  ADD CONSTRAINT `fk_payment_intents_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON UPDATE CASCADE ON DELETE SET NULL;

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
