<?php
// Admin-only maintenance endpoint: fix services.id=0 and references
// Usage: while logged in as admin, visit: /admin/api/fix_services_id_zero.php?confirm=1
// Safety: requires admin session and confirm=1

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    if (!isset($_GET['confirm']) || (string)$_GET['confirm'] !== '1') {
        echo json_encode(['success' => false, 'error' => 'Add confirm=1 to execute']);
        exit;
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->beginTransaction();

    // Ensure AUTO_INCREMENT on services.id
    $pdo->exec("ALTER TABLE services MODIFY id INT(11) NOT NULL AUTO_INCREMENT");

    // Check if there is row with id=0
    $existsStmt = $pdo->query("SELECT COUNT(*) FROM services WHERE id = 0");
    $hasZero = (int)$existsStmt->fetchColumn() > 0;

    $newId = null;
    $updated = [];
    if ($hasZero) {
        // Compute new id
        $maxStmt = $pdo->query("SELECT COALESCE(MAX(id),0) FROM services");
        $newId = ((int)$maxStmt->fetchColumn()) + 1;

        // Temporarily disable FKs (for engines that enforce)
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        // Update services id
        $u1 = $pdo->prepare("UPDATE services SET id = ? WHERE id = 0");
        $u1->execute([$newId]);
        $updated['services_id'] = $u1->rowCount();

        // Update references
        foreach ([
            'pricing_plans' => 'service_id',
            'testimonials' => 'service_id',
            'contact_inquiries' => 'service_id',
            'payment_intents' => 'service_id',
            'orders' => 'service_id',
        ] as $table => $col) {
            $stmt = $pdo->prepare("UPDATE {$table} SET {$col} = ? WHERE {$col} = 0");
            $stmt->execute([$newId]);
            $updated[$table] = $stmt->rowCount();
        }

        // Re-enable FKs
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'changed' => [
            'new_service_id' => $newId,
            'rows' => $updated,
        ],
        'note' => $hasZero ? 'Fixed id=0 and references' : 'No service with id=0 found',
    ]);
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
