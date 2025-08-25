<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_BASE_PATH . '/admin/login.php');
    exit();
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function verify_csrf_blog(): bool {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Helper functions
function slugify($text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('~[^a-z0-9]+~', '-', $text);
    $text = trim($text, '-');
    return $text ?: uniqid('post-');
}

function handle_blog_upload($file_input_name, $current_image_path = null) {
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] !== UPLOAD_ERR_OK) {
        return $current_image_path;
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $max_size = 3 * 1024 * 1024; // 3MB
    
    $file = $_FILES[$file_input_name];
    if ($file['size'] > $max_size) {
        throw new RuntimeException('Image too large. Max 3MB.');
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Invalid image type. Allowed: JPG, PNG, GIF, WEBP.');
    }
    
    $ext = $allowed[$mime];
    $dir = __DIR__ . '/../uploads/blog/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    
    $fname = 'post_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $dir . $fname;
    
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Failed to save uploaded image.');
    }
    
    // Delete old image if exists
    if ($current_image_path && file_exists(__DIR__ . '/../' . $current_image_path)) {
        @unlink(__DIR__ . '/../' . $current_image_path);
    }
    
    return 'uploads/blog/' . $fname;
}

// Feedback
$flash_msg = '';
$flash_type = 'success';

// Handle different actions
$action = $_GET['action'] ?? 'list';
$edit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Load post data for edit/view
$post_data = null;
if (($action === 'edit' || $action === 'view') && $edit_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $stmt->execute([$edit_id]);
    $post_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$post_data) {
        $action = 'list';
        $flash_msg = 'Post not found.';
        $flash_type = 'danger';
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_blog()) {
        $flash_msg = 'Invalid CSRF token. Please refresh and try again.';
        $flash_type = 'danger';
    } else {
        try {
            // Quick actions (publish/unpublish, feature/unfeature, delete)
            if (isset($_POST['quick_action']) && isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                if ($_POST['quick_action'] === 'publish') {
                    $stmt = $pdo->prepare("UPDATE blog_posts SET status = 'published', published_at = IF(published_at IS NULL, NOW(), published_at) WHERE id = ?");
                    $stmt->execute([$id]);
                    $flash_msg = 'Post published.';
                } elseif ($_POST['quick_action'] === 'unpublish') {
                    $stmt = $pdo->prepare("UPDATE blog_posts SET status = 'draft' WHERE id = ?");
                    $stmt->execute([$id]);
                    $flash_msg = 'Post unpublished (draft).';
                } elseif ($_POST['quick_action'] === 'feature') {
                    $stmt = $pdo->prepare("UPDATE blog_posts SET is_featured = 1 WHERE id = ?");
                    $stmt->execute([$id]);
                    $flash_msg = 'Post marked as featured.';
                } elseif ($_POST['quick_action'] === 'unfeature') {
                    $stmt = $pdo->prepare("UPDATE blog_posts SET is_featured = 0 WHERE id = ?");
                    $stmt->execute([$id]);
                    $flash_msg = 'Post un-featured.';
                } elseif ($_POST['quick_action'] === 'delete') {
                    $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
                    $stmt->execute([$id]);
                    $flash_msg = 'Post deleted.';
                }
            }
            // Create new post
            elseif (isset($_POST['create_post'])) {
                $title = trim($_POST['title'] ?? '');
                $slug_input = trim($_POST['slug'] ?? '');
                $slug = $slug_input !== '' ? slugify($slug_input) : slugify($title);
                $content = trim($_POST['content'] ?? '');
                $excerpt = trim($_POST['excerpt'] ?? '');
                $category = trim($_POST['category'] ?? '');
                $tags_input = trim($_POST['tags'] ?? '');
                $status = $_POST['status'] ?? 'draft';
                $is_featured = isset($_POST['is_featured']) ? 1 : 0;
                $meta_title = trim($_POST['meta_title'] ?? '');
                $meta_description = trim($_POST['meta_description'] ?? '');
                
                if ($title === '' || $content === '') {
                    throw new RuntimeException('Title and content are required.');
                }
                
                // Check unique slug
                $slugStmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE slug = ?");
                $slugStmt->execute([$slug]);
                if ((int)$slugStmt->fetchColumn() > 0) {
                    throw new RuntimeException('Slug already exists. Please choose another.');
                }
                
                // Handle tags
                $tags_arr = [];
                if ($tags_input !== '') {
                    $parts = array_filter(array_map('trim', explode(',', $tags_input)));
                    $tags_arr = array_values(array_unique($parts));
                }
                $tags_json = json_encode($tags_arr, JSON_UNESCAPED_UNICODE);
                
                // Handle image upload
                $featured_image_path = handle_blog_upload('featured_image');
                
                $stmt = $pdo->prepare("INSERT INTO blog_posts (title, slug, content, excerpt, featured_image, author_id, category, tags, status, published_at, view_count, is_featured, meta_title, meta_description, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([
                    $title, $slug, $content, $excerpt, $featured_image_path, $_SESSION['user_id'],
                    $category, $tags_json, $status,
                    ($status === 'published') ? date('Y-m-d H:i:s') : null,
                    $is_featured, $meta_title, $meta_description
                ]);
                
                $flash_msg = 'Post created successfully.';
                $action = 'list';
            }
            // Update existing post
            elseif (isset($_POST['update_post'])) {
                $id = (int)$_POST['post_id'];
                $title = trim($_POST['title'] ?? '');
                $slug_input = trim($_POST['slug'] ?? '');
                $slug = $slug_input !== '' ? slugify($slug_input) : slugify($title);
                $content = trim($_POST['content'] ?? '');
                $excerpt = trim($_POST['excerpt'] ?? '');
                $category = trim($_POST['category'] ?? '');
                $tags_input = trim($_POST['tags'] ?? '');
                $status = $_POST['status'] ?? 'draft';
                $is_featured = isset($_POST['is_featured']) ? 1 : 0;
                $meta_title = trim($_POST['meta_title'] ?? '');
                $meta_description = trim($_POST['meta_description'] ?? '');
                
                if ($title === '' || $content === '') {
                    throw new RuntimeException('Title and content are required.');
                }
                
                // Check unique slug (excluding current post)
                $slugStmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE slug = ? AND id <> ?");
                $slugStmt->execute([$slug, $id]);
                if ((int)$slugStmt->fetchColumn() > 0) {
                    throw new RuntimeException('Slug already exists. Please choose another.');
                }
                
                // Handle tags
                $tags_arr = [];
                if ($tags_input !== '') {
                    $parts = array_filter(array_map('trim', explode(',', $tags_input)));
                    $tags_arr = array_values(array_unique($parts));
                }
                $tags_json = json_encode($tags_arr, JSON_UNESCAPED_UNICODE);
                
                // Get current post data
                $stmt = $pdo->prepare("SELECT featured_image FROM blog_posts WHERE id = ?");
                $stmt->execute([$id]);
                $current_post = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Handle image upload
                $featured_image_path = handle_blog_upload('featured_image', $current_post['featured_image'] ?? null);
                
                $sql = "UPDATE blog_posts SET title = ?, slug = ?, content = ?, excerpt = ?, category = ?, tags = ?, status = ?, is_featured = ?, meta_title = ?, meta_description = ?, updated_at = NOW()";
                $params = [$title, $slug, $content, $excerpt, $category, $tags_json, $status, $is_featured, $meta_title, $meta_description];
                
                if ($featured_image_path !== ($current_post['featured_image'] ?? '')) {
                    $sql .= ", featured_image = ?";
                    $params[] = $featured_image_path;
                }
                
                if ($status === 'published' && empty($current_post['published_at'])) {
                    $sql .= ", published_at = NOW()";
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                $flash_msg = 'Post updated successfully.';
                $action = 'list';
            }
        } catch (Throwable $e) {
            $flash_msg = $e->getMessage();
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

// Only show list if action is 'list' or default
if ($action === 'list') {
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
            <a href="?action=add" class="btn btn-sm btn-primary">
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
                            <a href="?action=view&id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                            <a href="?action=edit&id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form method="POST" class="d-inline">
                              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                              <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                              <?php if ($p['status'] === 'published'): ?>
                                <button type="submit" name="quick_action" value="unpublish" class="btn btn-sm btn-outline-secondary">Unpublish</button>
                              <?php else: ?>
                                <button type="submit" name="quick_action" value="publish" class="btn btn-sm btn-outline-secondary">Publish</button>
                              <?php endif; ?>
                            </form>
                            <form method="POST" class="d-inline">
                              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                              <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                              <?php if ((int)$p['is_featured'] === 1): ?>
                                <button type="submit" name="quick_action" value="unfeature" class="btn btn-sm btn-outline-info">Unfeature</button>
                              <?php else: ?>
                                <button type="submit" name="quick_action" value="feature" class="btn btn-sm btn-outline-info">Feature</button>
                              <?php endif; ?>
                            </form>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this post?');">
                              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                              <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                              <button type="submit" name="quick_action" value="delete" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
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
<?php
} // End of list action

// Add/Edit Form
elseif ($action === 'add' || $action === 'edit') {
    // Prepare form data
    $form_data = [
        'title' => '',
        'slug' => '',
        'content' => '',
        'excerpt' => '',
        'category' => '',
        'tags' => '',
        'status' => 'draft',
        'is_featured' => 0,
        'featured_image' => '',
        'meta_title' => '',
        'meta_description' => ''
    ];
    
    if ($action === 'edit' && $post_data) {
        $form_data = array_merge($form_data, $post_data);
        // Convert tags JSON to comma-separated string
        if (!empty($form_data['tags'])) {
            $decoded = json_decode($form_data['tags'], true);
            if (is_array($decoded)) {
                $form_data['tags'] = implode(', ', $decoded);
            }
        }
    }
?>
<body id="page-top">
  <div id="wrapper">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    <div id="content-wrapper" class="d-flex flex-column">
      <div id="content">
        <?php require_once __DIR__ . '/includes/topbar.php'; ?>
        <div class="container-fluid">

          <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800"><?= $action === 'edit' ? 'Edit Blog Post' : 'Add Blog Post' ?></h1>
            <a href="?" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
          </div>

          <?php if (!empty($flash_msg)): ?>
            <div class="alert alert-<?php echo $flash_type; ?> alert-dismissible fade show" role="alert">
              <?php echo htmlspecialchars($flash_msg); ?>
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
          <?php endif; ?>

          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <?php if ($action === 'edit'): ?>
              <input type="hidden" name="post_id" value="<?php echo (int)$edit_id; ?>">
            <?php endif; ?>
            
            <div class="row">
              <div class="col-lg-8">
                <div class="card shadow mb-4">
                  <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Content</h6>
                  </div>
                  <div class="card-body">
                    <div class="form-group">
                      <label>Title <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($form_data['title']); ?>" required>
                    </div>
                    <div class="form-group">
                      <label>Slug</label>
                      <input type="text" class="form-control" name="slug" value="<?php echo htmlspecialchars($form_data['slug']); ?>" placeholder="auto from title if empty">
                    </div>
                    <div class="form-group">
                      <label>Excerpt</label>
                      <textarea class="form-control" name="excerpt" rows="3"><?php echo htmlspecialchars($form_data['excerpt']); ?></textarea>
                    </div>
                    <div class="form-group">
                      <label>Content <span class="text-danger">*</span></label>
                      <textarea class="form-control" name="content" rows="12" required><?php echo htmlspecialchars($form_data['content']); ?></textarea>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-lg-4">
                <div class="card shadow mb-4">
                  <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Settings</h6>
                  </div>
                  <div class="card-body">
                    <div class="form-group">
                      <label>Status</label>
                      <select name="status" class="form-control">
                        <option value="draft" <?php echo $form_data['status']==='draft'?'selected':''; ?>>Draft</option>
                        <option value="published" <?php echo $form_data['status']==='published'?'selected':''; ?>>Published</option>
                        <option value="archived" <?php echo $form_data['status']==='archived'?'selected':''; ?>>Archived</option>
                      </select>
                    </div>
                    <div class="form-group">
                      <label>Category</label>
                      <input type="text" class="form-control" name="category" value="<?php echo htmlspecialchars($form_data['category'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                      <label>Tags (comma-separated)</label>
                      <input type="text" class="form-control" name="tags" value="<?php echo htmlspecialchars($form_data['tags']); ?>" placeholder="e.g. php, web, news">
                    </div>
                    <div class="form-group form-check">
                      <input type="checkbox" class="form-check-input" id="is_featured" name="is_featured" <?php echo ((int)$form_data['is_featured']===1)?'checked':''; ?>>
                      <label class="form-check-label" for="is_featured">Featured</label>
                    </div>
                    <div class="form-group">
                      <label>Featured Image</label>
                      <?php if (!empty($form_data['featured_image'])): ?>
                        <div class="mb-2">
                          <img src="<?php echo htmlspecialchars(assetUrlAdmin($form_data['featured_image'])); ?>" alt="featured" style="max-height:100px;" class="rounded border"/>
                        </div>
                      <?php endif; ?>
                      <input type="file" name="featured_image" accept="image/*" class="form-control-file">
                      <small class="form-text text-muted">Max 3MB. JPG, PNG, GIF, WEBP.</small>
                    </div>
                    <hr>
                    <div class="form-group">
                      <label>Meta Title</label>
                      <input type="text" class="form-control" name="meta_title" value="<?php echo htmlspecialchars($form_data['meta_title'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                      <label>Meta Description</label>
                      <textarea class="form-control" name="meta_description" rows="3"><?php echo htmlspecialchars($form_data['meta_description'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" name="<?= $action === 'edit' ? 'update_post' : 'create_post' ?>" class="btn btn-primary btn-block">
                      <?= $action === 'edit' ? 'Update Post' : 'Create Post' ?>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </form>

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
<?php
} // End of add/edit action

// View Post
elseif ($action === 'view' && $post_data) {
    // Decode tags JSON if present
    $tags = [];
    if (!empty($post_data['tags'])) {
        $decoded = json_decode($post_data['tags'], true);
        if (is_array($decoded)) {
            $tags = $decoded;
        }
    }
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
              <a href="?" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
              <a href="?action=edit&id=<?php echo (int)$edit_id; ?>" class="btn btn-sm btn-primary">Edit</a>
              <?php if (!empty($post_data['slug'])): ?>
                <a href="<?= APP_BASE_PATH ?>/public/blog-detail.php?slug=<?php echo urlencode($post_data['slug']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary"><i class="fas fa-external-link-alt mr-1"></i> View Public</a>
              <?php endif; ?>
            </div>
          </div>

          <div class="row">
            <div class="col-lg-8">
              <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                  <h6 class="m-0 font-weight-bold text-primary"><?php echo htmlspecialchars($post_data['title']); ?></h6>
                  <div>
                    <?php if ($post_data['status'] === 'published'): ?>
                      <span class="badge badge-success">Published</span>
                    <?php elseif ($post_data['status'] === 'draft'): ?>
                      <span class="badge badge-secondary">Draft</span>
                    <?php else: ?>
                      <span class="badge badge-warning">Archived</span>
                    <?php endif; ?>
                    <?php if ((int)$post_data['is_featured'] === 1): ?>
                      <span class="badge badge-info ml-1">Featured</span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="card-body">
                  <?php if (!empty($post_data['excerpt'])): ?>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($post_data['excerpt'])); ?></p>
                    <hr>
                  <?php endif; ?>

                  <?php if (!empty($post_data['featured_image'])): ?>
                    <div class="mb-3">
                      <img src="<?php echo htmlspecialchars(assetUrlAdmin($post_data['featured_image'])); ?>" alt="featured" class="img-fluid rounded"/>
                    </div>
                  <?php endif; ?>

                  <div>
                    <?php echo nl2br(htmlspecialchars($post_data['content'])); ?>
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
                  <div class="mb-2"><strong>Slug:</strong> <code><?php echo htmlspecialchars($post_data['slug']); ?></code></div>
                  <div class="mb-2"><strong>Category:</strong> <?php echo htmlspecialchars($post_data['category'] ?? ''); ?></div>
                  <div class="mb-2"><strong>Tags:</strong>
                    <?php if (!empty($tags)): ?>
                      <?php foreach ($tags as $t): ?>
                        <span class="badge badge-light mr-1">#<?php echo htmlspecialchars((string)$t); ?></span>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </div>
                  <div class="mb-2"><strong>Published At:</strong> <?php echo $post_data['published_at'] ? date('d M Y H:i', strtotime($post_data['published_at'])) : '-'; ?></div>
                  <div class="mb-2"><strong>Created:</strong> <?php echo $post_data['created_at'] ? date('d M Y H:i', strtotime($post_data['created_at'])) : '-'; ?></div>
                  <div class="mb-2"><strong>Updated:</strong> <?php echo $post_data['updated_at'] ? date('d M Y H:i', strtotime($post_data['updated_at'])) : '-'; ?></div>
                  <div class="mb-2"><strong>Meta Title:</strong> <span class="text-muted"><?php echo htmlspecialchars($post_data['meta_title'] ?? ''); ?></span></div>
                  <div class="mb-2"><strong>Meta Description:</strong>
                    <div class="text-muted"><?php echo nl2br(htmlspecialchars($post_data['meta_description'] ?? '')); ?></div>
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
<?php
} // End of view action
?>
