<?php
// Contact form submission handler
// Security: CSRF validation, input validation, prepared statements, simple redirects

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

function redirect_with($params = []) {
    // Redirect to standalone Contact page
    $base = '/syntaxtrust/public/contact.php';
    if (!empty($params)) {
        $q = http_build_query($params);
        $base .= ($q ? ('?' . $q) : '');
    }
    header('Location: ' . $base);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect_with(['error' => 'Metode tidak diizinkan']);
}

// CSRF check
$csrf = $_POST['csrf_contact'] ?? '';
if (empty($csrf) || empty($_SESSION['csrf_contact']) || !hash_equals($_SESSION['csrf_contact'], $csrf)) {
    redirect_with(['error' => 'Sesi tidak valid. Muat ulang halaman.']);
}
// Single-use token
unset($_SESSION['csrf_contact']);

// Collect and validate inputs
$name = trim((string)($_POST['name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));
// Optional fields
$phone = trim((string)($_POST['phone'] ?? ''));
$subject = trim((string)($_POST['subject'] ?? ''));
// Honeypot (hidden field): if filled, treat as spam and pretend success
$honeypot = trim((string)($_POST['website'] ?? ''));

if ($name === '' || $email === '' || $message === '') {
    redirect_with(['error' => 'Semua kolom wajib diisi']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_with(['error' => 'Email tidak valid']);
}

// If honeypot is filled, silently accept without DB write
if ($honeypot !== '') {
    redirect_with(['sent' => '1']);
}

// Normalize lengths
if (mb_strlen($name) > 100) $name = mb_substr($name, 0, 100);
if (mb_strlen($email) > 100) $email = mb_substr($email, 0, 100);
if (mb_strlen($message) > 5000) $message = mb_substr($message, 0, 5000);
if (mb_strlen($phone) > 20) $phone = mb_substr($phone, 0, 20);
if (mb_strlen($subject) > 200) $subject = mb_substr($subject, 0, 200);

$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

try {
    // Rate limiting: 1 submission per 60 seconds per IP
    if ($ip) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM contact_inquiries WHERE ip_address = :ip AND created_at >= (NOW() - INTERVAL 1 MINUTE)');
        $check->execute([':ip' => $ip]);
        $recent = (int)$check->fetchColumn();
        if ($recent >= 1) {
            redirect_with(['error' => 'Terlalu sering. Coba lagi dalam 1 menit.']);
        }
    }

    $stmt = $pdo->prepare('INSERT INTO contact_inquiries (name, email, phone, subject, message, ip_address, user_agent) VALUES (:name, :email, :phone, :subject, :message, :ip, :ua)');
    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':phone' => ($phone !== '' ? $phone : null),
        ':subject' => ($subject !== '' ? $subject : null),
        ':message' => $message,
        ':ip' => $ip,
        ':ua' => $ua,
    ]);

    // Email notification to admin (settings.contact_email)
    try {
        $to = null;
        $q = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'contact_email' LIMIT 1");
        if ($q->execute()) {
            $to = trim((string)($q->fetchColumn() ?? ''));
        }
        if ($to && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $site = 'SyntaxTrust';
            $subj = 'Inquiry Baru - ' . ($subject !== '' ? $subject : ('Dari ' . $name));
            $lines = [
                "Anda menerima pesan kontak baru:",
                "",
                "Nama   : " . $name,
                "Email  : " . $email,
                ($phone !== '' ? ("Telepon: " . $phone) : null),
                ($subject !== '' ? ("Subjek : " . $subject) : null),
                "",
                "Pesan:",
                $message,
                "",
                "IP: " . ($ip ?: '-'),
                "UA: " . ($ua ?: '-')
            ];
            $body = implode("\r\n", array_values(array_filter($lines, fn($v) => $v !== null)));
            $headers = [];
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-type: text/plain; charset=UTF-8';
            $headers[] = 'From: ' . $site . ' <no-reply@syntaxtrust.local>';
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
            }
            // Suppress errors; we don't want to block user flow on mail failure
            @mail($to, $subj, $body, implode("\r\n", $headers));
        }
    } catch (Throwable $e) {
        // Ignore mail errors
    }
} catch (Throwable $e) {
    // Do not leak details
    redirect_with(['error' => 'Terjadi kesalahan. Coba lagi.']);
}

redirect_with(['sent' => '1']);
