<?php
// Layout wrapper for public pages
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/shared-components.php';

function renderPageStart(string $pageTitle, string $pageDescription = '', string $currentPage = '', string $extraHeadHtml = ''): string {
    if ($pageDescription === '') {
        $pageDescription = getSetting('site_description', 'Layanan Pembuatan Website untuk Mahasiswa & UMKM');
    }

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?></title>
    <meta name="description" content="<?= h($pageDescription) ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?= renderCommonStyles() ?>
    <?= $extraHeadHtml ?>
</head>
<body class="bg-gray-50">
    <?= renderNavigation($currentPage) ?>
<?php
    return ob_get_clean();
}

function renderPageEnd(): string {
    ob_start();
    ?>
    <?= renderFooter() ?>
    <?= renderWhatsAppButton() ?>
    <?= renderBackToTop() ?>
    <?= renderLoadingSpinner() ?>
    <?= renderCommonScripts() ?>
</body>
</html>
<?php
    return ob_get_clean();
}
