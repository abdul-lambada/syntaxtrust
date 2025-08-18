<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

try {
    $stmt = $pdo->prepare(
        "SELECT * FROM portfolio WHERE is_active = 1 ORDER BY is_featured DESC, created_at DESC"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$r) {
        $r['title'] = h($r['title'] ?? '');
        $r['short_description'] = h($r['short_description'] ?? '');
        $r['description'] = h($r['description'] ?? '');
        $r['client_name'] = h($r['client_name'] ?? '');
        $r['category'] = h($r['category'] ?? '');
        $r['project_url'] = h($r['project_url'] ?? '');
        $r['github_url'] = h($r['github_url'] ?? '');
        $r['image_main'] = h($r['image_main'] ?? '');
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
