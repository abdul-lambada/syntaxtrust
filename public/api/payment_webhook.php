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
    if ($event === 'payment_succeeded') { $newStatus = 'approved'; }
    elseif ($event === 'payment_failed') { $newStatus = 'rejected'; }

    if ($newStatus !== null) {
        $notesAppend = "\n[" . date('Y-m-d H:i:s') . "] webhook: $event";
        if ($amount !== null) { $notesAppend .= ' amt=' . number_format($amount, 2, '.', ''); }
        $stmt = $pdo->prepare("UPDATE payment_intents SET status = ?, amount = COALESCE(?, amount), notes = CONCAT(COALESCE(notes,''), ?), updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$newStatus, $amount, $notesAppend, $pi['id']]);

        // Optional: auto-create order on success if not exists
        if ($event === 'payment_succeeded') {
            // create order with paid status
            $order_number = 'ORD-' . date('Ymd') . '-' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $service_id = !empty($pi['service_id']) ? (int)$pi['service_id'] : null;
            $pricing_plan_id = !empty($pi['pricing_plan_id']) ? (int)$pi['pricing_plan_id'] : null;
            $total_amount = $amount !== null ? $amount : ((isset($pi['amount']) && $pi['amount'] !== null) ? (float)$pi['amount'] : 0.00);
            $project_description = 'Created via webhook from intent ' . $pi['intent_number'];
            $requirements = '[]';
            $payment_method = 'gateway';

            try {
                $stmt = $pdo->prepare('INSERT INTO orders (order_number, user_id, service_id, pricing_plan_id, customer_name, customer_email, customer_phone, project_description, requirements, total_amount, status, payment_status, payment_method, notes, created_at) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                $stmt->execute([
                    $order_number,
                    $service_id,
                    $pricing_plan_id,
                    $pi['customer_name'],
                    $pi['customer_email'],
                    $pi['customer_phone'] ?? null,
                    $project_description,
                    $requirements,
                    $total_amount,
                    'confirmed',
                    'paid',
                    $payment_method,
                    'Auto-created from payment intent ' . $pi['intent_number'],
                ]);
                $new_order_id = $pdo->lastInsertId();
                // Try to store back order_id
                try {
                    $up = $pdo->prepare('UPDATE payment_intents SET order_id = ? WHERE id = ?');
                    $up->execute([$new_order_id, $pi['id']]);
                } catch (Throwable $e2) { /* column may not exist */ }
            } catch (Throwable $e3) {
                // swallow to not break webhook 200
            }
        }
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
