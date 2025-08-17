-- =====================================================
-- DATABASE SYNTAXTRUST
-- Database untuk website SyntaxTrust
-- Mendukung fitur CRUD dengan PHP
-- Target: Mahasiswa dan Bisnis Kecil
-- =====================================================

-- Membuat database
CREATE DATABASE IF NOT EXISTS syntaxtrust_db;
USE syntaxtrust_db;

-- =====================================================
-- TABEL USERS (Pengguna)
-- =====================================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    user_type ENUM('mahasiswa', 'bisnis', 'admin') DEFAULT 'mahasiswa',
    profile_image VARCHAR(255),
    bio TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_user_type (user_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABEL SERVICES (Layanan)
-- =====================================================
CREATE TABLE services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    short_description VARCHAR(255),
    icon VARCHAR(100),
    image VARCHAR(255),
    price DECIMAL(10,2),
    duration VARCHAR(50),
    features JSON,
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_is_featured (is_featured),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABEL PRICING_PLANS (Paket Harga)
-- =====================================================
CREATE TABLE pricing_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    subtitle VARCHAR(255),
    price DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'IDR',
    billing_period ENUM('monthly', 'yearly', 'one_time') DEFAULT 'monthly',
    description TEXT,
    features JSON,
    delivery_time VARCHAR(50),
    technologies JSON,
    color VARCHAR(100),
    icon VARCHAR(50),
    is_popular BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_is_popular (is_popular),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABEL PORTFOLIO (Portfolio Proyek)
-- =====================================================
CREATE TABLE portfolio (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    short_description VARCHAR(255),
    client_name VARCHAR(100),
    category VARCHAR(100),
    technologies JSON,
    project_url VARCHAR(255),
    github_url VARCHAR(255),
    image_main VARCHAR(255),
    images JSON,
    start_date DATE,
    end_date DATE,
    status ENUM('completed', 'ongoing', 'planned') DEFAULT 'completed',
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_is_featured (is_featured)
);

-- =====================================================
-- TABEL TEAM (Tim)
-- =====================================================
CREATE TABLE team (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    position VARCHAR(100) NOT NULL,
    bio TEXT,
    email VARCHAR(100),
    phone VARCHAR(20),
    profile_image VARCHAR(255),
    social_links JSON,
    skills JSON,
    experience_years INT,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_is_active (is_active)
);

-- =====================================================
-- TABEL CLIENTS (Klien)
-- =====================================================
CREATE TABLE clients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    logo VARCHAR(255),
    website_url VARCHAR(255),
    description TEXT,
    testimonial TEXT,
    rating DECIMAL(2,1),
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_is_active (is_active)
);

-- =====================================================
-- TABEL CONTACT_INQUIRIES (Pesan Kontak)
-- =====================================================
CREATE TABLE contact_inquiries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    subject VARCHAR(200),
    message TEXT NOT NULL,
    service_id INT,
    budget_range VARCHAR(50),
    timeline VARCHAR(50),
    status ENUM('new', 'read', 'replied', 'closed') DEFAULT 'new',
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- =====================================================
-- TABEL ORDERS (Pesanan)
-- =====================================================
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT,
    service_id INT,
    pricing_plan_id INT,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20),
    project_description TEXT,
    requirements JSON,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('unpaid', 'paid', 'refunded') DEFAULT 'unpaid',
    payment_method VARCHAR(50),
    start_date DATE,
    estimated_completion DATE,
    actual_completion DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    FOREIGN KEY (pricing_plan_id) REFERENCES pricing_plans(id) ON DELETE SET NULL,
    INDEX idx_order_number (order_number),
    INDEX idx_status (status),
    INDEX idx_payment_status (payment_status)
);

-- =====================================================
-- TABEL BLOG_POSTS (Blog/Artikel)
-- =====================================================
CREATE TABLE blog_posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    content TEXT NOT NULL,
    excerpt VARCHAR(500),
    featured_image VARCHAR(255),
    author_id INT,
    category VARCHAR(100),
    tags JSON,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    published_at TIMESTAMP NULL,
    view_count INT DEFAULT 0,
    is_featured BOOLEAN DEFAULT FALSE,
    meta_title VARCHAR(200),
    meta_description VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_published_at (published_at)
);

-- =====================================================
-- TABEL TESTIMONIALS (Testimoni)
-- =====================================================
CREATE TABLE testimonials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_name VARCHAR(100) NOT NULL,
    client_position VARCHAR(100),
    client_company VARCHAR(100),
    client_image VARCHAR(255),
    content TEXT NOT NULL,
    rating DECIMAL(2,1),
    project_name VARCHAR(200),
    service_id INT,
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    INDEX idx_is_featured (is_featured),
    INDEX idx_is_active (is_active)
);

-- =====================================================
-- TABEL SETTINGS (Pengaturan Website)
-- =====================================================
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
    description VARCHAR(255),
    is_public BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
);

-- =====================================================
-- TABEL NOTIFICATIONS (Notifikasi)
-- =====================================================
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    related_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read)
);

-- =====================================================
-- INSERT DATA AWAL
-- =====================================================

-- Insert default admin user
INSERT INTO users (username, email, password_hash, full_name, user_type, is_active, email_verified) VALUES
('admin', 'admin@syntaxtrust.com', '$2y$10$Wb4CBT4YWeo0BhXdV3KSGO6NtlqtfTFEi7/SNaXmUP6dzcnvdNmGu', 'Administrator', 'admin', TRUE, TRUE);

-- Insert sample services
INSERT INTO services (name, description, short_description, icon, price, duration, features, is_featured, sort_order) VALUES
('Website Development', 'Pengembangan website profesional untuk bisnis Anda dengan teknologi modern dan responsive design.', 'Website modern dan responsive untuk bisnis Anda', 'code', 299000, '2-4 weeks', '["Responsive Design", "SEO Optimized", "CMS Integration", "1 Year Support"]', TRUE, 1),
('Mobile App Development', 'Aplikasi mobile native dan cross-platform untuk iOS dan Android dengan performa optimal.', 'Aplikasi mobile untuk iOS dan Android', 'smartphone', 599000, '4-8 weeks', '["Native Performance", "Cross Platform", "Push Notifications", "App Store Publishing"]', TRUE, 2),
('E-commerce Solution', 'Platform e-commerce lengkap dengan payment gateway dan sistem manajemen produk.', 'Platform e-commerce lengkap', 'shopping-cart', 799000, '6-10 weeks', '["Payment Gateway", "Inventory Management", "Order Tracking", "Analytics Dashboard"]', TRUE, 3),
('Digital Marketing', 'Strategi digital marketing komprehensif untuk meningkatkan brand awareness dan penjualan.', 'Strategi marketing digital yang efektif', 'trending-up', 199000, 'Ongoing', '["SEO Optimization", "Social Media Management", "Content Marketing", "Analytics Report"]', FALSE, 4);

-- Insert sample pricing plans
INSERT INTO pricing_plans (name, subtitle, price, billing_period, description, features, delivery_time, technologies, color, icon, is_popular, sort_order) VALUES
('Student Basic', 'Perfect untuk tugas kuliah', 299000, 'one_time', 'Website sederhana untuk tugas mata kuliah, portfolio pribadi, dan project kecil', '["Desain responsive (mobile-friendly)", "3-5 halaman website", "Optimasi SEO dasar", "Kontak form sederhana", "Hosting gratis 6 bulan", "Domain .com gratis 1 tahun", "SSL Certificate", "1x revisi desain", "Training penggunaan", "Support WhatsApp 1 bulan"]', '3-5 hari kerja', '["HTML5", "CSS3", "JavaScript", "Bootstrap"]', 'bg-blue-600', 'zap', FALSE, 1),
('Student Pro', 'Untuk portfolio & bisnis kecil', 599000, 'one_time', 'Website lengkap untuk portfolio profesional, bisnis kecil, dan UMKM', '["Semua fitur Student Basic", "5-8 halaman website", "CMS untuk update konten", "Galeri foto & portfolio", "Blog/artikel system", "Social media integration", "WhatsApp Business API", "Google Analytics", "Backup otomatis", "3x revisi desain", "Support WhatsApp 3 bulan"]', '5-7 hari kerja', '["Laravel", "MySQL", "Bootstrap", "jQuery"]', 'bg-purple-600', 'star', TRUE, 2),
('Business Starter', 'Untuk UMKM & startup', 999000, 'one_time', 'Website bisnis lengkap dengan fitur e-commerce dan sistem manajemen', '["Semua fitur Student Pro", "8-12 halaman website", "E-commerce sederhana", "Payment gateway (DANA/OVO)", "User management system", "Order management", "Inventory system", "Email marketing", "Advanced SEO", "5x revisi desain", "Support WhatsApp 6 bulan"]', '7-10 hari kerja', '["Laravel", "Vue.js", "MySQL", "Redis"]', 'bg-orange-600', 'crown', FALSE, 3),
('Custom Project', 'Solusi khusus mahasiswa', 0, 'one_time', 'Project khusus untuk tugas akhir, penelitian, atau kebutuhan spesifik', '["Analisis kebutuhan mendalam", "Custom design & development", "Integrasi dengan sistem kampus", "API development", "Database design", "Testing & debugging", "Deployment & maintenance", "Dokumentasi lengkap", "Training penggunaan", "Support 3 bulan"]', 'Sesuai kebutuhan', '["Custom"]', 'bg-gradient-to-r from-indigo-600 to-purple-600', 'rocket', FALSE, 4);

-- Insert sample portfolio
INSERT INTO portfolio (title, description, client_name, category, technologies, project_url, image_main, status, is_featured) VALUES
('E-commerce Website', 'Website e-commerce modern dengan payment gateway dan sistem manajemen produk yang lengkap.', 'Toko Online ABC', 'E-commerce', '["React", "Node.js", "MySQL", "Stripe"]', 'https://example.com', '/portfolio/ecommerce.jpg', 'completed', TRUE),
('Mobile App Food Delivery', 'Aplikasi mobile untuk layanan food delivery dengan fitur real-time tracking.', 'FoodCorp', 'Mobile App', '["React Native", "Firebase", "Google Maps API"]', 'https://play.google.com', '/portfolio/food-app.jpg', 'completed', TRUE),
('Company Profile Website', 'Website company profile dengan design modern dan SEO optimized.', 'TechStartup', 'Website', '["Next.js", "Tailwind CSS", "Vercel"]', 'https://techstartup.com', '/portfolio/company-profile.jpg', 'completed', FALSE);

-- Insert sample team
INSERT INTO team (name, position, bio, email, linkedin_url, github_url, skills, experience_years, sort_order) VALUES
('Ahmad Fauzi', 'Lead Developer', 'Full-stack developer dengan pengalaman 5 tahun dalam pengembangan web dan mobile app.', 'ahmad@syntaxtrust.com', 'https://linkedin.com/in/ahmadfauzi', 'https://github.com/ahmadfauzi', '["React", "Node.js", "Python", "Docker"]', 5, 1),
('Sarah Putri', 'UI/UX Designer', 'Designer kreatif dengan fokus pada user experience dan modern design principles.', 'sarah@syntaxtrust.com', 'https://linkedin.com/in/sarahputri', 'https://github.com/sarahputri', '["Figma", "Adobe XD", "Sketch", "Prototyping"]', 3, 2),
('Budi Santoso', 'Digital Marketing Specialist', 'Ahli marketing digital dengan track record meningkatkan brand awareness dan penjualan untuk berbagai klien.', 'budi@syntaxtrust.com', 'https://linkedin.com/in/budisantoso', 'https://github.com/budisantoso', '["SEO", "Google Ads", "Social Media", "Content Strategy"]', 4, 3);

-- Insert sample clients
INSERT INTO clients (name, logo, website_url, description, testimonial, rating, sort_order) VALUES
('Toko Online ABC', '/clients/abc-logo.png', 'https://tokoabc.com', 'Perusahaan e-commerce yang berfokus pada produk lokal.', 'SyntaxTrust membantu kami membangun website e-commerce yang luar biasa dengan sistem pembayaran yang aman.', 4.8, 1),
('FoodCorp', '/clients/foodcorp-logo.png', 'https://foodcorp.com', 'Perusahaan food delivery dengan jangkauan nasional.', 'Aplikasi mobile mereka sangat responsif dan mudah digunakan oleh pelanggan kami.', 4.9, 2),
('TechStartup', '/clients/techstartup-logo.png', 'https://techstartup.com', 'Startup teknologi dengan fokus pada solusi bisnis.', 'Desain website company profile mereka sangat modern dan profesional.', 4.7, 3);

-- Insert sample testimonials
INSERT INTO testimonials (client_name, client_position, client_company, content, rating, project_name, service_id, is_featured, sort_order) VALUES
('Dina Marlina', 'CEO', 'Toko Online ABC', 'SyntaxTrust telah membantu kami membangun website e-commerce yang luar biasa. Prosesnya cepat dan hasilnya memuaskan.', 5.0, 'E-commerce Website', 1, TRUE, 1),
('Rudi Hartono', 'Marketing Director', 'FoodCorp', 'Aplikasi mobile mereka sangat responsif dan mudah digunakan oleh pelanggan kami. Tim support sangat membantu.', 4.8, 'Mobile App Food Delivery', 2, TRUE, 2),
('Lina Kusuma', 'Founder', 'TechStartup', 'Desain website company profile mereka sangat modern dan profesional. Sangat merekomendasikan!', 4.9, 'Company Profile Website', 1, TRUE, 3);

-- Insert sample blog posts
INSERT INTO blog_posts (title, slug, content, excerpt, featured_image, author_id, category, tags, status, published_at, is_featured, meta_title, meta_description) VALUES
('5 Tips Membangun Website yang Efektif untuk Bisnis Kecil', 'tips-membangun-website-bisnis-kecil', 'Konten artikel lengkap tentang tips membangun website...', 'Panduan praktis untuk membangun website yang efektif untuk bisnis kecil Anda.', '/blog/website-tips.jpg', 1, 'Web Development', '["website", "bisnis", "tips"]', 'published', NOW(), TRUE, 'Tips Membangun Website Bisnis Kecil', 'Panduan praktis untuk membangun website yang efektif untuk bisnis kecil Anda.'),
('Tren Digital Marketing 2024 untuk Mahasiswa Wirausaha', 'tren-digital-marketing-2024-mahasiswa', 'Konten artikel lengkap tentang tren digital marketing...', 'Mengenal tren digital marketing terbaru yang perlu diketahui mahasiswa wirausaha.', '/blog/marketing-trends.jpg', 1, 'Digital Marketing', '["marketing", "mahasiswa", "tren"]', 'published', NOW(), FALSE, 'Tren Digital Marketing 2024', 'Mengenal tren digital marketing terbaru yang perlu diketahui mahasiswa wirausaha.');

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('site_name', 'SyntaxTrust', 'text', 'Nama website', TRUE),
('site_description', 'Solusi Digital untuk Mahasiswa dan Bisnis Kecil', 'text', 'Deskripsi website', TRUE),
('contact_email', 'info@syntaxtrust.com', 'text', 'Email kontak utama', TRUE),
('contact_phone', '+62 812 3456 7890', 'text', 'Nomor telepon kontak', TRUE),
('address', 'Jl. Teknologi No. 123, Jakarta', 'text', 'Alamat perusahaan', TRUE),
('social_media_facebook', 'https://facebook.com/syntaxtrust', 'text', 'Link Facebook', TRUE),
('social_media_twitter', 'https://twitter.com/syntaxtrust', 'text', 'Link Twitter', TRUE),
('social_media_instagram', 'https://instagram.com/syntaxtrust', 'text', 'Link Instagram', TRUE),
('social_media_linkedin', 'https://linkedin.com/company/syntaxtrust', 'text', 'Link LinkedIn', TRUE);

-- =====================================================
-- TABEL PAYMENT_INTENTS (Niat Pembayaran Publik)
-- =====================================================
CREATE TABLE payment_intents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    intent_number VARCHAR(50) UNIQUE NOT NULL,
    service_id INT NULL,
    pricing_plan_id INT NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20),
    amount DECIMAL(10,2) NULL,
    notes TEXT,
    payment_proof_path VARCHAR(255),
    status ENUM('submitted','reviewed','approved','rejected') DEFAULT 'submitted',
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    FOREIGN KEY (pricing_plan_id) REFERENCES pricing_plans(id) ON DELETE RESTRICT,
    INDEX idx_intent_number (intent_number),
    INDEX idx_status_pi (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tambah default WhatsApp number setting jika belum ada
INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('company_whatsapp', '+6281234567890', 'text', 'Nomor WhatsApp perusahaan untuk kontak/redirect', TRUE);