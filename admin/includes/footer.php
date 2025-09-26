<!-- Footer -->
<?php
// Derive brand and current year dynamically
$__brand = 'SyntaxTrust';
try {
    if (isset($pdo)) {
        $__stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key IN ('site_title','site_name','company_name') AND setting_value IS NOT NULL AND setting_value <> '' LIMIT 1");
        $__stmt->execute();
        $__val = $__stmt->fetchColumn();
        if ($__val) { $__brand = trim((string)$__val); }
    }
} catch (Throwable $__e) { /* ignore */ }
$__year = date('Y');
?>
<footer class="sticky-footer bg-white">
    <div class="container my-auto">
        <div class="d-flex justify-content-center align-items-center py-2">
            <span class="text-sm text-gray-600">&copy; <?= htmlspecialchars($__brand) ?> <?= $__year ?></span>
        </div>
    </div>
    <?php // Optional: you can add extra footer links or version here ?>
</footer>
<!-- End of Footer -->
<?php // Include shared logout modal so it's available on all pages ?>
<?php require_once __DIR__ . '/logout_modal.php'; ?>
