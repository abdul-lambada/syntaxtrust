<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

// Helper function for safe HTML output
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid portfolio ID']);
    exit;
}

$portfolio_id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT * FROM portfolio 
        WHERE id = ? AND is_active = 1
    ");
    $stmt->execute([$portfolio_id]);
    $portfolio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$portfolio) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Portfolio not found']);
        exit;
    }
    
    // Sanitize output
    $portfolio['title'] = h($portfolio['title']);
    $portfolio['description'] = h($portfolio['description']);
    $portfolio['short_description'] = h($portfolio['short_description']);
    $portfolio['client_name'] = h($portfolio['client_name']);
    $portfolio['category'] = h($portfolio['category']);
    $portfolio['project_url'] = h($portfolio['project_url']);
    $portfolio['github_url'] = h($portfolio['github_url']);
    
    echo json_encode([
        'success' => true,
        'portfolio' => $portfolio
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
