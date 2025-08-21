<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../config/database.php';

try {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('Invalid plan ID');
    }
    
    $plan_id = (int)$_GET['id'];
    
    // Get pricing plan with service information
    $stmt = $pdo->prepare("
        SELECT pp.*, s.name as service_name 
        FROM pricing_plans pp 
        LEFT JOIN services s ON pp.service_id = s.id 
        WHERE pp.id = ? AND pp.is_active = 1
    ");
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$plan) {
        throw new Exception('Pricing plan not found');
    }
    
    // Decode JSON fields safely
    if (!empty($plan['features'])) {
        $decoded = json_decode($plan['features'], true);
        $plan['features'] = is_array($decoded) ? $decoded : [];
    } else {
        $plan['features'] = [];
    }
    
    if (!empty($plan['technologies'])) {
        $decoded = json_decode($plan['technologies'], true);
        $plan['technologies'] = is_array($decoded) ? $decoded : [];
    } else {
        $plan['technologies'] = [];
    }
    
    // Format price for display
    $plan['formatted_price'] = $plan['price'] > 0 
        ? $plan['currency'] . ' ' . number_format($plan['price'], 0, ',', '.')
        : 'Custom Quote';
    
    // Format billing period
    $plan['formatted_billing_period'] = ucfirst(str_replace('_', ' ', $plan['billing_period']));
    
    echo json_encode([
        'success' => true,
        'plan' => $plan
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
