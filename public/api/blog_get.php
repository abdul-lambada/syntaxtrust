<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

if (!isset($_GET['slug']) || trim((string)$_GET['slug']) === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid slug']);
    exit;
}

$slug = trim((string)$_GET['slug']);

try {
    $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE slug = ? AND status = 'published' LIMIT 1");
    $stmt->execute([$slug]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Post not found']);
        exit;
    }

    // Sanitize output
    $post['title'] = h($post['title'] ?? '');
    $post['slug'] = h($post['slug'] ?? '');
    $post['excerpt'] = h($post['excerpt'] ?? '');
    $post['content'] = $post['content'] ?? ''; // Keep raw HTML; consumer should render safely
    $post['category'] = h($post['category'] ?? '');
    $post['featured_image'] = h($post['featured_image'] ?? '');
    $post['tags'] = $post['tags'] ?? null; // JSON string

    echo json_encode([
        'success' => true,
        'post' => $post
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
