<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: /syntaxtrust/public/login.php');
    exit();
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function verify_csrf(): bool {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Upload dir for user profile images
define('USER_UPLOAD_DIR', __DIR__ . '/../uploads/users/');

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
    if (function_exists('finfo_open')) {
        $f = finfo_open(FILEINFO_MIME_TYPE); $mime = finfo_file($f, $tmp); finfo_close($f);
        $allowed = ['image/jpeg','image/png','image/webp'];
        if (!in_array($mime, $allowed, true)) throw new RuntimeException('Invalid image MIME type.');
    }
    $new = uniqid('user_', true) . '_' . time() . '.' . $ext;
    $dest = rtrim(USER_UPLOAD_DIR,'/\\') . DIRECTORY_SEPARATOR . $new;
    if (!move_uploaded_file($tmp, $dest)) throw new RuntimeException('Failed to move uploaded image.');
    if ($current_path) {
        $base = realpath(rtrim(USER_UPLOAD_DIR,'/\\'));
        $old = realpath($current_path);
        if ($base && $old && strpos($old,$base)===0 && is_file($old)) { @unlink($old); }
    }
    return 'uploads/users/' . $new;
}

// Load current user
$user = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $user = []; }

$message = '';$message_type='';

// Handle profile update
if (isset($_POST['update_profile']) && verify_csrf()) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $bio = $_POST['bio'] ?? '';

    if ($username === '' || $email === '') { $message='Username and Email are required.'; $message_type='danger'; }
    else {
        try {
            // Ensure unique username/email excluding current user
            $stmt = $pdo->prepare('SELECT COUNT(*) c FROM users WHERE (username = ? OR email = ?) AND id <> ?');
            $stmt->execute([$username,$email,$_SESSION['user_id']]);
            if ((int)$stmt->fetch(PDO::FETCH_ASSOC)['c'] > 0) { throw new RuntimeException('Username or Email already in use.'); }

            // Current image path
            $current = $user['profile_image'] ?? null;
            $profile_image = upload_user_image('profile_image', $current);

            $stmt = $pdo->prepare('UPDATE users SET username=?, email=?, full_name=?, phone=?, profile_image=?, bio=? WHERE id=?');
            $stmt->execute([$username,$email,$full_name,$phone,$profile_image,$bio,$_SESSION['user_id']]);

            // Refresh $user
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: $user;

            $_SESSION['user_name'] = $user['username'] ?? $_SESSION['user_name'];
            $message='Profile updated successfully!'; $message_type='success';
        } catch (Throwable $e) { $message='Error updating profile: '.$e->getMessage(); $message_type='danger'; }
    }
}

// Handle password change
if (isset($_POST['change_password']) && verify_csrf()) {
    $current_password = (string)($_POST['current_password'] ?? '');
    $new_password = (string)($_POST['new_password'] ?? '');
    $confirm_password = (string)($_POST['confirm_password'] ?? '');

    if ($new_password === '' || strlen($new_password) < 6) { $message='New password must be at least 6 characters.'; $message_type='danger'; }
    elseif ($new_password !== $confirm_password) { $message='New password and confirmation do not match.'; $message_type='danger'; }
    else {
        try {
            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $hash = $row['password_hash'] ?? '';
            if ($hash && !password_verify($current_password, $hash)) {
                throw new RuntimeException('Current password is incorrect.');
            }
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');
            $stmt->execute([$new_hash, $_SESSION['user_id']]);
            $message='Password changed successfully!'; $message_type='success';
        } catch (Throwable $e) { $message='Error changing password: '.$e->getMessage(); $message_type='danger'; }
    }
}

// Page title
$pageTitle = 'Profile';
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
                    <h1 class="h3 mb-0 text-gray-800">My Profile</h1>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-7">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Profile Information</h6></div>
                            <div class="card-body">
                                <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label>Username *</label>
                                            <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
                                            <div class="invalid-feedback">Required.</div>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label>Email *</label>
                                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                            <div class="invalid-feedback">Required.</div>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label>Full Name</label>
                                            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label>Phone</label>
                                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Bio</label>
                                        <textarea name="bio" class="form-control" rows="3"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Profile Image</label>
                                        <div class="mb-2">
                                            <img src="<?php echo htmlspecialchars(assetUrlAdmin(!empty($user['profile_image']) ? $user['profile_image'] : 'assets/img/placeholder.png')); ?>" alt="avatar" style="max-height:120px;width:auto;" />
                                        </div>
                                        <input type="file" name="profile_image" class="form-control-file" accept="image/png,image/jpeg,image/webp">
                                        <small class="form-text text-muted">Allowed: JPG, PNG, WEBP. Max 2MB.</small>
                                    </div>
                                    <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Change Password</h6></div>
                            <div class="card-body">
                                <form method="POST" action="" class="needs-validation" novalidate>
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <div class="form-group">
                                        <label>Current Password</label>
                                        <input type="password" name="current_password" class="form-control" required>
                                        <div class="invalid-feedback">Required.</div>
                                    </div>
                                    <div class="form-group">
                                        <label>New Password</label>
                                        <input type="password" name="new_password" class="form-control" minlength="6" required>
                                        <div class="invalid-feedback">Min 6 chars.</div>
                                    </div>
                                    <div class="form-group">
                                        <label>Confirm New Password</label>
                                        <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                                        <div class="invalid-feedback">Must match new password.</div>
                                    </div>
                                    <button type="submit" name="change_password" class="btn btn-warning">Change Password</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php require_once 'includes/footer.php'; ?>
    </div>
</div>
<?php require_once 'includes/scripts.php'; ?>
<script>
$(document).ready(function(){
  Array.prototype.slice.call(document.querySelectorAll('form.needs-validation')).forEach(function(form){
    form.addEventListener('submit', function (event) {
      if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); }
      form.classList.add('was-validated');
    }, false);
  });
});
</script>
</body>
</html>
