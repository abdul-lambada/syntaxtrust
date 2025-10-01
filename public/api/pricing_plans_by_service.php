<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../../config/database.php';

try {
    $sid = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;
    if ($sid <= 0) {
        throw new Exception('service_id is required');
    }

    $stmt = $pdo->prepare('SELECT id, name, price, currency FROM pricing_plans WHERE is_active = 1 AND service_id = ? ORDER BY price ASC, id ASC');
    $stmt->execute([$sid]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    echo json_encode([
        'success' => true,
        'items' => array_map(function($r){
            return [
                'id' => (int)$r['id'],
                'name' => (string)$r['name'],
                'price' => (float)$r['price'],
                'currency' => (string)($r['currency'] ?? 'Rp')
            ];
        }, $rows)
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
