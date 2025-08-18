<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

try {
    $stmt = $pdo->prepare(
        "SELECT * FROM team WHERE is_active = 1 ORDER BY sort_order ASC, created_at ASC"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$r) {
        $r['name'] = h($r['name'] ?? '');
        $r['position'] = h($r['position'] ?? '');
        $r['bio'] = h($r['bio'] ?? '');
        $r['email'] = h($r['email'] ?? '');
        $r['phone'] = h($r['phone'] ?? '');
        $r['profile_image'] = h($r['profile_image'] ?? '');
        // Keep JSON fields as-is (skills, social_links)
    }

    echo json_encode([
        'success' => true,
        'items' => $rows,
        'count' => count($rows)
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
