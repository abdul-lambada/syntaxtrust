<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

try {
    $stmt = $pdo->prepare(
        "SELECT t.*, s.name AS service_name
         FROM testimonials t
         LEFT JOIN services s ON t.service_id = s.id
         WHERE t.is_active = 1
         ORDER BY t.is_featured DESC, t.sort_order ASC, t.created_at DESC"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$r) {
        $r['client_name'] = h($r['client_name'] ?? '');
        $r['client_position'] = h($r['client_position'] ?? '');
        $r['client_company'] = h($r['client_company'] ?? '');
        $r['client_image'] = h($r['client_image'] ?? '');
        $r['project_name'] = h($r['project_name'] ?? '');
        $r['service_name'] = h($r['service_name'] ?? '');
        $r['content'] = h($r['content'] ?? '');
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
