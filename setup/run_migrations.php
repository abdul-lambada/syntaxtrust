<?php
// Simple migration runner: applies SQL files in setup/migrations/ in filename order
// Usage (CLI): php setup/run_migrations.php

require_once __DIR__ . '/../config/database.php';

if (php_sapi_name() !== 'cli') {
    echo "This script should be run from CLI.\n";
    exit(1);
}

function println($msg) { echo $msg . PHP_EOL; }

try {
    // Ensure migrations table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (\n        id INT AUTO_INCREMENT PRIMARY KEY,\n        filename VARCHAR(255) NOT NULL UNIQUE,\n        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Load applied migrations
    $stmt = $pdo->query('SELECT filename FROM migrations');
    $applied = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'filename');
    $appliedMap = array_fill_keys($applied, true);

    $dir = __DIR__ . '/migrations';
    if (!is_dir($dir)) {
        println("No migrations directory found at: $dir");
        exit(0);
    }

    $files = glob($dir . '/*.sql');
    sort($files, SORT_STRING);

    if (empty($files)) {
        println('No migration files to apply.');
        exit(0);
    }

    $appliedCount = 0;

    foreach ($files as $file) {
        $name = basename($file);
        if (isset($appliedMap[$name])) {
            println("SKIP  $name (already applied)");
            continue;
        }

        $sql = file_get_contents($file);
        if ($sql === false) {
            println("ERROR reading $name, skipping.");
            continue;
        }

        // Naive split by semicolon; handle edge cases minimally
        $statements = array_filter(array_map('trim', preg_split('/;\s*\n|;\r?\n|;$/m', $sql)));

        try {
            $pdo->beginTransaction();
            if (count($statements) <= 1) {
                $pdo->exec($sql);
            } else {
                foreach ($statements as $stmtSql) {
                    if ($stmtSql === '') continue;
                    $pdo->exec($stmtSql);
                }
            }
            $ins = $pdo->prepare('INSERT INTO migrations (filename) VALUES (?)');
            $ins->execute([$name]);
            $pdo->commit();
            $appliedCount++;
            println("APPLY $name");
        } catch (Throwable $e) {
            $pdo->rollBack();
            println("FAIL  $name: " . $e->getMessage());
            exit(1);
        }
    }


    println("Done. Applied $appliedCount new migration(s).");
    exit(0);
} catch (Throwable $e) {
    println('Migration runner error: ' . $e->getMessage());
    exit(1);
}
