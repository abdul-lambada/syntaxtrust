<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Inputs
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) && is_numeric($_GET['per_page']) ? max(1, min(50, (int)$_GET['per_page'])) : 6;
$offset = ($page - 1) * $per_page;
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$category = isset($_GET['category']) ? trim((string)$_GET['category']) : '';

$where = ["status = 'published'"];
$params = [];

if ($search !== '') {
    $where[] = "(title LIKE ? OR content LIKE ? OR excerpt LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term]);
}
if ($category !== '') {
    $where[] = "category = ?";
    $params[] = $category;
}
$where_clause = implode(' AND ', $where);

try {
    // Count
    $count_sql = "SELECT COUNT(*) FROM blog_posts WHERE $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total = (int)$count_stmt->fetchColumn();

    // Data
    $sql = "SELECT * FROM blog_posts WHERE $where_clause ORDER BY is_featured DESC, published_at DESC LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$r) {
        $r['title'] = h($r['title'] ?? '');
        $r['slug'] = h($r['slug'] ?? '');
        $r['excerpt'] = h($r['excerpt'] ?? '');
        // For content, send stripped short preview to avoid huge payloads
        $content_preview = strip_tags((string)($r['content'] ?? ''));
        $r['content_preview'] = h(mb_substr($content_preview, 0, 180)) . (mb_strlen($content_preview) > 180 ? '...' : '');
        $r['category'] = h($r['category'] ?? '');
        $r['featured_image'] = h($r['featured_image'] ?? '');
        $r['tags'] = $r['tags'] ?? null; // JSON string kept as-is
    }

    echo json_encode([
        'success' => true,
        'items' => $rows,
        'count' => count($rows),
        'meta' => [
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => (int)ceil($total / $per_page)
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
