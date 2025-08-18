<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/includes/layout.php';

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: /syntaxtrust/admin/index.php');
    exit();
}

// Include database config
require_once __DIR__ . '/../config/database.php';

// Handle login form submission
$login_error = '';
// Optional info message (e.g., after logout)
$info_message = '';
if (isset($_GET['logged_out']) && $_GET['logged_out'] === '1') {
    $info_message = 'Anda telah keluar dari sesi.';
}
// CSRF token for login form
if (empty($_SESSION['csrf_login'])) {
    try { $_SESSION['csrf_login'] = bin2hex(random_bytes(32)); } catch (Exception $e) { $_SESSION['csrf_login'] = bin2hex(openssl_random_pseudo_bytes(32)); }
}
$csrf_login = $_SESSION['csrf_login'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    $csrf = $_POST['csrf_login'] ?? '';
    if (empty($csrf) || empty($_SESSION['csrf_login']) || !hash_equals($_SESSION['csrf_login'], $csrf)) {
        $login_error = 'Sesi tidak valid. Muat ulang halaman.';
    } else {
        // Single-use token
        unset($_SESSION['csrf_login']);
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
    
        if (empty($email) || empty($password)) {
            $login_error = 'Please fill in all fields';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, username, email, password_hash, full_name FROM users WHERE email = ? AND is_active = 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($password, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['user_username'] = $user['username'];
                    $_SESSION['user_email'] = $user['email'];

                    // Set session timeout (30 minutes)
                    $_SESSION['LAST_ACTIVITY'] = time();

                    // Remember me
                    $remember = isset($_POST['remember']) && $_POST['remember'] === 'on';
                    if ($remember) {
                        try {
                            $selector = bin2hex(random_bytes(9));
                            $validator = bin2hex(random_bytes(32));
                        } catch (Exception $e) {
                            $selector = bin2hex(openssl_random_pseudo_bytes(9));
                            $validator = bin2hex(openssl_random_pseudo_bytes(32));
                        }
                        $validator_hash = hash('sha256', $validator);
                        $expires = new DateTime('+30 days');
                        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

                        // Optional: clean old tokens for this user
                        try {
                            $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ? AND expires_at < NOW()')->execute([$user['id']]);
                        } catch (PDOException $e) {}

                        // Insert token
                        $stmtTok = $pdo->prepare('INSERT INTO remember_tokens (user_id, selector, validator_hash, user_agent, ip_address, expires_at) VALUES (?,?,?,?,?,?)');
                        $stmtTok->execute([$user['id'], $selector, $validator_hash, $ua, $ip, $expires->format('Y-m-d H:i:s')]);

                        // Set cookie
                        $cookieValue = $selector . ':' . $validator;
                        $cookieParams = [
                            'expires' => $expires->getTimestamp(),
                            'path' => '/syntaxtrust',
                            'domain' => '',
                            'secure' => false, // set true on HTTPS
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ];
                        setcookie('SYNTRUST_REMEMBER', $cookieValue, $cookieParams);
                    }

                    header('Location: /syntaxtrust/admin/index.php');
                    exit();
                } else {
                    $login_error = 'Invalid email or password';
                }
            } catch (PDOException $e) {
                $login_error = 'Database error occurred. Please try again.';
            }
        }
    }
}
?>
<?php echo renderPageStart('Masuk | SyntaxTrust', 'Masuk untuk mengelola konten situs', ''); ?>

<div class="relative isolate">
  <div class="pointer-events-none absolute inset-0 -z-10 bg-gradient-to-b from-primary/10 via-transparent to-transparent"></div>
  <div class="mx-auto flex max-w-7xl items-center justify-center px-4 py-12 sm:py-16">
    <div class="w-full max-w-md">
      <div class="mb-8 text-center">
        <h1 class="mt-2 text-2xl font-bold tracking-tight">Masuk ke Akun</h1>
        <p class="mt-2 text-sm text-slate-500">Silakan masukkan email dan kata sandi Anda.</p>
      </div>

      <?php if ($info_message): ?>
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">
          <?php echo htmlspecialchars($info_message); ?>
        </div>
      <?php endif; ?>

      <?php if ($login_error): ?>
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">
          <?php echo htmlspecialchars($login_error); ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="login.php" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <input type="hidden" name="csrf_login" value="<?php echo htmlspecialchars($csrf_login, ENT_QUOTES, 'UTF-8'); ?>" />
        <label class="block text-sm font-medium">Email</label>
        <input type="email" name="email" id="email" required autocomplete="email" placeholder="nama@contoh.com"
               class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-900" />

        <div class="mt-4">
          <label class="block text-sm font-medium">Kata Sandi</label>
          <input type="password" name="password" id="password" required placeholder="••••••••"
                 class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-900" />
        </div>

        <div class="mt-4 flex items-center justify-between">
          <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" id="remember" name="remember" class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
            <span>Ingat saya</span>
          </label>
          <a href="#" class="text-sm text-primary hover:underline">Lupa kata sandi?</a>
        </div>

        <button type="submit" class="mt-6 inline-flex w-full items-center justify-center rounded-lg bg-primary px-4 py-2 text-white shadow-soft hover:brightness-110">Masuk</button>

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <a href="#" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
            <svg class="h-4 w-4" viewBox="0 0 533.5 544.3"><path fill="#4285F4" d="M533.5 278.4c0-18.5-1.5-37-4.7-54.8H272v103.8h147.3c-6.4 34.7-26.6 64.1-56.6 83.7v69.4h91.5c53.6-49.3 79.3-122 79.3-202.1z"/><path fill="#34A853" d="M272 544.3c73.6 0 135.4-24.4 180.5-66.2l-91.5-69.4c-25.4 17.1-58 27-89 27-68.3 0-126.3-46.1-147.1-108.1H31.8v67.9C76.4 494.5 168.4 544.3 272 544.3z"/><path fill="#FBBC05" d="M124.9 327.6c-10.3-30.4-10.3-63.5 0-93.9V165.8H31.8c-42.7 84.7-42.7 184.1 0 268l93.1-69.2z"/><path fill="#EA4335" d="M272 107.7c39.9-.6 78.7 14.1 108.1 41.3l80.5-80.5C406.9 20.2 340.9-3.6 272 0 168.4 0 76.4 49.8 31.8 128.1l93.1 69.6C145.7 153.8 203.7 107.7 272 107.7z"/></svg>
            <span>Login Google</span>
          </a>
          <a href="#" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
            <svg class="h-4 w-4" viewBox="0 0 320 512"><path fill="#1877F2" d="M279.14 288l14.22-92.66h-88.91V127.77c0-25.35 12.42-50.06 52.24-50.06H297V6.26S262.43 0 231.36 0c-73.22 0-121.08 44.38-121.08 124.72v70.62H22.89V288h87.39v224h107.8V288z"/></svg>
            <span>Login Facebook</span>
          </a>
        </div>

        <p class="mt-6 text-center text-sm text-slate-500">Belum punya akun? <a href="#" class="text-primary hover:underline">Daftar</a></p>
      </form>
    </div>
  </div>
</div>

<?php echo renderPageEnd(); ?>
