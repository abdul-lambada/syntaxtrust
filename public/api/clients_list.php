<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

try {
    $stmt = $pdo->prepare(
        "SELECT * FROM clients WHERE is_active = 1 ORDER BY sort_order ASC, created_at DESC"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$r) {
        $r['name'] = h($r['name'] ?? '');
        $r['logo'] = h($r['logo'] ?? '');
        $r['description'] = h($r['description'] ?? '');
        $r['testimonial'] = h($r['testimonial'] ?? '');
        $r['website_url'] = h($r['website_url'] ?? '');
        // rating kept numeric as-is
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
