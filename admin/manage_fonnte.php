<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle save templates
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_templates'])) {
    $csrf_ok = isset($_POST['csrf_token'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token']);
    if (!$csrf_ok) {
        $message = 'Invalid CSRF token.';
        $message_type = 'danger';
    } else {
        $labels = isset($_POST['tpl_label']) && is_array($_POST['tpl_label']) ? $_POST['tpl_label'] : [];
        $messages = isset($_POST['tpl_message']) && is_array($_POST['tpl_message']) ? $_POST['tpl_message'] : [];
        $out = [];
        $count = max(count($labels), count($messages));
        for ($i = 0; $i < $count; $i++) {
            $lbl = trim((string)($labels[$i] ?? ''));
            $msg = trim((string)($messages[$i] ?? ''));
            if ($lbl !== '' && $msg !== '') {
                $out[] = ['label' => mb_substr($lbl, 0, 100), 'message' => mb_substr($msg, 0, 1000)];
            }
        }
        try {
            $json = json_encode($out, JSON_UNESCAPED_UNICODE);
            $existsStmt = $pdo->prepare('SELECT id FROM settings WHERE setting_key = ? LIMIT 1');
            $existsStmt->execute(['fonnte_templates']);
            $exists = $existsStmt->fetch(PDO::FETCH_ASSOC);
            if ($exists) {
                $upd = $pdo->prepare('UPDATE settings SET setting_value = ?, setting_type = ?, description = ?, is_public = ? WHERE id = ?');
                $upd->execute([$json, 'json', 'Daftar template pesan WhatsApp Fonnte', 0, $exists['id']]);
            } else {
                $ins = $pdo->prepare('INSERT INTO settings (setting_key, setting_value, setting_type, description, is_public) VALUES (?, ?, ?, ?, ?)');
                $ins->execute(['fonnte_templates', $json, 'json', 'Daftar template pesan WhatsApp Fonnte', 0]);
            }
            $templates = $out;
            $message = 'Templates saved successfully!';
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = 'Error saving templates: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/app.php';
    $publicBase = defined('PUBLIC_BASE_PATH') ? PUBLIC_BASE_PATH : '';
    header('Location: ' . rtrim($publicBase, '/') . '/login.php');
    exit();
}

$message = '';
$message_type = '';

// Load current fonnte token
$current_token = '';
$templates = [];
try {
    $stmt = $pdo->prepare("SELECT setting_value, is_public FROM settings WHERE setting_key = ? LIMIT 1");
    $stmt->execute(['fonnte_token']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $current_token = (string)($row['setting_value'] ?? '');
    }
} catch (Throwable $e) {
    $current_token = '';
}

// Load templates
try {
    $tstmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
    $tstmt->execute(['fonnte_templates']);
    $tval = $tstmt->fetchColumn();
    $decoded = json_decode($tval ?: '[]', true);
    if (is_array($decoded)) { $templates = $decoded; }
} catch (Throwable $e) {
    $templates = [];
}

// Handle save token
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_token'])) {
    $csrf_ok = isset($_POST['csrf_token'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token']);
    if (!$csrf_ok) {
        $message = 'Invalid CSRF token.';
        $message_type = 'danger';
    } else {
        $token = trim((string)($_POST['fonnte_token'] ?? ''));
        if ($token === '') {
            $message = 'Fonnte token cannot be empty.';
            $message_type = 'danger';
        } else try {
            // Upsert into settings as non-public secret
            // Ensure key exists
            $existsStmt = $pdo->prepare('SELECT id FROM settings WHERE setting_key = ? LIMIT 1');
            $existsStmt->execute(['fonnte_token']);
            $exists = $existsStmt->fetch(PDO::FETCH_ASSOC);

            if ($exists) {
                $upd = $pdo->prepare('UPDATE settings SET setting_value = ?, setting_type = ?, description = ?, is_public = ? WHERE id = ?');
                $upd->execute([$token, 'text', 'Fonnte API Token (PRIVATE) untuk pengiriman WhatsApp otomatis.', 0, $exists['id']]);
            } else {
                $ins = $pdo->prepare('INSERT INTO settings (setting_key, setting_value, setting_type, description, is_public) VALUES (?, ?, ?, ?, ?)');
                $ins->execute(['fonnte_token', $token, 'text', 'Fonnte API Token (PRIVATE) untuk pengiriman WhatsApp otomatis.', 0]);
            }

            $current_token = $token;
            $message = 'Fonnte token saved successfully!';
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = 'Error saving token: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Include header
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
                        <h1 class="h3 mb-0 text-gray-800">Fonnte Integration</h1>
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Configuration</h6>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <div class="form-group">
                                            <label for="fonnte_token">Fonnte API Token (Private)</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="fonnte_token" name="fonnte_token" value="<?php echo htmlspecialchars($current_token); ?>" placeholder="Enter your Fonnte API token" autocomplete="off">
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-outline-secondary" id="toggleTokenVisibility" title="Show/Hide">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <small class="form-text text-muted">
                                                Token ini disimpan pada tabel <code>settings</code> dengan <code>setting_key</code> = <code>fonnte_token</code> dan status non-public.
                                            </small>
                                        </div>
                                        <button type="submit" name="save_token" class="btn btn-primary">
                                            <i class="fas fa-save mr-1"></i> Simpan Token
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">Send Test Message</h6>
                                    <button id="btnFillSample" class="btn btn-sm btn-secondary">Isi Contoh</button>
                                </div>
                                <div class="card-body">
                                    <form id="testSendForm" onsubmit="return false;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <div class="form-group">
                                            <label for="test_phone">Nomor WhatsApp</label>
                                            <input type="text" class="form-control" id="test_phone" name="phone" placeholder="contoh: 0851xxxxxxxx">
                                        </div>
                                        <div class="form-group">
                                            <label for="test_message">Pesan</label>
                                            <textarea class="form-control" id="test_message" name="message" rows="3" placeholder="Tulis pesan..."></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="test_order_id">Order ID (opsional)</label>
                                            <input type="number" class="form-control" id="test_order_id" name="order_id" placeholder="Masukkan ID order jika ingin dilog ke notifikasi order">
                                        </div>
                                        <button id="btnTestSend" class="btn btn-success">
                                            <i class="fas fa-paper-plane mr-1"></i> Kirim Test
                                        </button>
                                    </form>

                                    <hr>
                                    <div>
                                        <h6 class="font-weight-bold">Hasil</h6>
                                        <pre id="testResult" class="bg-light p-3" style="max-height: 260px; overflow:auto;">Belum ada pengujian.</pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Templates Manager -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Fonnte Message Templates</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddTpl">
                                <i class="fas fa-plus mr-1"></i> Tambah Template
                            </button>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">Gunakan variabel berikut dalam pesan: <code>{customer_name}</code>, <code>{order_number}</code>, <code>{total_amount}</code>.</p>
                            <form method="POST" id="tplForm">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <div id="tplList">
                                    <?php if (!empty($templates)): ?>
                                        <?php foreach ($templates as $idx => $tpl): ?>
                                            <div class="border rounded p-3 mb-3 tpl-item">
                                                <div class="form-row">
                                                    <div class="form-group col-md-4">
                                                        <label>Label</label>
                                                        <input type="text" class="form-control" name="tpl_label[]" value="<?php echo htmlspecialchars($tpl['label']); ?>" maxlength="100" required>
                                                    </div>
                                                    <div class="form-group col-md-7">
                                                        <label>Message</label>
                                                        <input type="text" class="form-control" name="tpl_message[]" value="<?php echo htmlspecialchars($tpl['message']); ?>" maxlength="1000" required>
                                                    </div>
                                                    <div class="form-group col-md-1 d-flex align-items-end">
                                                        <div class="btn-group">
                                                            <button type="button" class="btn btn-sm btn-outline-secondary btnInsertTpl" title="Insert to Test">
                                                                <i class="fas fa-level-down-alt"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-danger btnRemoveTpl" title="Hapus">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="border rounded p-3 mb-3 tpl-item">
                                            <div class="form-row">
                                                <div class="form-group col-md-4">
                                                    <label>Label</label>
                                                    <input type="text" class="form-control" name="tpl_label[]" value="Order Confirmed" maxlength="100" required>
                                                </div>
                                                <div class="form-group col-md-7">
                                                    <label>Message</label>
                                                    <input type="text" class="form-control" name="tpl_message[]" value="Halo {customer_name}, pesanan {order_number} sudah kami konfirmasi. Terima kasih!" maxlength="1000" required>
                                                </div>
                                                <div class="form-group col-md-1 d-flex align-items-end">
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-sm btn-outline-secondary btnInsertTpl" title="Insert to Test">
                                                            <i class="fas fa-level-down-alt"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger btnRemoveTpl" title="Hapus">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <button type="button" class="btn btn-sm btn-outline-info" id="btnAddVars">Masukkan Variabel</button>
                                    </div>
                                    <div>
                                        <button type="submit" name="save_templates" class="btn btn-primary">Simpan Templates</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>

            <?php require_once 'includes/footer.php'; ?>
        </div>
    </div>

    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <?php require_once 'includes/scripts.php'; ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const btnFillSample = document.getElementById('btnFillSample');
        const phoneEl = document.getElementById('test_phone');
        const msgEl = document.getElementById('test_message');
        const orderEl = document.getElementById('test_order_id');
        const btnSend = document.getElementById('btnTestSend');
        const resultEl = document.getElementById('testResult');
        const toggleBtn = document.getElementById('toggleTokenVisibility');
        const tokenInput = document.getElementById('fonnte_token');

        // Disable test send if token not configured
        const hasToken = <?php echo $current_token !== '' ? 'true' : 'false'; ?>;
        if (!hasToken) {
            if (phoneEl) phoneEl.disabled = true;
            if (msgEl) msgEl.disabled = true;
            if (orderEl) orderEl.disabled = true;
            if (btnSend) btnSend.disabled = true;
            if (resultEl) resultEl.textContent = 'Konfigurasi token Fonnte terlebih dahulu untuk menguji pengiriman.';
        }

        if (btnFillSample) {
            btnFillSample.addEventListener('click', function() {
                if (phoneEl && !phoneEl.value) phoneEl.value = '+62851xxxxxxxx';
                if (msgEl && !msgEl.value) msgEl.value = 'Halo! Ini pesan percobaan dari Admin SyntaxTrust.';
            });
        }

        // Show/Hide token
        if (toggleBtn && tokenInput) {
            toggleBtn.addEventListener('click', function() {
                const isPassword = tokenInput.getAttribute('type') === 'password';
                tokenInput.setAttribute('type', isPassword ? 'text' : 'password');
                const icon = toggleBtn.querySelector('i');
                if (icon) {
                    icon.classList.remove(isPassword ? 'fa-eye' : 'fa-eye-slash');
                    icon.classList.add(isPassword ? 'fa-eye-slash' : 'fa-eye');
                }
            });
        }

        // Rate limiting for test send (10 seconds)
        let cooldown = 0;
        let cooldownTimer = null;
        function startCooldown(seconds) {
            cooldown = seconds;
            if (btnSend) btnSend.disabled = true;
            updateCooldownLabel();
            cooldownTimer = setInterval(() => {
                cooldown--;
                updateCooldownLabel();
                if (cooldown <= 0) {
                    clearInterval(cooldownTimer);
                    cooldownTimer = null;
                    if (btnSend && hasToken) {
                        btnSend.disabled = false;
                        btnSend.innerHTML = '<i class="fas fa-paper-plane mr-1"></i> Kirim Test';
                    }
                }
            }, 1000);
        }
        function updateCooldownLabel() {
            if (btnSend) {
                btnSend.innerHTML = '<i class="fas fa-hourglass-half mr-1"></i> Tunggu ' + cooldown + ' dtk';
            }
        }

        if (btnSend && hasToken) {
            btnSend.addEventListener('click', async function() {
                const csrf = document.querySelector('#testSendForm input[name="csrf_token"]').value;
                const phone = phoneEl.value.trim();
                const message = msgEl.value.trim();
                const orderId = orderEl.value ? parseInt(orderEl.value, 10) : '';

                resultEl.textContent = 'Mengirim...';

                try {
                    const form = new FormData();
                    form.append('csrf_token', csrf);
                    form.append('phone', phone);
                    form.append('message', message);
                    if (orderId) form.append('order_id', orderId);

                    const res = await fetch('api/send_whatsapp.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: form
                    });

                    const text = await res.text();
                    let json;
                    try {
                        json = JSON.parse(text);
                    } catch (e) {
                        json = { raw: text };
                    }

                    resultEl.textContent = JSON.stringify(json, null, 2);
                    // Start cooldown regardless of success to avoid abuse
                    startCooldown(10);
                } catch (err) {
                    resultEl.textContent = 'Request error: ' + (err && err.message ? err.message : err);
                }
            });
        }

        // Templates dynamic handlers
        function tplItem(label = '', message = '') {
            return `
            <div class="border rounded p-3 mb-3 tpl-item">
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Label</label>
                        <input type="text" class="form-control" name="tpl_label[]" value="${label}" maxlength="100" required>
                    </div>
                    <div class="form-group col-md-7">
                        <label>Message</label>
                        <input type="text" class="form-control" name="tpl_message[]" value="${message}" maxlength="1000" required>
                    </div>
                    <div class="form-group col-md-1 d-flex align-items-end">
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-secondary btnInsertTpl" title="Insert to Test">
                                <i class="fas fa-level-down-alt"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger btnRemoveTpl" title="Hapus">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;
        }

        const tplList = document.getElementById('tplList');
        const btnAddTpl = document.getElementById('btnAddTpl');
        if (btnAddTpl && tplList) {
            btnAddTpl.addEventListener('click', function() {
                tplList.insertAdjacentHTML('beforeend', tplItem('', ''));
            });
        }

        // Delegate remove/insert actions
        if (tplList) {
            tplList.addEventListener('click', function(e) {
                const t = e.target.closest('button');
                if (!t) return;
                if (t.classList.contains('btnRemoveTpl')) {
                    e.preventDefault();
                    const item = t.closest('.tpl-item');
                    if (item) item.remove();
                } else if (t.classList.contains('btnInsertTpl')) {
                    e.preventDefault();
                    const item = t.closest('.tpl-item');
                    if (!item) return;
                    const input = item.querySelector('input[name="tpl_message[]"]');
                    if (input && msgEl) {
                        msgEl.value = input.value;
                        msgEl.focus();
                    }
                }
            });
        }

        // Insert variables into currently focused template message or test message
        const btnAddVars = document.getElementById('btnAddVars');
        if (btnAddVars) {
            btnAddVars.addEventListener('click', function() {
                const token = prompt('Masukkan variabel (customer_name / order_number / total_amount):');
                if (!token) return;
                const map = { customer_name: '{customer_name}', order_number: '{order_number}', total_amount: '{total_amount}' };
                const val = map[token] || token;
                const active = document.activeElement;
                if (active && active.tagName === 'INPUT' && active.name === 'tpl_message[]') {
                    const start = active.selectionStart || active.value.length;
                    active.value = active.value.slice(0, start) + val + active.value.slice(start);
                    active.focus();
                } else if (msgEl) {
                    const start = msgEl.selectionStart || msgEl.value.length;
                    msgEl.value = msgEl.value.slice(0, start) + val + msgEl.value.slice(start);
                    msgEl.focus();
                }
            });
        }
    });
    </script>
</body>
</html>
