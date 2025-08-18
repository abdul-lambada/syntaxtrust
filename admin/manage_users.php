<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// Upload dir for user profile images
define('USER_UPLOAD_DIR', __DIR__ . '/../uploads/users/');

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function verify_csrf(): bool {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: /syntaxtrust/login.php');
    exit();
}

// Upload helper
function upload_user_image($input_name, $current_path = null) {
    if (!isset($_FILES[$input_name]) || $_FILES[$input_name]['error'] !== UPLOAD_ERR_OK) {
        return $current_path;
    }
    $allowed_ext = ['jpg','jpeg','png','webp'];
    $max_bytes = 2 * 1024 * 1024;
    if (!is_dir(USER_UPLOAD_DIR)) { mkdir(USER_UPLOAD_DIR, 0777, true); }
    $tmp = $_FILES[$input_name]['tmp_name'];
    $name = $_FILES[$input_name]['name'];
    $size = (int)$_FILES[$input_name]['size'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($size <= 0 || $size > $max_bytes) throw new RuntimeException('Image too large or empty (max 2MB).');
    if (!in_array($ext, $allowed_ext, true)) throw new RuntimeException('Invalid image type. Allowed: jpg, jpeg, png, webp.');
    if (!is_uploaded_file($tmp)) throw new RuntimeException('Invalid upload.');
    if (function_exists('finfo_open')) { $f=finfo_open(FILEINFO_MIME_TYPE); $mime=finfo_file($f,$tmp); finfo_close($f); $allowed=['image/jpeg','image/png','image/webp']; if(!in_array($mime,$allowed,true)) throw new RuntimeException('Invalid image MIME type.'); }
    $new = uniqid('user_', true) . '_' . time() . '.' . $ext;
    $dest = rtrim(USER_UPLOAD_DIR,'/\\') . DIRECTORY_SEPARATOR . $new;
    if (!move_uploaded_file($tmp, $dest)) throw new RuntimeException('Failed to move uploaded image.');
    if ($current_path) { $base = realpath(rtrim(USER_UPLOAD_DIR,'/\\')); $old = realpath($current_path); if ($base && $old && strpos($old,$base)===0 && is_file($old)) { @unlink($old); } }
    // Return relative path for database storage
    return 'uploads/users/' . $new;
}

// Flash message
$message = '';$message_type='';

// Create user
if (isset($_POST['create_user']) && verify_csrf()) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $user_type = $_POST['user_type'] ?? 'mahasiswa';
    $bio = $_POST['bio'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $email_verified = isset($_POST['email_verified']) ? 1 : 0;

    if ($username === '' || $email === '' || $password === '') { $message='Username, Email, and Password are required.'; $message_type='danger'; }
    else {
        try {
            // Uniqueness checks
            $stmt = $pdo->prepare('SELECT COUNT(*) c FROM users WHERE username = ? OR email = ?');
            $stmt->execute([$username,$email]);
            if ((int)$stmt->fetch(PDO::FETCH_ASSOC)['c'] > 0) { throw new RuntimeException('Username or Email already exists.'); }
            $profile_image = upload_user_image('profile_image');
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (username,email,password_hash,full_name,phone,user_type,profile_image,bio,is_active,email_verified) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$username,$email,$password_hash,$full_name,$phone,$user_type,$profile_image,$bio,$is_active,$email_verified]);
            $message='User created successfully!'; $message_type='success';
        } catch (Throwable $e) { $message='Error creating user: '.$e->getMessage(); $message_type='danger'; }
    }
}

// Update user
if (isset($_POST['update_user']) && verify_csrf()) {
    $id = (int)($_POST['user_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? ''); // optional
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $user_type = $_POST['user_type'] ?? 'mahasiswa';
    $bio = $_POST['bio'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $email_verified = isset($_POST['email_verified']) ? 1 : 0;

    if ($username === '' || $email === '') { $message='Username and Email are required.'; $message_type='danger'; }
    else {
        try {
            // Get current values
            $stmt = $pdo->prepare('SELECT profile_image FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $current = $row['profile_image'] ?? null;
            $profile_image = upload_user_image('profile_image', $current);
            // Enforce unique username/email for others
            $stmt = $pdo->prepare('SELECT COUNT(*) c FROM users WHERE (username = ? OR email = ?) AND id <> ?');
            $stmt->execute([$username,$email,$id]);
            if ((int)$stmt->fetch(PDO::FETCH_ASSOC)['c'] > 0) { throw new RuntimeException('Username or Email already in use by another account.'); }
            if ($password !== '') { $password_hash = password_hash($password, PASSWORD_DEFAULT); $stmt = $pdo->prepare('UPDATE users SET username=?, email=?, password_hash=?, full_name=?, phone=?, user_type=?, profile_image=?, bio=?, is_active=?, email_verified=? WHERE id=?'); $stmt->execute([$username,$email,$password_hash,$full_name,$phone,$user_type,$profile_image,$bio,$is_active,$email_verified,$id]); }
            else { $stmt = $pdo->prepare('UPDATE users SET username=?, email=?, full_name=?, phone=?, user_type=?, profile_image=?, bio=?, is_active=?, email_verified=? WHERE id=?'); $stmt->execute([$username,$email,$full_name,$phone,$user_type,$profile_image,$bio,$is_active,$email_verified,$id]); }
            $message='User updated successfully!'; $message_type='success';
        } catch (Throwable $e) { $message='Error updating user: '.$e->getMessage(); $message_type='danger'; }
    }
}

// Delete user
if (isset($_POST['delete_user']) && verify_csrf()) {
    $id = (int)($_POST['user_id'] ?? 0);
    try {
        $stmt = $pdo->prepare('SELECT profile_image FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['profile_image'])) { $base = realpath(rtrim(USER_UPLOAD_DIR,'/\\')); $target = realpath($row['profile_image']); if ($base && $target && strpos($target,$base)===0 && is_file($target)) { @unlink($target); } }
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $message='User deleted successfully!'; $message_type='success';
    } catch (Throwable $e) { $message='Error deleting user: '.$e->getMessage(); $message_type='danger'; }
}

// Toggle active / email_verified
if (isset($_POST['toggle_active']) && verify_csrf()) {
    $id = (int)$_POST['user_id']; $to = (int)$_POST['to']; $stmt = $pdo->prepare('UPDATE users SET is_active=? WHERE id=?'); $stmt->execute([$to,$id]);
}
if (isset($_POST['toggle_verified']) && verify_csrf()) {
    $id = (int)$_POST['user_id']; $to = (int)$_POST['to']; $stmt = $pdo->prepare('UPDATE users SET email_verified=? WHERE id=?'); $stmt->execute([$to,$id]);
}

// Search, filters, pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_active = isset($_GET['active']) && $_GET['active'] !== '' ? (int)$_GET['active'] : '';
$filter_role = isset($_GET['role']) && $_GET['role'] !== '' ? $_GET['role'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$allowed_limits = [10,25,50,100]; if (!in_array($limit,$allowed_limits,true)) $limit = 10;

$w=[]; $params=[];
if ($search !== '') { $w[]='(username LIKE ? OR email LIKE ? OR full_name LIKE ? OR phone LIKE ?)'; $like="%$search%"; array_push($params,$like,$like,$like,$like); }
if ($filter_active !== '') { $w[]='is_active = ?'; $params[]=$filter_active; }
if ($filter_role !== '') { $w[]='user_type = ?'; $params[]=$filter_role; }
$where = $w ? ('WHERE '.implode(' AND ',$w)) : '';

$stmt = $pdo->prepare("SELECT COUNT(*) c FROM users $where"); $stmt->execute($params); $total_records = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
$total_pages = max(1, (int)ceil($total_records / $limit)); if ($page<1) $page=1; if ($page>$total_pages) $page=$total_pages; $offset = ($page-1)*$limit;
$showing_start = $total_records>0?($offset+1):0; $showing_end = min($offset+$limit, $total_records);

$sql = "SELECT * FROM users $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                    <h1 class="h3 mb-0 text-gray-800">Manage Users</h1>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#addUserModal"><i class="fas fa-plus fa-sm text-white-50"></i> Add New User</button>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <?php endif; ?>

                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Users (<?php echo (int)$total_records; ?> total)</h6>
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
                            <label class="mb-0 mr-2">Search:</label>
                            <input type="text" name="search" class="form-control form-control-sm mr-3" placeholder="Username/Email/Name/Phone" value="<?php echo htmlspecialchars($search); ?>">
                            <label class="mb-0 mr-2">Role:</label>
                            <select name="role" class="form-control form-control-sm mr-3" onchange="this.form.submit()">
                                <option value="">All</option>
                                <?php foreach (['mahasiswa','bisnis','admin'] as $r): ?>
                                <option value="<?php echo $r; ?>" <?php echo ($filter_role===$r?'selected':''); ?>><?php echo ucfirst($r); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label class="mb-0 mr-2">Active:</label>
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
                                        <th>Avatar</th>
                                        <th>Username</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Role</th>
                                        <th>Active</th>
                                        <th>Verified</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($users): foreach ($users as $u): ?>
                                    <tr>
                                        <td style="width:70px"><img src="<?php echo !empty($u['profile_image']) ? htmlspecialchars($u['profile_image']) : 'assets/img/placeholder.png'; ?>" alt="img" width="60"></td>
                                        <td class="font-weight-bold">@<?php echo htmlspecialchars($u['username']); ?></td>
                                        <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                                        <td><?php echo htmlspecialchars($u['phone']); ?></td>
                                        <td><span class="badge badge-info text-uppercase"><?php echo htmlspecialchars($u['user_type']); ?></span></td>
                                        <td>
                                            <form method="POST" action="" style="display:inline-block;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                                <input type="hidden" name="to" value="<?php echo (int)!$u['is_active']; ?>">
                                                <button class="btn btn-sm <?php echo $u['is_active'] ? 'btn-success' : 'btn-outline-secondary'; ?>" name="toggle_active" title="Toggle Active"><i class="fas fa-power-off"></i></button>
                                            </form>
                                        </td>
                                        <td>
                                            <form method="POST" action="" style="display:inline-block;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                                <input type="hidden" name="to" value="<?php echo (int)!$u['email_verified']; ?>">
                                                <button class="btn btn-sm <?php echo $u['email_verified'] ? 'btn-primary' : 'btn-outline-secondary'; ?>" name="toggle_verified" title="Toggle Email Verified"><i class="fas fa-envelope"></i></button>
                                            </form>
                                        </td>
                                        <td>
                                            <button class="btn btn-info btn-sm" data-toggle="modal" data-target="#viewUserModal<?php echo (int)$u['id']; ?>"><i class="fas fa-eye"></i></button>
                                            <button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#editUserModal<?php echo (int)$u['id']; ?>"><i class="fas fa-edit"></i></button>
                                            <form method="POST" action="" style="display:inline-block;" onsubmit="return confirm('Delete this user?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                                <button type="submit" name="delete_user" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; else: ?>
                                    <tr><td colspan="9" class="text-center text-muted">No users found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div>Showing <?php echo (int)$showing_start; ?> to <?php echo (int)$showing_end; ?> of <?php echo (int)$total_records; ?> entries</div>
                            <nav>
                                <ul class="pagination mb-0">
                                    <?php $prev=max(1,$page-1); $next=min($total_pages,$page+1); ?>
                                    <li class="page-item <?php echo ($page<=1)?'disabled':''; ?>">
                                        <a class="page-link" href="?page=<?php echo $prev; ?>&search=<?php echo urlencode($search); ?>&limit=<?php echo (int)$limit; ?>&active=<?php echo urlencode((string)$filter_active); ?>&role=<?php echo urlencode((string)$filter_role); ?>">Previous</a>
                                    </li>
                                    <?php for($i=1;$i<=$total_pages;$i++): ?>
                                    <li class="page-item <?php echo ($i==$page)?'active':''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&limit=<?php echo (int)$limit; ?>&active=<?php echo urlencode((string)$filter_active); ?>&role=<?php echo urlencode((string)$filter_role); ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo ($page>=$total_pages)?'disabled':''; ?>">
                                        <a class="page-link" href="?page=<?php echo $next; ?>&search=<?php echo urlencode($search); ?>&limit=<?php echo (int)$limit; ?>&active=<?php echo urlencode((string)$filter_active); ?>&role=<?php echo urlencode((string)$filter_role); ?>">Next</a>
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

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Add New User</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
      <div class="modal-body">
        <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <div class="form-row">
            <div class="form-group col-md-6"><label>Username</label><input type="text" name="username" class="form-control" required><div class="invalid-feedback">Required.</div></div>
            <div class="form-group col-md-6"><label>Email</label><input type="email" name="email" class="form-control" required><div class="invalid-feedback">Required.</div></div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6"><label>Password</label><input type="password" name="password" class="form-control" required minlength="6"><div class="invalid-feedback">Min 6 chars.</div></div>
            <div class="form-group col-md-6"><label>Full Name</label><input type="text" name="full_name" class="form-control"></div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6"><label>Phone</label><input type="text" name="phone" class="form-control"></div>
            <div class="form-group col-md-6"><label>Role</label><select name="user_type" class="form-control"><option value="mahasiswa">Mahasiswa</option><option value="bisnis">Bisnis</option><option value="admin">Admin</option></select></div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6"><label>Profile Image</label><input type="file" name="profile_image" class="form-control-file" accept="image/png,image/jpeg,image/webp"><small class="form-text text-muted">Allowed: JPG, PNG, WEBP. Max 2MB.</small></div>
            <div class="form-group col-md-6"><label class="d-block">Status</label><div class="form-check"><input type="checkbox" name="is_active" class="form-check-input" id="add_is_active" checked><label class="form-check-label" for="add_is_active">Active</label></div><div class="form-check"><input type="checkbox" name="email_verified" class="form-check-input" id="add_verified"><label class="form-check-label" for="add_verified">Email Verified</label></div></div>
          </div>
          <div class="form-group"><label>Bio</label><textarea name="bio" class="form-control summernote"></textarea></div>
          <button type="submit" name="create_user" class="btn btn-primary">Create User</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($users)): foreach ($users as $u): ?>
<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal<?php echo (int)$u['id']; ?>" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Edit User</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
      <div class="modal-body">
        <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
          <div class="form-row">
            <div class="form-group col-md-6"><label>Username</label><input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($u['username']); ?>" required><div class="invalid-feedback">Required.</div></div>
            <div class="form-group col-md-6"><label>Email</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($u['email']); ?>" required><div class="invalid-feedback">Required.</div></div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6"><label>New Password (leave blank to keep)</label><input type="password" name="password" class="form-control" minlength="6"></div>
            <div class="form-group col-md-6"><label>Full Name</label><input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($u['full_name']); ?>"></div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6"><label>Phone</label><input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($u['phone']); ?>"></div>
            <div class="form-group col-md-6"><label>Role</label><select name="user_type" class="form-control">
              <?php foreach (['mahasiswa','bisnis','admin'] as $r): ?><option value="<?php echo $r; ?>" <?php echo ($u['user_type']===$r?'selected':''); ?>><?php echo ucfirst($r); ?></option><?php endforeach; ?>
            </select></div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Current Image</label>
              <div><img src="<?php echo !empty($u['profile_image']) ? htmlspecialchars($u['profile_image']) : 'assets/img/placeholder.png'; ?>" width="100"></div>
              <label class="mt-2">New Image</label>
              <input type="file" name="profile_image" class="form-control-file" accept="image/png,image/jpeg,image/webp">
              <small class="form-text text-muted">Allowed: JPG, PNG, WEBP. Max 2MB.</small>
            </div>
            <div class="form-group col-md-6"><label class="d-block">Status</label><div class="form-check"><input type="checkbox" name="is_active" class="form-check-input" id="edit_active_<?php echo (int)$u['id']; ?>" <?php echo $u['is_active']?'checked':''; ?>><label class="form-check-label" for="edit_active_<?php echo (int)$u['id']; ?>">Active</label></div><div class="form-check"><input type="checkbox" name="email_verified" class="form-check-input" id="edit_verified_<?php echo (int)$u['id']; ?>" <?php echo $u['email_verified']?'checked':''; ?>><label class="form-check-label" for="edit_verified_<?php echo (int)$u['id']; ?>">Email Verified</label></div></div>
          </div>
          <div class="form-group"><label>Bio</label><textarea name="bio" class="form-control summernote"><?php echo htmlspecialchars($u['bio']); ?></textarea></div>
          <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- View User Modal -->
<div class="modal fade" id="viewUserModal<?php echo (int)$u['id']; ?>" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">View User</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
      <div class="modal-body">
        <div class="mb-3 text-center"><img src="<?php echo !empty($u['profile_image']) ? htmlspecialchars($u['profile_image']) : 'assets/img/placeholder.png'; ?>" alt="<?php echo htmlspecialchars($u['username']); ?>" style="max-height: 120px; width: auto;" /></div>
        <h5>@<?php echo htmlspecialchars($u['username']); ?></h5>
        <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($u['full_name']); ?></p>
        <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($u['email']); ?></p>
        <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($u['phone']); ?></p>
        <p class="mb-1"><strong>Role:</strong> <?php echo htmlspecialchars($u['user_type']); ?></p>
        <p class="mb-1"><strong>Active:</strong> <?php echo $u['is_active'] ? 'Yes' : 'No'; ?></p>
        <p class="mb-1"><strong>Verified:</strong> <?php echo $u['email_verified'] ? 'Yes' : 'No'; ?></p>
        <div><strong>Bio:</strong> <?php echo $u['bio']; ?></div>
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
