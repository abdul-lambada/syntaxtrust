<?php
require_once 'config/session.php';
require_once 'config/database.php';
require_once 'includes/upload.php';

// Define upload directory
define('UPLOAD_DIR', 'uploads/blog_posts/');

// Function to handle file uploads
function handle_upload($file_input_name, $current_image_path = null) {
    return secure_upload(
        $file_input_name,
        UPLOAD_DIR,
        [
            'maxBytes' => 2 * 1024 * 1024,
            'allowedExt' => ['jpg','jpeg','png','webp','gif'],
            'allowedMime' => ['image/jpeg','image/png','image/webp','image/gif'],
            'prefix' => 'blog_'
        ],
        $current_image_path
    );
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// CSRF protection setup
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verify_csrf(): bool {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token']);
}

// Handle CRUD operations
$message = '';
$message_type = '';

// Delete blog post
if (isset($_POST['delete_post']) && isset($_POST['post_id'])) {
    if (!verify_csrf()) {
        $message = "Invalid CSRF token.";
        $message_type = "danger";
    } else {
        $post_id = $_POST['post_id'];
        try {
        // Fetch image path first
        $stmt = $pdo->prepare("SELECT featured_image FROM blog_posts WHERE id = ?");
        $stmt->execute([$post_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $image_to_delete = $row ? $row['featured_image'] : null;

        // Delete record
        $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
        $stmt->execute([$post_id]);

        // Safely delete file in UPLOAD_DIR
        if ($stmt->rowCount() > 0 && $image_to_delete) {
            $base = realpath(rtrim(UPLOAD_DIR, '/\\'));
            $target = realpath($image_to_delete);
            if ($base && $target && strpos($target, $base) === 0 && is_file($target)) {
                @unlink($target);
            }
        }

            $message = "Blog post deleted successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error deleting blog post: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Toggle post status
if (isset($_POST['toggle_status']) && isset($_POST['post_id'])) {
    if (!verify_csrf()) {
        $message = "Invalid CSRF token.";
        $message_type = "danger";
    } else {
        $post_id = $_POST['post_id'];
        try {
            $stmt = $pdo->prepare("UPDATE blog_posts SET status = CASE WHEN status = 'published' THEN 'draft' ELSE 'published' END WHERE id = ?");
            $stmt->execute([$post_id]);
            $message = "Blog post status updated successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error updating blog post status: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Toggle featured status
if (isset($_POST['toggle_featured']) && isset($_POST['post_id'])) {
    if (!verify_csrf()) {
        $message = "Invalid CSRF token.";
        $message_type = "danger";
    } else {
        $post_id = $_POST['post_id'];
        try {
            $stmt = $pdo->prepare("UPDATE blog_posts SET is_featured = NOT is_featured WHERE id = ?");
            $stmt->execute([$post_id]);
            $message = "Featured status updated successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error updating featured status: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Create new blog post
if (isset($_POST['create_post'])) {
    if (!verify_csrf()) {
        $message = "Invalid CSRF token.";
        $message_type = "danger";
    } else {
        $title = $_POST['title'];
        $slug = $_POST['slug'];
        $content = $_POST['content'];
        $excerpt = $_POST['excerpt'];
        $featured_image = handle_upload('featured_image');
        $author_id = $_SESSION['user_id'];
        $category = $_POST['category'];
        $tags = $_POST['tags'];
        $status = $_POST['status'];
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $meta_title = $_POST['meta_title'];
        $meta_description = $_POST['meta_description'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO blog_posts (title, slug, content, excerpt, featured_image, author_id, category, tags, status, is_featured, meta_title, meta_description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $slug, $content, $excerpt, $featured_image, $author_id, $category, $tags, $status, $is_featured, $meta_title, $meta_description]);
            $message = "Blog post created successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error creating blog post: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Update blog post
if (isset($_POST['update_post'])) {
    if (!verify_csrf()) {
        $message = "Invalid CSRF token.";
        $message_type = "danger";
    } else {
        $post_id = $_POST['post_id'];

        // Fetch current image path
        $stmt = $pdo->prepare("SELECT featured_image FROM blog_posts WHERE id = ?");
        $stmt->execute([$post_id]);
        $current_item = $stmt->fetch(PDO::FETCH_ASSOC);
        $current_image = $current_item ? $current_item['featured_image'] : null;

        $title = $_POST['title'];
        $slug = $_POST['slug'];
        $content = $_POST['content'];
        $excerpt = $_POST['excerpt'];
        $featured_image = handle_upload('featured_image', $current_image);
        $category = $_POST['category'];
        $tags = $_POST['tags'];
        $status = $_POST['status'];
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $meta_title = $_POST['meta_title'];
        $meta_description = $_POST['meta_description'];
        
        try {
            $stmt = $pdo->prepare("UPDATE blog_posts SET title = ?, slug = ?, content = ?, excerpt = ?, featured_image = ?, category = ?, tags = ?, status = ?, is_featured = ?, meta_title = ?, meta_description = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$title, $slug, $content, $excerpt, $featured_image, $category, $tags, $status, $is_featured, $meta_title, $meta_description, $post_id]);
            $message = "Blog post updated successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error updating featured status: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Search and pagination
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query
$where_clause = "";
$params = [];

if (!empty($search)) {
    $where_clause = "WHERE title LIKE ? OR content LIKE ? OR excerpt LIKE ? OR category LIKE ?";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param, $search_param];
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM blog_posts $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get blog posts with pagination
$sql = "SELECT b.*, u.full_name as author_name FROM blog_posts b LEFT JOIN users u ON b.author_id = u.id $where_clause ORDER BY b.is_featured DESC, b.published_at DESC, b.created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$blog_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include header
require_once 'includes/header.php';
?>

<body id="page-top">

    <!-- Page Wrapper -->
    <div id="wrapper">

        <!-- Sidebar -->
        <?php require_once 'includes/sidebar.php'; ?>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">

                <!-- Topbar -->
                <?php require_once 'includes/topbar.php'; ?>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">

                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Manage Blog Posts</h1>
                        <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#addPostModal">
                            <i class="fas fa-plus fa-sm text-white-50"></i> Add New Blog Post
                        </a>
                    </div>

                    <!-- Alert Messages -->
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Search Bar -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Search Blog Posts</h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="form-inline">
                                <div class="form-group mx-sm-3 mb-2">
                                    <input type="text" class="form-control" name="search" placeholder="Search by title, content, category..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <button type="submit" class="btn btn-primary mb-2">Search</button>
                                <?php if (!empty($search)): ?>
                                    <a href="manage_blog_posts.php" class="btn btn-secondary mb-2 ml-2">Clear</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <!-- Blog Posts Table -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Blog Posts List (<?php echo $total_records; ?> total)</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Featured Image</th>
                                            <th>Title</th>
                                            <th>Author</th>
                                            <th>Category</th>
                                            <th>Status</th>
                                            <th>Featured</th>
                                            <th>Views</th>
                                            <th>Published</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($blog_posts as $post): ?>
                                            <tr>
                                                <td><?php echo $post['id']; ?></td>
                                                <td>
                                                    <?php if (!empty($post['featured_image'])): ?>
                                                        <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="Blog Post" class="img-thumbnail" width="80" height="60" style="object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-secondary text-white d-flex align-items-center justify-content-center" style="width: 80px; height: 60px;">
                                                            <i class="fas fa-image"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($post['title']); ?></strong>
                                                    <?php if (!empty($post['excerpt'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($post['excerpt'], 0, 100) . '...'); ?></small>
                                                    <?php endif; ?>
                                                    <br><small class="text-info">Slug: <?php echo htmlspecialchars($post['slug']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($post['author_name'] ?? 'Unknown'); ?></td>
                                                <td>
                                                    <span class="badge badge-info">
                                                        <?php echo htmlspecialchars($post['category'] ?? 'Uncategorized'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $post['status'] === 'published' ? 'success' : ($post['status'] === 'draft' ? 'warning' : 'secondary'); ?>">
                                                        <?php echo ucfirst($post['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $post['is_featured'] ? 'warning' : 'light'; ?>">
                                                        <?php echo $post['is_featured'] ? 'Featured' : 'Regular'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-info"><?php echo $post['view_count']; ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($post['published_at']): ?>
                                                        <?php echo date('d M Y', strtotime($post['published_at'])); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not published</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#viewPostModal<?php echo $post['id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editPostModal<?php echo $post['id']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to toggle this post\'s status?')">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                                            <button type="submit" name="toggle_status" class="btn btn-sm btn-<?php echo $post['status'] === 'published' ? 'warning' : 'success'; ?>">
                                                                <i class="fas fa-<?php echo $post['status'] === 'published' ? 'eye-slash' : 'eye'; ?>"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to toggle featured status?')">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                                            <button type="submit" name="toggle_featured" class="btn btn-sm btn-<?php echo $post['is_featured'] ? 'secondary' : 'warning'; ?>">
                                                                <i class="fas fa-star"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this blog post? This action cannot be undone.')">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                                            <button type="submit" name="delete_post" class="btn btn-sm btn-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
                <!-- /.container-fluid -->

            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            <?php require_once 'includes/footer.php'; ?>
            <!-- End of Footer -->

        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <!-- Footer -->
    <?php require_once 'includes/footer.php'; ?>
    <!-- End of Footer -->

</div>
<!-- End of Content Wrapper -->

</div>
<!-- End of Page Wrapper -->

<!-- Scroll to Top Button-->
<a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
</a>

<!-- Add Blog Post Modal -->
<div class="modal fade" id="addPostModal" tabindex="-1" role="dialog" aria-labelledby="addPostModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPostModalLabel">Add New Blog Post</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="manage_blog_posts.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="title">Title</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="slug">Slug</label>
                                <input type="text" class="form-control" id="slug" name="slug" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="excerpt">Excerpt</label>
                        <textarea class="form-control" id="excerpt" name="excerpt" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="content">Content</label>
                        <textarea class="form-control" id="content" name="content" rows="8" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="featured_image">Featured Image</label>
                                <input type="file" class="form-control-file" id="featured_image" name="featured_image" accept="image/*">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="category">Category</label>
                                <input type="text" class="form-control" id="category" name="category">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="tags">Tags (comma separated)</label>
                                <input type="text" class="form-control" id="tags" name="tags">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="draft">Draft</option>
                                    <option value="published">Published</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="meta_title">Meta Title</label>
                                <input type="text" class="form-control" id="meta_title" name="meta_title">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="meta_description">Meta Description</label>
                                <input type="text" class="form-control" id="meta_description" name="meta_description">
                            </div>
                        </div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="is_featured" name="is_featured">
                        <label class="form-check-label" for="is_featured">Featured Post</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_post" class="btn btn-primary">Create Post</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Blog Post Modals -->
<?php foreach ($blog_posts as $post): ?>
<div class="modal fade" id="editPostModal<?php echo $post['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="editPostModalLabel<?php echo $post['id']; ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editPostModalLabel<?php echo $post['id']; ?>">Edit Blog Post</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="manage_blog_posts.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_title<?php echo $post['id']; ?>">Title</label>
                                <input type="text" class="form-control" id="edit_title<?php echo $post['id']; ?>" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_slug<?php echo $post['id']; ?>">Slug</label>
                                <input type="text" class="form-control" id="edit_slug<?php echo $post['id']; ?>" name="slug" value="<?php echo htmlspecialchars($post['slug']); ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_excerpt<?php echo $post['id']; ?>">Excerpt</label>
                        <textarea class="form-control" id="edit_excerpt<?php echo $post['id']; ?>" name="excerpt" rows="3"><?php echo htmlspecialchars($post['excerpt']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit_content<?php echo $post['id']; ?>">Content</label>
                        <textarea class="form-control" id="edit_content<?php echo $post['id']; ?>" name="content" rows="8" required><?php echo htmlspecialchars($post['content']); ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_featured_image_<?php echo $post['id']; ?>">New Featured Image (optional)</label>
                                <input type="file" class="form-control-file" id="edit_featured_image_<?php echo $post['id']; ?>" name="featured_image" accept="image/*">
                                <?php if (!empty($post['featured_image'])): ?>
                                    <div class="mt-2">
                                        <small>Current Image:</small><br>
                                        <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="Current Featured Image" style="max-width: 200px; height: auto;">
                                        <a href="<?php echo htmlspecialchars($post['featured_image']); ?>" target="_blank" class="ml-2">View</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_category<?php echo $post['id']; ?>">Category</label>
                                <input type="text" class="form-control" id="edit_category<?php echo $post['id']; ?>" name="category" value="<?php echo htmlspecialchars($post['category']); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_tags<?php echo $post['id']; ?>">Tags (comma separated)</label>
                                <input type="text" class="form-control" id="edit_tags<?php echo $post['id']; ?>" name="tags" value="<?php echo htmlspecialchars($post['tags']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_status<?php echo $post['id']; ?>">Status</label>
                                <select class="form-control" id="edit_status<?php echo $post['id']; ?>" name="status">
                                    <option value="draft" <?php echo $post['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="published" <?php echo $post['status'] == 'published' ? 'selected' : ''; ?>>Published</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_meta_title<?php echo $post['id']; ?>">Meta Title</label>
                                <input type="text" class="form-control" id="edit_meta_title<?php echo $post['id']; ?>" name="meta_title" value="<?php echo htmlspecialchars($post['meta_title']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_meta_description<?php echo $post['id']; ?>">Meta Description</label>
                                <input type="text" class="form-control" id="edit_meta_description<?php echo $post['id']; ?>" name="meta_description" value="<?php echo htmlspecialchars($post['meta_description']); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_featured<?php echo $post['id']; ?>" name="is_featured" <?php echo $post['is_featured'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="edit_is_featured<?php echo $post['id']; ?>">Featured Post</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_post" class="btn btn-primary">Update Post</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- View Blog Post Modals -->
<?php foreach ($blog_posts as $post): ?>
<div class="modal fade" id="viewPostModal<?php echo $post['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="viewPostModalLabel<?php echo $post['id']; ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="viewPostModalLabel<?php echo $post['id']; ?>">View Blog Post: <?php echo htmlspecialchars($post['title']); ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8">
                        <?php if (!empty($post['featured_image'])): ?>
                            <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="img-fluid rounded mb-3">
                        <?php endif; ?>
                        
                        <div class="post-meta mb-4">
                            <span class="badge badge-<?php echo $post['status'] === 'published' ? 'success' : 'warning'; ?> mr-2">
                                <?php echo ucfirst($post['status']); ?>
                            </span>
                            <?php if ($post['is_featured']): ?>
                                <span class="badge badge-warning mr-2"><i class="fas fa-star"></i> Featured</span>
                            <?php endif; ?>
                            <span class="text-muted">
                                <i class="far fa-user"></i> <?php echo htmlspecialchars($post['author_name'] ?? 'Unknown'); ?>
                                <i class="far fa-calendar-alt ml-2"></i> <?php echo date('M d, Y', strtotime($post['created_at'])); ?>
                                <i class="far fa-eye ml-2"></i> <?php echo $post['view_count']; ?> views
                            </span>
                        </div>
                        
                        <div class="post-content">
                            <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Post Details</h6>
                            </div>
                            <div class="card-body">
                                <p><strong>Slug:</strong> <?php echo htmlspecialchars($post['slug']); ?></p>
                                <p><strong>Category:</strong> <?php echo htmlspecialchars($post['category'] ?? 'Uncategorized'); ?></p>
                                
                                <?php if (!empty($post['tags'])): ?>
                                    <div class="mb-2">
                                        <strong>Tags:</strong><br>
                                        <?php 
                                        $tags = explode(',', $post['tags']);
                                        foreach ($tags as $tag): 
                                            $tag = trim($tag);
                                            if (!empty($tag)): ?>
                                                <span class="badge badge-secondary"><?php echo htmlspecialchars($tag); ?></span>
                                            <?php endif; 
                                        endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <p><strong>Created:</strong> <?php echo date('M d, Y H:i', strtotime($post['created_at'])); ?></p>
                                <?php if (!empty($post['published_at'])): ?>
                                    <p><strong>Published:</strong> <?php echo date('M d, Y H:i', strtotime($post['published_at'])); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($post['updated_at']) && $post['updated_at'] !== $post['created_at']): ?>
                                    <p><strong>Last Updated:</strong> <?php echo date('M d, Y H:i', strtotime($post['updated_at'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($post['excerpt'])): ?>
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Excerpt</h6>
                            </div>
                            <div class="card-body">
                                <?php echo nl2br(htmlspecialchars($post['excerpt'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($post['meta_title']) || !empty($post['meta_description'])): ?>
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">SEO Information</h6>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($post['meta_title'])): ?>
                                    <p><strong>Meta Title:</strong> <?php echo htmlspecialchars($post['meta_title']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($post['meta_description'])): ?>
                                    <p><strong>Meta Description:</strong> <?php echo htmlspecialchars($post['meta_description']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <a href="#" class="btn btn-primary" data-dismiss="modal" data-toggle="modal" data-target="#editPostModal<?php echo $post['id']; ?>">
                    <i class="fas fa-edit"></i> Edit Post
                </a>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Scripts -->
<?php require_once 'includes/scripts.php'; ?>

</body>

</html>
