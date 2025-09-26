<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

// Authenticate via environment variable WEBHOOK_SECRET
$provided = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';
$expected = getenv('WEBHOOK_SECRET');
if (!$expected || !hash_equals((string)$expected, (string)$provided)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$event = $payload['event'] ?? '';
$intent_number = $payload['intent_number'] ?? '';
$amount = isset($payload['amount']) ? (float)$payload['amount'] : null;

if ($intent_number === '' || $event === '') {
    http_response_code(422);
    echo json_encode(['error' => 'intent_number and event are required']);
    exit;
}

try {
    // Fetch intent
    $stmt = $pdo->prepare('SELECT * FROM payment_intents WHERE intent_number = ? LIMIT 1');
    $stmt->execute([$intent_number]);
    $pi = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pi) {
        http_response_code(404);
        echo json_encode(['error' => 'Intent not found']);
        exit;
    }

    $newStatus = null;
    if ($event === 'payment_succeeded') { $newStatus = 'paid'; }
    elseif ($event === 'payment_failed') { $newStatus = 'failed'; }

    if ($newStatus !== null) {
        $notesAppend = "\n[" . date('Y-m-d H:i:s') . "] webhook: $event";
        if ($amount !== null) { $notesAppend .= ' amt=' . number_format($amount, 2, '.', ''); }
        $stmt = $pdo->prepare("UPDATE payment_intents SET status = ?, amount = COALESCE(?, amount), notes = CONCAT(COALESCE(notes,''), ?), updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$newStatus, $amount, $notesAppend, $pi['id']]);

        // Reconcile order payment on success
        if ($event === 'payment_succeeded') {
            // Find order_number from notes JSON or order_id
            $order_number = null;
            $notesJson = $pi['notes'] ?? '';
            $decoded = json_decode((string)$notesJson, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded['order_number'])) {
                $order_number = (string)$decoded['order_number'];
            }
            $order = null;
            if ($order_number) {
                $os = $pdo->prepare('SELECT * FROM orders WHERE order_number = ? LIMIT 1');
                $os->execute([$order_number]);
                $order = $os->fetch(PDO::FETCH_ASSOC) ?: null;
            } elseif (!empty($pi['order_id'])) {
                $os = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
                $os->execute([(int)$pi['order_id']]);
                $order = $os->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($order) { $order_number = $order['order_number']; }
            }

            if ($order) {
                // Sum all paid intents for this order
                $sum = 0.0;
                try {
                    $qs = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payment_intents WHERE status = 'paid' AND JSON_EXTRACT(notes, '$.order_number') = ?");
                    $qs->execute([$order_number]);
                    $sum = (float)$qs->fetchColumn();
                } catch (Throwable $e4) { $sum = 0.0; }

                $newPayStatus = $sum >= (float)$order['total_amount'] ? 'paid' : 'unpaid';
                if ($newPayStatus === 'paid' && $order['payment_status'] !== 'paid') {
                    // mark fully paid
                    $up = $pdo->prepare("UPDATE orders SET payment_status = 'paid', status = CASE WHEN status IN ('pending','confirmed') THEN 'in_progress' ELSE status END, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $up->execute([$order['id']]);

                    // Send WhatsApp thank-you with testimonial link
                    try {
                        // Load Fonnte token
                        $tokenStmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
                        $tokenStmt->execute(['fonnte_token']);
                        $fonnteToken = $tokenStmt->fetchColumn();
                        if ($fonnteToken && !empty($order['customer_phone'])) {
                            // Brand
                            $brand = 'SyntaxTrust';
                            foreach (['site_title','site_name','company_name'] as $k) {
                                $bk = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
                                $bk->execute([$k]);
                                $bv = trim((string)($bk->fetchColumn() ?: ''));
                                if ($bv !== '') { $brand = $bv; break; }
                            }
                            // Testimonial link
                            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                            $base = $scheme . '://' . $host . str_replace('/public/api', '', rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/'));
                            $testimonialUrl = $base . '/public/testimonial_submit.php?order_number=' . urlencode($order_number);

                            // Normalize phone
                            $digits = preg_replace('/\D+/', '', (string)$order['customer_phone']);
                            if ($digits !== '' && strpos($digits, '0') === 0) { $digits = '62' . substr($digits, 1); }

                            $lines = [];
                            $lines[] = 'Halo ' . $order['customer_name'] . ', pembayaran untuk ' . $order_number . ' telah kami terima. Terima kasih telah memilih ' . $brand . '!';
                            $lines[] = '';
                            $lines[] = 'Kami sangat menghargai jika Anda bersedia memberikan testimoni:';
                            $lines[] = $testimonialUrl;
                            $lines[] = '';
                            $lines[] = 'Jika membutuhkan layanan tambahan, balas pesan ini ya. — ' . $brand;
                            $message = implode("\n", $lines);

                            $ch = curl_init();
                            curl_setopt_array($ch, [
                                CURLOPT_URL => 'https://api.fonnte.com/send',
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_POST => true,
                                CURLOPT_POSTFIELDS => http_build_query([
                                    'target' => $digits,
                                    'message' => $message,
                                ]),
                                CURLOPT_HTTPHEADER => [
                                    'Authorization: ' . $fonnteToken,
                                    'Accept: application/json',
                                ],
                                CURLOPT_TIMEOUT => 20,
                            ]);
                            curl_exec($ch);
                            curl_close($ch);
                        }
                    } catch (Throwable $e5) { /* ignore WA errors */ }
                } else {
                    // Not fully paid yet, update partial info if needed
                    if ($order['payment_status'] !== 'paid' && $sum > 0) {
                        $up = $pdo->prepare("UPDATE orders SET payment_status = 'unpaid', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $up->execute([$order['id']]);
                    }
                }
            }
        }
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
