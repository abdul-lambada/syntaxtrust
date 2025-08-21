<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: /syntaxtrust/login.php');
    exit();
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function verify_csrf_blog(): bool {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Feedback
$flash_msg = '';
$flash_type = 'success';

// Handle quick actions (publish/unpublish, feature/unfeature, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_blog()) {
        $flash_msg = 'Invalid CSRF token. Please refresh and try again.';
        $flash_type = 'danger';
    } else {
        try {
            if (isset($_POST['action']) && isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                if ($_POST['action'] === 'publish') {
                    $stmt = $pdo->prepare("UPDATE blog_posts SET status = 'published', published_at = IF(published_at IS NULL, NOW(), published_at) WHERE id = ?");
                    $stmt->execute([$id]);
                    $flash_msg = 'Post published.';
                    $flash_type = 'success';
                } elseif ($_POST['action'] === 'unpublish') {
                    $stmt = $pdo->prepare("UPDATE blog_posts SET status = 'draft' WHERE id = ?");
                    $stmt->execute([$id]);
                    $flash_msg = 'Post unpublished (draft).';
                    $flash_type = 'success';
                } elseif ($_POST['action'] === 'feature') {
                    $stmt = $pdo->prepare("UPDATE blog_posts SET is_featured = 1 WHERE id = ?");
                    $stmt->execute([$id]);
                    $flash_msg = 'Post marked as featured.';
                    $flash_type = 'success';
                } elseif ($_POST['action'] === 'unfeature') {
                    $stmt = $pdo->prepare("UPDATE blog_posts SET is_featured = 0 WHERE id = ?");
                    $stmt->execute([$id]);
                    $flash_msg = 'Post un-featured.';
                    $flash_type = 'success';
                } elseif ($_POST['action'] === 'delete') {
                    $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
                    $stmt->execute([$id]);
                    $flash_msg = 'Post deleted.';
                    $flash_type = 'success';
                }
            }
        } catch (Throwable $e) {
            $flash_msg = 'An error occurred while processing your request.';
            $flash_type = 'danger';
        }
    }
}

// Filters & pagination
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$featured = isset($_GET['featured']) ? trim($_GET['featured']) : '';// '', '1', '0'
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(title LIKE :q OR slug LIKE :q)';
    $params[':q'] = "%$q%";
}
if ($status !== '' && in_array($status, ['draft','published','archived'], true)) {
    $where[] = 'status = :status';
    $params[':status'] = $status;
}
if ($category !== '') {
    $where[] = 'category = :cat';
    $params[':cat'] = $category;
}
if ($featured !== '' && in_array($featured, ['0','1'], true)) {
    $where[] = 'is_featured = :feat';
    $params[':feat'] = (int)$featured;
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count total
$cnt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts $where_sql");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $limit));
if ($page > $total_pages) { $page = $total_pages; }
$offset = ($page - 1) * $limit;

// Fetch rows
$sql = "SELECT id, title, slug, status, category, is_featured, published_at, created_at, updated_at, featured_image
        FROM blog_posts
        $where_sql
        ORDER BY COALESCE(published_at, created_at) DESC
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            <h1 class="h3 mb-0 text-gray-800">Manage Blog Posts</h1>
            <a href="/syntaxtrust/admin/add_blog_post.php" class="btn btn-sm btn-primary">
              <i class="fas fa-plus mr-1"></i> Add New
            </a>
          </div>

          <?php if (!empty($flash_msg)): ?>
          <div class="alert alert-<?php echo $flash_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($flash_msg); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <?php endif; ?>

          <div class="card shadow mb-4">
            <div class="card-header py-3">
              <h6 class="m-0 font-weight-bold text-primary">Search and Filter</h6>
            </div>
            <div class="card-body">
              <form method="GET" class="form-inline">
                <div class="form-group mx-sm-2 mb-2">
                  <input type="text" class="form-control" name="q" placeholder="Search title or slug" value="<?php echo htmlspecialchars($q); ?>">
                </div>
                <div class="form-group mx-sm-2 mb-2">
                  <select class="form-control" name="status">
                    <option value="">All Status</option>
                    <option value="draft" <?php echo $status==='draft'?'selected':''; ?>>Draft</option>
                    <option value="published" <?php echo $status==='published'?'selected':''; ?>>Published</option>
                    <option value="archived" <?php echo $status==='archived'?'selected':''; ?>>Archived</option>
                  </select>
                </div>
                <div class="form-group mx-sm-2 mb-2">
                  <input type="text" class="form-control" name="category" placeholder="Category" value="<?php echo htmlspecialchars($category); ?>">
                </div>
                <div class="form-group mx-sm-2 mb-2">
                  <select class="form-control" name="featured">
                    <option value="">Featured: All</option>
                    <option value="1" <?php echo $featured==='1'?'selected':''; ?>>Yes</option>
                    <option value="0" <?php echo $featured==='0'?'selected':''; ?>>No</option>
                  </select>
                </div>
                <button type="submit" class="btn btn-primary mb-2">Search</button>
                <?php if ($q!=='' || $status!=='' || $category!=='' || $featured!==''): ?>
                  <a href="manage_blog_posts.php" class="btn btn-secondary mb-2 ml-2">Clear</a>
                <?php endif; ?>
              </form>
            </div>
          </div>

          <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex align-items-center justify-content-between">
              <h6 class="m-0 font-weight-bold text-primary">Posts (<?php echo $total; ?> total)</h6>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                  <thead>
                    <tr>
                      <th>Title</th>
                      <th>Slug</th>
                      <th>Category</th>
                      <th>Status</th>
                      <th>Featured</th>
                      <th>Published</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($rows)): ?>
                      <tr><td colspan="7" class="text-center text-muted">No posts found.</td></tr>
                    <?php else: ?>
                      <?php foreach ($rows as $p): ?>
                      <tr>
                        <td>
                          <div class="font-weight-bold mb-1"><?php echo htmlspecialchars($p['title']); ?></div>
                          <?php if (!empty($p['featured_image'])): ?>
                            <img src="<?php echo htmlspecialchars(assetUrlAdmin($p['featured_image'])); ?>" alt="thumb" style="height:40px;"/>
                          <?php endif; ?>
                        </td>
                        <td><code><?php echo htmlspecialchars($p['slug']); ?></code></td>
                        <td><?php echo htmlspecialchars($p['category'] ?? ''); ?></td>
                        <td>
                          <?php if ($p['status'] === 'published'): ?>
                            <span class="badge badge-success">Published</span>
                          <?php elseif ($p['status'] === 'draft'): ?>
                            <span class="badge badge-secondary">Draft</span>
                          <?php else: ?>
                            <span class="badge badge-warning">Archived</span>
                          <?php endif; ?>
                        </td>
                        <td><?php echo ((int)$p['is_featured'] === 1) ? '<span class="badge badge-info">Yes</span>' : '<span class="badge badge-light">No</span>'; ?></td>
                        <td><?php echo $p['published_at'] ? date('d M Y H:i', strtotime($p['published_at'])) : '-'; ?></td>
                        <td>
                          <div class="btn-group" role="group">
                            <a href="/syntaxtrust/admin/view_blog_post.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                            <a href="/syntaxtrust/admin/edit_blog_post.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form method="POST" class="d-inline">
                              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                              <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                              <?php if ($p['status'] === 'published'): ?>
                                <button type="submit" name="action" value="unpublish" class="btn btn-sm btn-outline-secondary">Unpublish</button>
                              <?php else: ?>
                                <button type="submit" name="action" value="publish" class="btn btn-sm btn-outline-secondary">Publish</button>
                              <?php endif; ?>
                            </form>
                            <form method="POST" class="d-inline">
                              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                              <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                              <?php if ((int)$p['is_featured'] === 1): ?>
                                <button type="submit" name="action" value="unfeature" class="btn btn-sm btn-outline-info">Unfeature</button>
                              <?php else: ?>
                                <button type="submit" name="action" value="feature" class="btn btn-sm btn-outline-info">Feature</button>
                              <?php endif; ?>
                            </form>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this post?');">
                              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                              <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                              <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                            </form>
                          </div>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>

              <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                  <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                      <li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>&q=<?php echo urlencode($q); ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>&featured=<?php echo urlencode($featured); ?>">Previous</a></li>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                      <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&q=<?php echo urlencode($q); ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>&featured=<?php echo urlencode($featured); ?>"><?php echo $i; ?></a>
                      </li>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                      <li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>&q=<?php echo urlencode($q); ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>&featured=<?php echo urlencode($featured); ?>">Next</a></li>
                    <?php endif; ?>
                  </ul>
                </nav>
              <?php endif; ?>

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
