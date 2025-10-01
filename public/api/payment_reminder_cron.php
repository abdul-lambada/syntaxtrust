<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// Auth via shared secret token
$token = $_GET['token'] ?? '';
function get_setting(PDO $pdo, string $key, $default = null) {
    try {
        $s = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $s->execute([$key]);
        $v = $s->fetchColumn();
        if ($v !== false && $v !== null) return $v;
    } catch (Throwable $e) {}
    return $default;
}

$cronSecret = (string)get_setting($pdo, 'cron_shared_secret', '');
if ($cronSecret === '' || !hash_equals($cronSecret, (string)$token)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$remBefore = (int)get_setting($pdo, 'reminder_hours_before_due', 6);
$remAfter  = (int)get_setting($pdo, 'reminder_hours_after_due', 0);
$fonnteToken = (string)get_setting($pdo, 'fonnte_token', '');
$enableAuto = (string)get_setting($pdo, 'enable_auto_whatsapp_payment_notice', '1');

if ($fonnteToken === '' || $enableAuto === '0') {
    echo json_encode(['ok' => true, 'sent' => 0, 'skipped' => 0, 'reason' => 'auto disabled or token missing']);
    exit;
}

$now = time();
$window = 900; // 15 minutes window tolerance
$sent = 0; $skipped = 0; $errors = 0;

// Fetch recent/submitted intents to evaluate
try {
    $q = $pdo->prepare("SELECT id, intent_number, customer_name, customer_email, customer_phone, amount, notes FROM payment_intents WHERE status = 'submitted' AND updated_at >= (NOW() - INTERVAL 120 DAY) ORDER BY id DESC LIMIT 1000");
    $q->execute();
    $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB error', 'detail' => $e->getMessage()]);
    exit;
}

function normalize_phone($phone) {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if ($digits === '') return '';
    if (strpos($digits, '0') === 0) $digits = '62' . substr($digits, 1);
    return $digits;
}

function send_wa($token, $target, $message) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['target' => $target, 'message' => $message]),
        CURLOPT_HTTPHEADER => [ 'Authorization: ' . $token, 'Accept: application/json' ],
        CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    $ok = ($resp !== false);
    curl_close($ch);
    return $ok;
}

function build_msg($pi, $label, $dueTs) {
    $dueHuman = date('d M Y H:i', (int)$dueTs) . ' WIB';
    $amt = 'Rp ' . number_format((float)($pi['amount'] ?? 0), 0, ',', '.');
    $greet = 'Halo ' . (($pi['customer_name'] ?? '') !== '' ? $pi['customer_name'] : 'Pelanggan') . ",\n\n";
    $body  = "Pengingat pembayaran untuk intent: " . ($pi['intent_number'] ?? '') . "\n";
    $body .= "Tipe: " . $label . "\n";
    $body .= "Jumlah: " . $amt . "\n";
    $body .= "Jatuh tempo: " . $dueHuman . "\n\n";
    $body .= "Terima kasih.";
    return $greet . $body;
}

foreach ($rows as $pi) {
    $notesRaw = (string)($pi['notes'] ?? '');
    $n = json_decode($notesRaw, true);
    if (!is_array($n)) { $n = []; }
    $kind = (string)($n['kind'] ?? '');
    $dueTs = isset($n['due_ts']) ? (int)$n['due_ts'] : null;
    $finalTs = isset($n['final_due_ts']) ? (int)$n['final_due_ts'] : null;

    $phone = normalize_phone($pi['customer_phone'] ?? '');
    if ($phone === '') { $skipped++; continue; }

    // Before due reminder (primary)
    if ($remBefore > 0 && $dueTs) {
        $targetTs = $dueTs - ($remBefore * 3600);
        if (abs($now - $targetTs) <= $window && empty($n['reminded_before'])) {
            $ok = send_wa($fonnteToken, $phone, build_msg($pi, $kind === 'installment' ? 'Cicilan' : 'Bayar Penuh', $dueTs));
            if ($ok) { $n['reminded_before'] = true; $sent++; } else { $errors++; }
        }
    }

    // After due reminder (primary)
    if ($remAfter > 0 && $dueTs) {
        $targetTs = $dueTs + ($remAfter * 3600);
        if (abs($now - $targetTs) <= $window && empty($n['reminded_after'])) {
            $ok = send_wa($fonnteToken, $phone, build_msg($pi, $kind === 'installment' ? 'Cicilan (lewat jatuh tempo)' : 'Bayar Penuh (lewat jatuh tempo)', $dueTs));
            if ($ok) { $n['reminded_after'] = true; $sent++; } else { $errors++; }
        }
    }

    // Final settlement reminders for installment
    if ($kind === 'installment' && $finalTs) {
        if ($remBefore > 0) {
            $targetTs = $finalTs - ($remBefore * 3600);
            if (abs($now - $targetTs) <= $window && empty($n['reminded_final_before'])) {
                $ok = send_wa($fonnteToken, $phone, build_msg($pi, 'Pelunasan', $finalTs));
                if ($ok) { $n['reminded_final_before'] = true; $sent++; } else { $errors++; }
            }
        }
        if ($remAfter > 0) {
            $targetTs = $finalTs + ($remAfter * 3600);
            if (abs($now - $targetTs) <= $window && empty($n['reminded_final_after'])) {
                $ok = send_wa($fonnteToken, $phone, build_msg($pi, 'Pelunasan (lewat jatuh tempo)', $finalTs));
                if ($ok) { $n['reminded_final_after'] = true; $sent++; } else { $errors++; }
            }
        }
    }

    // Update notes if modified
    $newNotes = json_encode($n, JSON_UNESCAPED_UNICODE);
    if ($newNotes !== $notesRaw) {
        try {
            $u = $pdo->prepare('UPDATE payment_intents SET notes = ?, updated_at = NOW() WHERE id = ?');
            $u->execute([$newNotes, $pi['id']]);
        } catch (Throwable $e) { $errors++; }
    }
}

echo json_encode(['ok' => true, 'sent' => $sent, 'skipped' => $skipped, 'errors' => $errors, 'checked' => count($rows)]);
