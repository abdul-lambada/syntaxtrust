<?php
require_once __DIR__ . '/includes/layout.php';

$pageTitle = 'Pengumuman';
$currentPage = 'announcements';

// Pagination
$perPage = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$total = 0;
$list = [];
$typeMap = [
    'success' => ['label' => 'Sukses', 'class' => 'bg-green-100 text-green-800'],
    'warning' => ['label' => 'Peringatan', 'class' => 'bg-yellow-100 text-yellow-800'],
    'error'   => ['label' => 'Error', 'class' => 'bg-red-100 text-red-800'],
    'info'    => ['label' => 'Info', 'class' => 'bg-blue-100 text-blue-800'],
];

try {
    // Count publik (user_id IS NULL)
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM notifications WHERE user_id IS NULL");
    $total = (int)$stmt->fetch(PDO::FETCH_ASSOC)['c'];

    // Ambil list
    $stmt = $pdo->prepare("SELECT id, title, message, type, related_url, created_at FROM notifications WHERE user_id IS NULL ORDER BY created_at DESC LIMIT :lim OFFSET :off");
    $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $list = [];
    $total = 0;
}

$pages = max(1, (int)ceil($total / $perPage));

echo renderPageStart($pageTitle, 'Pengumuman terbaru dari SyntaxTrust', $currentPage);
?>

<main class="max-w-6xl mx-auto px-4 py-10">
    <h1 class="text-3xl font-bold mb-6">Pengumuman</h1>

    <?php if (empty($list)): ?>
        <div class="bg-white shadow rounded p-6 text-gray-600">Belum ada pengumuman.</div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($list as $n): 
                $type = strtolower($n['type'] ?? 'info');
                $m = $typeMap[$type] ?? $typeMap['info'];
                $created = $n['created_at'] ? date('d M Y H:i', strtotime($n['created_at'])) : '';
                $title = h($n['title'] ?? 'Pengumuman');
                $msg = h($n['message'] ?? '');
                $rel = trim((string)($n['related_url'] ?? ''));
                $href = '';
                if ($rel !== '') {
                    if (preg_match('/^https?:\/\//i', $rel)) { $href = $rel; }
                    else { $href = $rel; /* biarkan relatif terhadap public/ */ }
                }
            ?>
            <article class="bg-white shadow rounded p-5">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="text-xl font-semibold"><?= $title ?></h2>
                    <span class="text-sm text-gray-500"><?= h($created) ?></span>
                </div>
                <div class="mb-3">
                    <span class="inline-block text-xs px-2 py-1 rounded <?= h($m['class']) ?>"><?= h($m['label']) ?></span>
                </div>
                <p class="text-gray-700 mb-3"><?= $msg ?></p>
                <?php if ($href): ?>
                    <a class="text-blue-600 hover:underline" href="<?= h($href) ?>">Selengkapnya →</a>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
        <nav class="mt-6 flex items-center justify-center space-x-2">
            <?php for ($i = 1; $i <= $pages; $i++): $active = $i === $page; ?>
                <a href="?page=<?= $i ?>" class="px-3 py-1 rounded border <?= $active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 hover:bg-gray-100' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php echo renderPageEnd(); ?>
