<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: /syntaxtrust/login.php');
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: /syntaxtrust/admin/manage_blog_posts.php');
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id, title, slug, content, excerpt, featured_image, author_id, category, tags, status, published_at, view_count, is_featured, meta_title, meta_description, created_at, updated_at FROM blog_posts WHERE id = ?");
    $stmt->execute([$id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $post = false;
}

if (!$post) {
    header('Location: /syntaxtrust/admin/manage_blog_posts.php');
    exit();
}

// decode tags JSON if present
$tags = [];
if (!empty($post['tags'])) {
    $decoded = json_decode($post['tags'], true);
    if (is_array($decoded)) {
        $tags = $decoded;
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<body id="page-top">
  <div id="wrapper">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    <div id="content-wrapper" class="d-flex flex-column">
      <div id="content">
        <?php require_once __DIR__ . '/includes/topbar.php'; ?>
        <div class="container-fluid">

          <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">View Blog Post</h1>
            <div>
              <a href="/syntaxtrust/admin/manage_blog_posts.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
              <?php if (!empty($post['slug'])): ?>
                <a href="/syntaxtrust/public/blog.php?slug=<?php echo urlencode($post['slug']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary"><i class="fas fa-external-link-alt mr-1"></i> View Public</a>
              <?php endif; ?>
            </div>
          </div>

          <div class="row">
            <div class="col-lg-8">
              <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                  <h6 class="m-0 font-weight-bold text-primary"><?php echo htmlspecialchars($post['title']); ?></h6>
                  <div>
                    <?php if ($post['status'] === 'published'): ?>
                      <span class="badge badge-success">Published</span>
                    <?php elseif ($post['status'] === 'draft'): ?>
                      <span class="badge badge-secondary">Draft</span>
                    <?php else: ?>
                      <span class="badge badge-warning">Archived</span>
                    <?php endif; ?>
                    <?php if ((int)$post['is_featured'] === 1): ?>
                      <span class="badge badge-info ml-1">Featured</span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="card-body">
                  <?php if (!empty($post['excerpt'])): ?>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($post['excerpt'])); ?></p>
                    <hr>
                  <?php endif; ?>

                  <?php if (!empty($post['featured_image'])): ?>
                    <div class="mb-3">
                      <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="featured" class="img-fluid rounded"/>
                    </div>
                  <?php endif; ?>

                  <div>
                    <?php
                      // Content may contain HTML; display safely. If your CMS stores trusted HTML, you may want to allow it. For now, escape to be safe.
                      echo nl2br(htmlspecialchars($post['content']));
                    ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-4">
              <div class="card shadow mb-4">
                <div class="card-header py-3">
                  <h6 class="m-0 font-weight-bold text-primary">Details</h6>
                </div>
                <div class="card-body">
                  <div class="mb-2"><strong>Slug:</strong> <code><?php echo htmlspecialchars($post['slug']); ?></code></div>
                  <div class="mb-2"><strong>Category:</strong> <?php echo htmlspecialchars($post['category'] ?? ''); ?></div>
                  <div class="mb-2"><strong>Tags:</strong>
                    <?php if (!empty($tags)): ?>
                      <?php foreach ($tags as $t): ?>
                        <span class="badge badge-light mr-1">#<?php echo htmlspecialchars((string)$t); ?></span>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </div>
                  <div class="mb-2"><strong>Published At:</strong> <?php echo $post['published_at'] ? date('d M Y H:i', strtotime($post['published_at'])) : '-'; ?></div>
                  <div class="mb-2"><strong>Created:</strong> <?php echo $post['created_at'] ? date('d M Y H:i', strtotime($post['created_at'])) : '-'; ?></div>
                  <div class="mb-2"><strong>Updated:</strong> <?php echo $post['updated_at'] ? date('d M Y H:i', strtotime($post['updated_at'])) : '-'; ?></div>
                  <div class="mb-2"><strong>Meta Title:</strong> <span class="text-muted"><?php echo htmlspecialchars($post['meta_title'] ?? ''); ?></span></div>
                  <div class="mb-2"><strong>Meta Description:</strong>
                    <div class="text-muted"><?php echo nl2br(htmlspecialchars($post['meta_description'] ?? '')); ?></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
      <?php require_once __DIR__ . '/includes/footer.php'; ?>
    </div>
  </div>

  <a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
  </a>

  <?php require_once __DIR__ . '/includes/scripts.php'; ?>
</body>
</html>
