<?php
// New endpoint to avoid caching of old script and avoid DELETE operations entirely.
// Usage: while logged in as admin: /admin/api/fix_services_id_zero_v2.php?confirm=1

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

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

    // Check presence of id=0
    $hasZero = (int)$pdo->query("SELECT COUNT(*) FROM services WHERE id = 0")->fetchColumn() > 0;
    $log[] = 'Has id=0: ' . ($hasZero ? 'yes' : 'no');

    $newId = null;
    $updated = [];

    if ($hasZero) {
        // Compute a safe new id
        $newId = (int)$pdo->query("SELECT COALESCE(MAX(id),0)+1 FROM services")->fetchColumn();
        $log[] = 'Computed new id: ' . $newId;

        // Disable FK checks within this session to allow reference updates in strict environments
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $log[] = 'Disabled FK checks (session)';

        // Copy row id=0 to the new id
        $ins = $pdo->prepare("INSERT INTO services (id, name, description, short_description, icon, image, price, duration, features, is_featured, is_active, sort_order, created_at, updated_at)
                              SELECT ?, name, description, short_description, icon, image, price, duration, features, is_featured, is_active, sort_order, created_at, updated_at
                              FROM services WHERE id = 0");
        $ins->execute([$newId]);
        $updated['services_inserted'] = $ins->rowCount();
        $log[] = 'Inserted duplicate services row: ' . $updated['services_inserted'];

        // Update references in child tables
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

        // DO NOT DELETE id=0. Just neuter it so UI ignores it and FK remains satisfied in edge engines.
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
        'note' => $hasZero ? 'Fixed id=0 and references (no delete)' : 'No service with id=0 found',
    ]);
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
