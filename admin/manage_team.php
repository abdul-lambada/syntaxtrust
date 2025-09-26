<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// Upload dir
define('TEAM_UPLOAD_DIR', __DIR__ . '/../uploads/team/');

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function verify_csrf(): bool {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Auth
if (!isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/app.php';
    $publicBase = defined('PUBLIC_BASE_PATH') ? PUBLIC_BASE_PATH : '';
    header('Location: ' . rtrim($publicBase, '/') . '/login.php');
    exit();
}

// Upload helper
function upload_team_image($input, $current = null) {
    if (!isset($_FILES[$input]) || $_FILES[$input]['error'] !== UPLOAD_ERR_OK) {
        return $current;
    }
    $allowed_ext = ['jpg','jpeg','png','webp'];
    $max_bytes = 2*1024*1024;
    if (!is_dir(TEAM_UPLOAD_DIR)) { mkdir(TEAM_UPLOAD_DIR, 0777, true); }
    $tmp = $_FILES[$input]['tmp_name'];
    $name = $_FILES[$input]['name'];
    $size = (int)$_FILES[$input]['size'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($size <= 0 || $size > $max_bytes) throw new RuntimeException('Image too large or empty (max 2MB).');
    if (!in_array($ext, $allowed_ext, true)) throw new RuntimeException('Invalid image type. Allowed: jpg, jpeg, png, webp.');
    if (!is_uploaded_file($tmp)) throw new RuntimeException('Invalid upload.');
    if (function_exists('finfo_open')) { $f=finfo_open(FILEINFO_MIME_TYPE); $mime=finfo_file($f,$tmp); finfo_close($f); $allowed=['image/jpeg','image/png','image/webp']; if(!in_array($mime,$allowed,true)) throw new RuntimeException('Invalid image MIME type.'); }
    $new = uniqid('team_', true) . '_' . time() . '.' . $ext;
    $dest = rtrim(TEAM_UPLOAD_DIR,'/\\') . DIRECTORY_SEPARATOR . $new;
    if (!move_uploaded_file($tmp, $dest)) throw new RuntimeException('Failed to move uploaded image.');
    if ($current) { $base = realpath(rtrim(TEAM_UPLOAD_DIR,'/\\')); $old = realpath($current); if ($base && $old && strpos($old,$base)===0 && is_file($old)) { @unlink($old); } }
    // Return relative path for database storage
    return 'uploads/team/' . $new;
}

// Flash
$message = '';
$message_type = '';

// Create
if (isset($_POST['create_member']) && verify_csrf()) {
    $name = trim($_POST['name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $bio = $_POST['bio'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $social_links_raw = trim($_POST['social_links'] ?? '');
    $skills_raw = trim($_POST['skills'] ?? '');
    $experience_years = ($_POST['experience_years'] !== '' ? (int)$_POST['experience_years'] : null);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $sort_order = ($_POST['sort_order'] !== '' ? max(0,(int)$_POST['sort_order']) : 0);

    if ($name === '' || $position === '') { $message='Name and position are required.'; $message_type='danger'; }
    else {
        try {
            $profile_image = upload_team_image('profile_image');
            // Normalize JSON fields
            $social_links = null; if ($social_links_raw !== '') { $decoded = json_decode($social_links_raw, true); if (json_last_error() === JSON_ERROR_NONE) { $social_links = json_encode($decoded, JSON_UNESCAPED_SLASHES); } else { throw new RuntimeException('Invalid JSON for social_links.'); } }
            $skills = null; if ($skills_raw !== '') { $decoded = json_decode($skills_raw, true); if (json_last_error() === JSON_ERROR_NONE) { $skills = json_encode($decoded, JSON_UNESCAPED_UNICODE); } else { throw new RuntimeException('Invalid JSON for skills.'); } }
            $stmt = $pdo->prepare('INSERT INTO team (name, position, bio, email, phone, profile_image, social_links, skills, experience_years, is_active, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$name,$position,$bio,$email,$phone,$profile_image,$social_links,$skills,$experience_years,$is_active,$sort_order]);
            $message='Team member created successfully!'; $message_type='success';
        } catch (Throwable $e) { $message='Error creating member: '.$e->getMessage(); $message_type='danger'; }
    }
}

// Update
if (isset($_POST['update_member']) && verify_csrf()) {
    $id = (int)($_POST['member_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $bio = $_POST['bio'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $social_links_raw = trim($_POST['social_links'] ?? '');
    $skills_raw = trim($_POST['skills'] ?? '');
    $experience_years = ($_POST['experience_years'] !== '' ? (int)$_POST['experience_years'] : null);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $sort_order = ($_POST['sort_order'] !== '' ? max(0,(int)$_POST['sort_order']) : 0);

    if ($name === '' || $position === '') { $message='Name and position are required.'; $message_type='danger'; }
    else {
        try {
            $stmt = $pdo->prepare('SELECT profile_image FROM team WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $current = $row['profile_image'] ?? null;
            $profile_image = upload_team_image('profile_image', $current);
            $social_links = null; if ($social_links_raw !== '') { $decoded = json_decode($social_links_raw, true); if (json_last_error() === JSON_ERROR_NONE) { $social_links = json_encode($decoded, JSON_UNESCAPED_SLASHES); } else { throw new RuntimeException('Invalid JSON for social_links.'); } }
            $skills = null; if ($skills_raw !== '') { $decoded = json_decode($skills_raw, true); if (json_last_error() === JSON_ERROR_NONE) { $skills = json_encode($decoded, JSON_UNESCAPED_UNICODE); } else { throw new RuntimeException('Invalid JSON for skills.'); } }
            $stmt = $pdo->prepare('UPDATE team SET name=?, position=?, bio=?, email=?, phone=?, profile_image=?, social_links=?, skills=?, experience_years=?, is_active=?, sort_order=? WHERE id=?');
            $stmt->execute([$name,$position,$bio,$email,$phone,$profile_image,$social_links,$skills,$experience_years,$is_active,$sort_order,$id]);
            $message='Team member updated successfully!'; $message_type='success';
        } catch (Throwable $e) { $message='Error updating member: '.$e->getMessage(); $message_type='danger'; }
    }
}

// Delete
if (isset($_POST['delete_member']) && verify_csrf()) {
    $id = (int)($_POST['member_id'] ?? 0);
    try {
        $stmt = $pdo->prepare('SELECT profile_image FROM team WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['profile_image'])) { $base = realpath(rtrim(TEAM_UPLOAD_DIR,'/\\')); $target = realpath($row['profile_image']); if ($base && $target && strpos($target,$base)===0 && is_file($target)) { @unlink($target); } }
        $stmt = $pdo->prepare('DELETE FROM team WHERE id = ?');
        $stmt->execute([$id]);
        $message='Team member deleted successfully!'; $message_type='success';
    } catch (Throwable $e) { $message='Error deleting member: '.$e->getMessage(); $message_type='danger'; }
}

// Toggle active
if (isset($_POST['toggle_active']) && verify_csrf()) {
    $id = (int)$_POST['member_id'];
    $to = (int)$_POST['to'];
    $stmt = $pdo->prepare('UPDATE team SET is_active=? WHERE id=?');
    $stmt->execute([$to,$id]);
}

// Filters & pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_active = isset($_GET['active']) && $_GET['active'] !== '' ? (int)$_GET['active'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$allowed_limits = [10,25,50,100];
if (!in_array($limit, $allowed_limits, true)) { $limit = 10; }

$w = [];$params=[];
if ($search !== '') { $w[]='(name LIKE ? OR position LIKE ? OR email LIKE ? OR phone LIKE ?)'; $like="%$search%"; array_push($params,$like,$like,$like,$like); }
if ($filter_active !== '') { $w[] = 'is_active = ?'; $params[] = $filter_active; }
$where = $w ? ('WHERE '.implode(' AND ', $w)) : '';

$stmt = $pdo->prepare("SELECT COUNT(*) c FROM team $where");
$stmt->execute($params);
$total_records = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
$total_pages = max(1, (int)ceil($total_records / $limit));
if ($page < 1) $page = 1; if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;
$showing_start = $total_records > 0 ? ($offset + 1) : 0;
$showing_end = min($offset + $limit, $total_records);

$sql = "SELECT * FROM team $where ORDER BY sort_order ASC, name ASC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<body id="page-top">
<div id="wrapper">
    <?php require_once 'includes/sidebar.php'; ?>
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <?php require_once 'includes/topbar.php'; ?>
            <div class="container-fluid">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Kelola Tim</h1>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#addMemberModal"><i class="fas fa-plus fa-sm text-white-50"></i> Tambah Anggota</button>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <?php endif; ?>

                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Daftar Tim (<?php echo (int)$total_records; ?> total)</h6>
                        <form method="GET" action="" class="form-inline m-0">
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <input type="hidden" name="page" value="1">
                            <label class="mr-2 mb-0">Show</label>
                            <select name="limit" class="form-control form-control-sm" onchange="this.form.submit()">
                                <?php foreach ($allowed_limits as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo ($limit===$opt)?'selected':''; ?>><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="ml-2">entries</span>
                        </form>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="form-inline mb-3">
                            <input type="hidden" name="limit" value="<?php echo (int)$limit; ?>">
                            <label class="mb-0 mr-2">Cari:</label>
                            <input type="text" name="search" class="form-control form-control-sm mr-3" placeholder="Nama/Jabatan/Email/Telepon" value="<?php echo htmlspecialchars($search); ?>">
                            <label class="mb-0 mr-2">Aktif:</label>
                            <select name="active" class="form-control form-control-sm" onchange="this.form.submit()">
                                <option value="">All</option>
                                <option value="1" <?php echo ($filter_active===1?'selected':''); ?>>Active</option>
                                <option value="0" <?php echo ($filter_active===0?'selected':''); ?>>Inactive</option>
                            </select>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Name</th>
                                        <th>Position</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Active</th>
                                        <th>Sort</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($members): foreach ($members as $m): ?>
                                    <tr>
                                        <td style="width:70px"><img src="<?php echo htmlspecialchars(assetUrlAdmin(!empty($m['profile_image']) ? $m['profile_image'] : 'assets/img/placeholder.png')); ?>" alt="img" width="60"></td>
                                        <td class="font-weight-bold"><?php echo htmlspecialchars($m['name']); ?></td>
                                        <td><?php echo htmlspecialchars($m['position']); ?></td>
                                        <td><?php echo htmlspecialchars($m['email']); ?></td>
                                        <td><?php echo htmlspecialchars($m['phone']); ?></td>
                                        <td>
                                            <form method="POST" action="" style="display:inline-block;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="member_id" value="<?php echo (int)$m['id']; ?>">
                                                <input type="hidden" name="to" value="<?php echo (int)!$m['is_active']; ?>">
                                                <button class="btn btn-sm <?php echo $m['is_active'] ? 'btn-success' : 'btn-outline-secondary'; ?>" name="toggle_active" title="Toggle Active">
                                                    <i class="fas fa-power-off"></i>
                                                </button>
                                            </form>
                                        </td>
                                        <td><?php echo (int)$m['sort_order']; ?></td>
                                        <td>
                                            <button class="btn btn-info btn-sm" data-toggle="modal" data-target="#viewMemberModal<?php echo (int)$m['id']; ?>"><i class="fas fa-eye"></i></button>
                                            <button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#editMemberModal<?php echo (int)$m['id']; ?>"><i class="fas fa-edit"></i></button>
                                            <form method="POST" action="" style="display:inline-block;" onsubmit="return confirm('Hapus anggota ini?')">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="member_id" value="<?php echo (int)$m['id']; ?>">
                                                <button type="submit" name="delete_member" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; else: ?>
                                    <tr><td colspan="8" class="text-center text-muted">No team members found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div>Menampilkan <?php echo (int)$showing_start; ?> - <?php echo (int)$showing_end; ?> dari <?php echo (int)$total_records; ?> data</div>
                            <nav>
                                <ul class="pagination mb-0">
                                    <?php $prev=max(1,$page-1); $next=min($total_pages,$page+1); ?>
                                    <li class="page-item <?php echo ($page<=1)?'disabled':''; ?>">
                                        <a class="page-link" href="?page=<?php echo $prev; ?>&search=<?php echo urlencode($search); ?>&limit=<?php echo (int)$limit; ?>&active=<?php echo urlencode((string)$filter_active); ?>">Sebelumnya</a>
                                    </li>
                                    <?php for($i=1;$i<=$total_pages;$i++): ?>
                                    <li class="page-item <?php echo ($i==$page)?'active':''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&limit=<?php echo (int)$limit; ?>&active=<?php echo urlencode((string)$filter_active); ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo ($page>=$total_pages)?'disabled':''; ?>">
                                        <a class="page-link" href="?page=<?php echo $next; ?>&search=<?php echo urlencode($search); ?>&limit=<?php echo (int)$limit; ?>&active=<?php echo urlencode((string)$filter_active); ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php require_once 'includes/footer.php'; ?>
    </div>
</div>

<!-- Add Member Modal -->
<div class="modal fade" id="addMemberModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Tambah Anggota</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
      <div class="modal-body">
        <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Nama</label>
              <input type="text" name="name" class="form-control" required>
              <div class="invalid-feedback">Name is required.</div>
            </div>
            <div class="form-group col-md-6">
              <label>Jabatan</label>
              <input type="text" name="position" class="form-control" required>
              <div class="invalid-feedback">Position is required.</div>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Email</label>
              <input type="email" name="email" class="form-control">
            </div>
            <div class="form-group col-md-6">
              <label>Telepon</label>
              <input type="text" name="phone" class="form-control">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Foto Profil</label>
              <input type="file" name="profile_image" class="form-control-file" accept="image/png,image/jpeg,image/webp">
              <small class="form-text text-muted">Allowed: JPG, PNG, WEBP. Max 2MB.</small>
            </div>
            <div class="form-group col-md-6">
              <label>Pengalaman (tahun)</label>
              <input type="number" name="experience_years" class="form-control" min="0">
            </div>
          </div>
          <div class="form-group">
            <label>Bio</label>
            <textarea name="bio" class="form-control summernote"></textarea>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Social Links (JSON)</label>
              <textarea name="social_links" class="form-control" placeholder='{"linkedin":"...","github":"..."}'></textarea>
            </div>
            <div class="form-group col-md-6">
              <label>Skills (JSON array)</label>
              <textarea name="skills" class="form-control" placeholder='["PHP","React"]'></textarea>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-4">
              <label>Sort Order</label>
              <input type="number" name="sort_order" class="form-control" value="0" min="0">
            </div>
            <div class="form-group col-md-4">
              <label class="d-block">Active</label>
              <div class="form-check"><input type="checkbox" name="is_active" class="form-check-input" id="add_is_active" checked><label class="form-check-label" for="add_is_active">Active</label></div>
            </div>
          </div>
          <button type="submit" name="create_member" class="btn btn-primary">Simpan Anggota</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($members)): foreach ($members as $m): ?>
<!-- Edit Member Modal -->
<div class="modal fade" id="editMemberModal<?php echo (int)$m['id']; ?>" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Ubah Anggota</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
      <div class="modal-body">
        <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="member_id" value="<?php echo (int)$m['id']; ?>">
          <div class="form-row">
            <div class="form-group col-md-6"><label>Name</label><input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($m['name']); ?>" required><div class="invalid-feedback">Name is required.</div></div>
            <div class="form-group col-md-6"><label>Position</label><input type="text" name="position" class="form-control" value="<?php echo htmlspecialchars($m['position']); ?>" required><div class="invalid-feedback">Position is required.</div></div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6"><label>Email</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($m['email']); ?>"></div>
            <div class="form-group col-md-6"><label>Phone</label><input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($m['phone']); ?>"></div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Current Image</label>
              <div><img src="<?php echo htmlspecialchars(assetUrlAdmin(!empty($m['profile_image']) ? $m['profile_image'] : 'assets/img/placeholder.png')); ?>" width="100"></div>
              <label class="mt-2">New Image</label>
              <input type="file" name="profile_image" class="form-control-file" accept="image/png,image/jpeg,image/webp">
              <small class="form-text text-muted">Allowed: JPG, PNG, WEBP. Max 2MB.</small>
            </div>
            <div class="form-group col-md-6"><label>Experience Years</label><input type="number" name="experience_years" class="form-control" min="0" value="<?php echo htmlspecialchars($m['experience_years']); ?>"></div>
          </div>
          <div class="form-group"><label>Bio</label><textarea name="bio" class="form-control summernote"><?php echo htmlspecialchars($m['bio']); ?></textarea></div>
          <div class="form-row">
            <div class="form-group col-md-6"><label>Social Links (JSON)</label><textarea name="social_links" class="form-control"><?php echo htmlspecialchars($m['social_links']); ?></textarea></div>
            <div class="form-group col-md-6"><label>Skills (JSON array)</label><textarea name="skills" class="form-control"><?php echo htmlspecialchars($m['skills']); ?></textarea></div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-4"><label>Sort Order</label><input type="number" name="sort_order" class="form-control" min="0" value="<?php echo (int)$m['sort_order']; ?>"></div>
            <div class="form-group col-md-4"><label class="d-block">Active</label><div class="form-check"><input type="checkbox" name="is_active" class="form-check-input" id="edit_is_active_<?php echo (int)$m['id']; ?>" <?php echo $m['is_active']?'checked':''; ?>><label class="form-check-label" for="edit_is_active_<?php echo (int)$m['id']; ?>">Active</label></div></div>
          </div>
          <button type="submit" name="update_member" class="btn btn-primary">Update Member</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- View Member Modal -->
<div class="modal fade" id="viewMemberModal<?php echo (int)$m['id']; ?>" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">View Member</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
      <div class="modal-body">
        <div class="mb-3 text-center"><img src="<?php echo htmlspecialchars(assetUrlAdmin(!empty($m['profile_image']) ? $m['profile_image'] : 'assets/img/placeholder.png')); ?>" alt="<?php echo htmlspecialchars($m['name']); ?>" style="max-height: 120px; width: auto;" /></div>
        <h5><?php echo htmlspecialchars($m['name']); ?></h5>
        <p class="mb-1"><strong>Position:</strong> <?php echo htmlspecialchars($m['position']); ?></p>
        <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($m['email']); ?></p>
        <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($m['phone']); ?></p>
        <p class="mb-1"><strong>Experience:</strong> <?php echo htmlspecialchars($m['experience_years']); ?> years</p>
        <p class="mb-1"><strong>Active:</strong> <?php echo $m['is_active'] ? 'Yes' : 'No'; ?></p>
        <div class="mb-1"><strong>Skills (JSON):</strong> <code><?php echo htmlspecialchars($m['skills']); ?></code></div>
        <div class="mb-1"><strong>Social Links (JSON):</strong> <code><?php echo htmlspecialchars($m['social_links']); ?></code></div>
        <div><strong>Bio:</strong> <?php echo $m['bio']; ?></div>
      </div>
    </div>
  </div>
</div>
<?php endforeach; endif; ?>

<?php require_once 'includes/scripts.php'; ?>
<script>
$(document).ready(function(){
  $('.summernote').summernote({ height: 150, toolbar: [ ['style',['style']], ['font',['bold','italic','underline','clear']], ['para',['ul','ol','paragraph']], ['table',['table']] ] });
  Array.prototype.slice.call(document.querySelectorAll('form.needs-validation')).forEach(function(form){
    form.addEventListener('submit', function (event) { if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); } form.classList.add('was-validated'); }, false);
  });
});
</script>
</body>
</html>
