<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: /syntaxtrust/login.php');
    exit();
}

// Ensure CSRF exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verify_csrf_post(): bool {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Helpers
function slugify($text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('~[^a-z0-9]+~', '-', $text);
    $text = trim($text, '-');
    return $text ?: uniqid('post-');
}

$errors = [];
$flash_msg = '';
$flash_type = 'success';

// Load existing post if id provided
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$post = [
    'title' => '',
    'slug' => '',
    'content' => '',
    'excerpt' => '',
    'category' => '',
    'tags' => '[]',
    'status' => 'draft',
    'is_featured' => 0,
    'featured_image' => '',
    'meta_title' => '',
    'meta_description' => '',
    'published_at' => null,
];

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $post = $row;
    } else {
        header('Location: /syntaxtrust/admin/manage_blog_posts.php');
        exit();
    }
}

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_post()) {
        $errors[] = 'Invalid CSRF token. Please refresh the page and try again.';
    }

    $title = trim($_POST['title'] ?? '');
    $slug_input = trim($_POST['slug'] ?? '');
    $slug = $slug_input !== '' ? slugify($slug_input) : slugify($title);
    $content = trim($_POST['content'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $tags_input = trim($_POST['tags'] ?? ''); // comma-separated
    $status = $_POST['status'] ?? 'draft';
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $meta_title = trim($_POST['meta_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');

    if ($title === '') { $errors[] = 'Title is required.'; }
    if (!in_array($status, ['draft','published','archived'], true)) { $errors[] = 'Invalid status.'; }
    if ($content === '') { $errors[] = 'Content is required.'; }

    // Build tags JSON
    $tags_arr = [];
    if ($tags_input !== '') {
        $parts = array_filter(array_map('trim', explode(',', $tags_input)));
        $tags_arr = array_values(array_unique($parts));
    }
    $tags_json = json_encode($tags_arr, JSON_UNESCAPED_UNICODE);

    // Unique slug check
    $slugStmt = $pdo->prepare($id > 0 ? "SELECT COUNT(*) FROM blog_posts WHERE slug = ? AND id <> ?" : "SELECT COUNT(*) FROM blog_posts WHERE slug = ?");
    $slugParams = $id > 0 ? [$slug, $id] : [$slug];
    $slugStmt->execute($slugParams);
    if ((int)$slugStmt->fetchColumn() > 0) {
        $errors[] = 'Slug already exists. Please choose another.';
    }

    // Handle image upload if provided
    $featured_image_path = $post['featured_image'] ?? '';
    if (isset($_FILES['featured_image']) && is_uploaded_file($_FILES['featured_image']['tmp_name'])) {
        $file = $_FILES['featured_image'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!isset($allowed[$mime])) {
                $errors[] = 'Invalid image type. Allowed: JPG, PNG, GIF, WEBP.';
            }
            if ($file['size'] > 3 * 1024 * 1024) { // 3MB
                $errors[] = 'Image too large. Max 3MB.';
            }
            if (empty($errors)) {
                $ext = $allowed[$mime];
                $dir = __DIR__ . '/../uploads/blog/';
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                $fname = 'post_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $dir . $fname;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    // Store relative path
                    $featured_image_path = 'uploads/blog/' . $fname;
                } else {
                    $errors[] = 'Failed to save uploaded image.';
                }
            }
        } else {
            $errors[] = 'Upload error code: ' . (int)$file['error'];
        }
    }

    if (empty($errors)) {
        try {
            if ($status === 'published' && empty($post['published_at'])) {
                $published_at_sql = ', published_at = NOW()';
            } elseif ($status !== 'published') {
                // keep published_at as is when unpublishing; only set to NULL when archiving if desired
                $published_at_sql = '';
            } else {
                $published_at_sql = '';
            }

            if ($id > 0) {
                $sql = "UPDATE blog_posts SET title = :title, slug = :slug, content = :content, excerpt = :excerpt, category = :category, tags = :tags, status = :status, is_featured = :is_featured, meta_title = :meta_title, meta_description = :meta_description";
                $params = [
                    ':title' => $title,
                    ':slug' => $slug,
                    ':content' => $content,
                    ':excerpt' => $excerpt,
                    ':category' => $category,
                    ':tags' => $tags_json,
                    ':status' => $status,
                    ':is_featured' => $is_featured,
                    ':meta_title' => $meta_title,
                    ':meta_description' => $meta_description,
                    ':id' => $id,
                ];
                if ($featured_image_path !== ($post['featured_image'] ?? '')) {
                    $sql .= ", featured_image = :featured_image";
                    $params[':featured_image'] = $featured_image_path;
                }
                if ($published_at_sql !== '') { $sql .= $published_at_sql; }
                $sql .= " WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $flash_msg = 'Post updated successfully.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO blog_posts (title, slug, content, excerpt, featured_image, author_id, category, tags, status, published_at, view_count, is_featured, meta_title, meta_description, created_at, updated_at) VALUES (:title, :slug, :content, :excerpt, :featured_image, :author_id, :category, :tags, :status, :published_at, 0, :is_featured, :meta_title, :meta_description, NOW(), NOW())");
                $stmt->execute([
                    ':title' => $title,
                    ':slug' => $slug,
                    ':content' => $content,
                    ':excerpt' => $excerpt,
                    ':featured_image' => $featured_image_path,
                    ':author_id' => $_SESSION['user_id'],
                    ':category' => $category,
                    ':tags' => $tags_json,
                    ':status' => $status,
                    ':published_at' => ($status === 'published') ? date('Y-m-d H:i:s') : null,
                    ':is_featured' => $is_featured,
                    ':meta_title' => $meta_title,
                    ':meta_description' => $meta_description,
                ]);
                $id = (int)$pdo->lastInsertId();
                $flash_msg = 'Post created successfully.';
            }
            $flash_type = 'success';
            // Reload latest state
            $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ?");
            $stmt->execute([$id]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $flash_msg = 'An error occurred while saving the post.';
            $flash_type = 'danger';
        }
    } else {
        $flash_msg = 'Please fix the errors below.';
        $flash_type = 'danger';
        // Preserve submitted values
        $post = array_merge($post, [
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'excerpt' => $excerpt,
            'category' => $category,
            'tags' => $tags_json,
            'status' => $status,
            'is_featured' => $is_featured,
            'meta_title' => $meta_title,
            'meta_description' => $meta_description,
            'featured_image' => $featured_image_path,
        ]);
    }
}

// Prepare view values
$tags_display = '';
if (!empty($post['tags'])) {
    $decoded = json_decode($post['tags'], true);
    if (is_array($decoded)) {
        $tags_display = implode(', ', $decoded);
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
            <h1 class="h3 mb-0 text-gray-800"><?php echo $id > 0 ? 'Edit Blog Post' : 'Add Blog Post'; ?></h1>
            <div>
              <a href="/syntaxtrust/admin/manage_blog_posts.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
              <?php if ($id > 0): ?>
                <a href="/syntaxtrust/admin/view_blog_post.php?id=<?php echo (int)$id; ?>" class="btn btn-sm btn-outline-primary">View</a>
              <?php endif; ?>
            </div>
          </div>

          <?php if (!empty($flash_msg)): ?>
            <div class="alert alert-<?php echo $flash_type; ?> alert-dismissible fade show" role="alert">
              <?php echo htmlspecialchars($flash_msg); ?>
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
          <?php endif; ?>

          <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
              <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                  <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="row">
              <div class="col-lg-8">
                <div class="card shadow mb-4">
                  <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Content</h6>
                  </div>
                  <div class="card-body">
                    <div class="form-group">
                      <label>Title <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required>
                    </div>
                    <div class="form-group">
                      <label>Slug</label>
                      <input type="text" class="form-control" name="slug" value="<?php echo htmlspecialchars($post['slug']); ?>" placeholder="auto from title if empty">
                    </div>
                    <div class="form-group">
                      <label>Excerpt</label>
                      <textarea class="form-control" name="excerpt" rows="3"><?php echo htmlspecialchars($post['excerpt']); ?></textarea>
                    </div>
                    <div class="form-group">
                      <label>Content <span class="text-danger">*</span></label>
                      <textarea class="form-control" name="content" rows="12" required><?php echo htmlspecialchars($post['content']); ?></textarea>
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
                        <option value="draft" <?php echo $post['status']==='draft'?'selected':''; ?>>Draft</option>
                        <option value="published" <?php echo $post['status']==='published'?'selected':''; ?>>Published</option>
                        <option value="archived" <?php echo $post['status']==='archived'?'selected':''; ?>>Archived</option>
                      </select>
                    </div>
                    <div class="form-group">
                      <label>Category</label>
                      <input type="text" class="form-control" name="category" value="<?php echo htmlspecialchars($post['category'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                      <label>Tags (comma-separated)</label>
                      <input type="text" class="form-control" name="tags" value="<?php echo htmlspecialchars($tags_display); ?>" placeholder="e.g. php, web, news">
                    </div>
                    <div class="form-group form-check">
                      <input type="checkbox" class="form-check-input" id="is_featured" name="is_featured" <?php echo ((int)$post['is_featured']===1)?'checked':''; ?>>
                      <label class="form-check-label" for="is_featured">Featured</label>
                    </div>
                    <div class="form-group">
                      <label>Featured Image</label>
                      <?php if (!empty($post['featured_image'])): ?>
                        <div class="mb-2">
                          <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="featured" style="max-height:100px;" class="rounded border"/>
                        </div>
                      <?php endif; ?>
                      <input type="file" name="featured_image" accept="image/*" class="form-control-file">
                      <small class="form-text text-muted">Max 3MB. JPG, PNG, GIF, WEBP.</small>
                    </div>
                    <hr>
                    <div class="form-group">
                      <label>Meta Title</label>
                      <input type="text" class="form-control" name="meta_title" value="<?php echo htmlspecialchars($post['meta_title'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                      <label>Meta Description</label>
                      <textarea class="form-control" name="meta_description" rows="3"><?php echo htmlspecialchars($post['meta_description'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block"><?php echo $id>0?'Save Changes':'Create Post'; ?></button>
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
