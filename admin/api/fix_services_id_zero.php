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
    $log = [];

    // Ensure AUTO_INCREMENT on services.id
    $pdo->exec("ALTER TABLE services MODIFY id INT(11) NOT NULL AUTO_INCREMENT");
    $log[] = 'Ensured AUTO_INCREMENT on services.id';

    // Check if there is row with id=0
    $existsStmt = $pdo->query("SELECT COUNT(*) FROM services WHERE id = 0");
    $hasZero = (int)$existsStmt->fetchColumn() > 0;

    $newId = null;
    $updated = [];
    if ($hasZero) {
        // Compute new id
        $maxStmt = $pdo->query("SELECT COALESCE(MAX(id),0) FROM services");
        $newId = ((int)$maxStmt->fetchColumn()) + 1;
        $log[] = 'Computed new id: ' . $newId;

        // For safety, temporarily disable FK checks within this session
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $log[] = 'Disabled FK checks (session)';

        // 1) Insert duplicate row with new id (avoid PK update to bypass FK RESTRICT)
        $insertSql = "INSERT INTO services (id, name, description, short_description, icon, image, price, duration, features, is_featured, is_active, sort_order, created_at, updated_at)
                      SELECT ?, name, description, short_description, icon, image, price, duration, features, is_featured, is_active, sort_order, created_at, updated_at
                      FROM services WHERE id = 0";
        $ins = $pdo->prepare($insertSql);
        $ins->execute([$newId]);
        $updated['services_inserted'] = $ins->rowCount();
        $log[] = 'Inserted duplicate services row: ' . $updated['services_inserted'];

        // 2) Update child references to the new id
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
            $log[] = "Updated {$table}.{$col} from 0 -> {$newId}: " . $updated[$table];
        }

        // 3) Do NOT delete id=0 to avoid FK RESTRICT on some environments.
        //    Instead, neuter the row: mark inactive and rename so it's ignored by UI/logic.
        $neuter = $pdo->prepare("UPDATE services SET name = CONCAT('[DEPRECATED] ', name), is_active = 0 WHERE id = 0");
        $neuter->execute();
        $updated['services_neutered_zero'] = $neuter->rowCount();
        $log[] = 'Neutered services id=0 rows: ' . $updated['services_neutered_zero'];

        // Re-enable FK checks
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $log[] = 'Re-enabled FK checks';
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'changed' => [
            'new_service_id' => $newId,
            'rows' => $updated,
        ],
        'log' => $log,
        'note' => $hasZero ? 'Fixed id=0 and references' : 'No service with id=0 found',
    ]);
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
