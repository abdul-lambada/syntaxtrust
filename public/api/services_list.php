<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

function h($string) { return htmlspecialchars($string, ENT_QUOTES, 'UTF-8'); }

try {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE is_active = 1 ORDER BY is_featured DESC, sort_order ASC");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // sanitize
    foreach ($rows as &$r) {
        $r['name'] = h($r['name'] ?? '');
        $r['short_description'] = h($r['short_description'] ?? '');
        $r['description'] = h($r['description'] ?? '');
        $r['icon'] = h($r['icon'] ?? '');
        $r['duration'] = h($r['duration'] ?? '');
        $r['image'] = h($r['image'] ?? '');
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
