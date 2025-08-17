<?php
// Payment Intent submission handler
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

function redirect_with_pi($params = []) {
    $base = '/syntaxtrust/public/payment_intent_new.php';
    if (!empty($params)) {
        $q = http_build_query($params);
        $base .= ($q ? ('?' . $q) : '');
    }
    header('Location: ' . $base);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect_with_pi(['error' => 'Metode tidak diizinkan']);
}

// CSRF check
$csrf = $_POST['csrf_pi'] ?? '';
if (empty($csrf) || empty($_SESSION['csrf_pi']) || !hash_equals($_SESSION['csrf_pi'], $csrf)) {
    redirect_with_pi(['error' => 'Sesi tidak valid. Muat ulang halaman.']);
}
// Single-use token
unset($_SESSION['csrf_pi']);

// Collect inputs
$customer_name  = trim((string)($_POST['customer_name'] ?? ''));
$customer_email = trim((string)($_POST['customer_email'] ?? ''));
$customer_phone = trim((string)($_POST['customer_phone'] ?? ''));
$pricing_plan_id = (int)($_POST['pricing_plan_id'] ?? 0);
$service_id     = isset($_POST['service_id']) && $_POST['service_id'] !== '' ? (int)$_POST['service_id'] : null;
$amount_raw     = trim((string)($_POST['amount'] ?? ''));
$amount         = ($amount_raw === '' ? null : (float)$amount_raw);
$notes          = trim((string)($_POST['notes'] ?? ''));
$honeypot       = trim((string)($_POST['website'] ?? ''));

// Basic validation
if ($customer_name === '' || $customer_email === '' || $pricing_plan_id <= 0) {
    redirect_with_pi(['error' => 'Nama, email, dan paket harga wajib diisi']);
}
if (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
    redirect_with_pi(['error' => 'Email tidak valid']);
}

// Honeypot
if ($honeypot !== '') {
    header('Location: /syntaxtrust/public/payment_intent_thanks.php');
    exit();
}

// Normalize lengths
if (mb_strlen($customer_name) > 100) $customer_name = mb_substr($customer_name, 0, 100);
if (mb_strlen($customer_email) > 100) $customer_email = mb_substr($customer_email, 0, 100);
if (mb_strlen($customer_phone) > 20) $customer_phone = mb_substr($customer_phone, 0, 20);
if (mb_strlen($notes) > 5000) $notes = mb_substr($notes, 0, 5000);

$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

try {
    // Rate limiting: 1 submission per 60 seconds per IP
    if ($ip) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM payment_intents WHERE ip_address = :ip AND created_at >= (NOW() - INTERVAL 1 MINUTE)');
        $check->execute([':ip' => $ip]);
        $recent = (int)$check->fetchColumn();
        if ($recent >= 1) {
            redirect_with_pi(['error' => 'Terlalu sering. Coba lagi dalam 1 menit.']);
        }
    }

    // Handle optional file upload
    $proof_path = null;
    if (isset($_FILES['payment_proof']) && is_array($_FILES['payment_proof']) && ($_FILES['payment_proof']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $err = (int)($_FILES['payment_proof']['error'] ?? UPLOAD_ERR_OK);
        if ($err === UPLOAD_ERR_OK) {
            $tmp = $_FILES['payment_proof']['tmp_name'];
            $name = basename((string)$_FILES['payment_proof']['name']);
            $size = (int)($_FILES['payment_proof']['size'] ?? 0);
            if ($size > 2 * 1024 * 1024) {
                redirect_with_pi(['error' => 'File terlalu besar (maks 2MB).']);
            }
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            $mime  = $finfo ? finfo_file($finfo, $tmp) : null;
            if ($finfo) @finfo_close($finfo);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
            if (!$mime || !isset($allowed[$mime])) {
                redirect_with_pi(['error' => 'Tipe file tidak diizinkan.']);
            }
            $ext = $allowed[$mime];
            $targetDir = __DIR__ . '/../uploads/payment_proofs';
            if (!is_dir($targetDir)) { @mkdir($targetDir, 0755, true); }
            $safeName = 'proof_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $target = $targetDir . '/' . $safeName;
            if (!@move_uploaded_file($tmp, $target)) {
                redirect_with_pi(['error' => 'Gagal menyimpan file.']);
            }
            // Public path used by app
            $proof_path = '/syntaxtrust/uploads/payment_proofs/' . $safeName;
        } else {
            redirect_with_pi(['error' => 'Gagal upload file.']);
        }
    }

    // Generate intent number
    $rand = random_int(1000, 9999);
    $intent_number = 'PI' . date('YmdHis') . $rand;

    $stmt = $pdo->prepare('INSERT INTO payment_intents (intent_number, service_id, pricing_plan_id, customer_name, customer_email, customer_phone, amount, notes, payment_proof_path, status, ip_address, user_agent) VALUES (:intent, :service, :plan, :name, :email, :phone, :amount, :notes, :proof, :status, :ip, :ua)');
    $stmt->execute([
        ':intent' => $intent_number,
        ':service' => $service_id,
        ':plan' => $pricing_plan_id,
        ':name' => $customer_name,
        ':email' => $customer_email,
        ':phone' => ($customer_phone !== '' ? $customer_phone : null),
        ':amount' => $amount,
        ':notes' => ($notes !== '' ? $notes : null),
        ':proof' => $proof_path,
        ':status' => 'submitted',
        ':ip' => $ip,
        ':ua' => $ua,
    ]);

    header('Location: /syntaxtrust/public/payment_intent_thanks.php?intent=' . urlencode($intent_number));
    exit();
} catch (Throwable $e) {
    redirect_with_pi(['error' => 'Terjadi kesalahan. Coba lagi.']);
}
