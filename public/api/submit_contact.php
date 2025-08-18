<?php
// JSON API to accept contact inquiries
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(['success' => false, 'message' => 'Method not allowed'], 405);
    }

    // Read JSON body
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);

    if (!is_array($payload)) {
        respond(['success' => false, 'message' => 'Invalid payload'], 400);
    }

    // Sanitize and validate
    $name = trim($payload['name'] ?? '');
    $email = trim($payload['email'] ?? '');
    $phone = trim($payload['phone'] ?? '');
    $subject = trim($payload['subject'] ?? '');
    $message = trim($payload['message'] ?? '');
    $service_id = isset($payload['service_id']) && $payload['service_id'] !== '' ? (int)$payload['service_id'] : null;
    $budget_range = trim($payload['budget_range'] ?? '');
    $timeline = trim($payload['timeline'] ?? '');

    if ($name === '' || $email === '' || $message === '') {
        respond(['success' => false, 'message' => 'Nama, email, dan pesan wajib diisi'], 422);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(['success' => false, 'message' => 'Format email tidak valid'], 422);
    }

    // Insert into DB
    $stmt = $pdo->prepare("INSERT INTO contact_inquiries (name, email, phone, subject, message, service_id, budget_range, timeline, status, ip_address, user_agent) VALUES (?,?,?,?,?,?,?,?, 'new', ?, ?)");
    $ok = $stmt->execute([
        $name,
        $email,
        $phone ?: null,
        $subject ?: null,
        $message,
        $service_id,
        $budget_range ?: null,
        $timeline ?: null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    if (!$ok) {
        respond(['success' => false, 'message' => 'Gagal menyimpan pesan'], 500);
    }

    respond(['success' => true, 'message' => 'Pesan berhasil dikirim']);
} catch (Throwable $e) {
    respond(['success' => false, 'message' => 'Terjadi kesalahan server'], 500);
}
