<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid service ID']);
    exit;
}

$service_id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ? AND is_active = 1");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$service) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Service not found']);
        exit;
    }
    
    // Sanitize output
    $service['name'] = h($service['name']);
    $service['description'] = h($service['description']);
    $service['short_description'] = h($service['short_description']);
    $service['icon'] = h($service['icon']);
    $service['duration'] = h($service['duration']);
    
    echo json_encode([
        'success' => true,
        'service' => $service
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
