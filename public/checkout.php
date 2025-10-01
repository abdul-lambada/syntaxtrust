<?php
// Bootstrap session with robust path fallback for hosting
$__sessionPath = __DIR__ . '/../config/session.php';
if (!file_exists($__sessionPath)) {
  $__sessionPath = __DIR__ . '/config/session.php';
}
if (!file_exists($__sessionPath)) {
  $__sessionPath = dirname(__DIR__) . '/config/session.php';
}
if (file_exists($__sessionPath)) {
  require_once $__sessionPath;
} else {
  // Minimal session bootstrap if config/session.php not present (e.g., only public folder deployed)
  @ini_set('session.cookie_httponly', 1);
  @ini_set('session.use_strict_mode', 1);
  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
  @ini_set('session.cookie_secure', $isHttps ? 1 : 0);
  if (function_exists('session_name')) {
    @session_name('SYNTRUST_SESS');
  }
  if (session_status() === PHP_SESSION_NONE) {
    @session_start();
  }
}
require_once __DIR__ . '/includes/layout.php';

$pageTitle = 'Checkout';
$currentPage = 'checkout';

// CSRF token for public form
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$message_type = '';
$order_number = '';

// Get pre-selected plan and service from URL parameters
$preselected_plan_id = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : null;
$preselected_service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : null;

// Load selected plan details if provided
$selected_plan = null;
$selected_service = null;
if ($preselected_plan_id) {
  try {
    $stmt = $pdo->prepare("
            SELECT pp.*, s.name as service_name 
            FROM pricing_plans pp 
            LEFT JOIN services s ON pp.service_id = s.id 
            WHERE pp.id = ? AND pp.is_active = 1
        ");
    $stmt->execute([$preselected_plan_id]);
    $selected_plan = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($selected_plan) {
      $preselected_service_id = $selected_plan['service_id'];
    }
  } catch (Throwable $e) {
    $selected_plan = null;
  }
}

// Load services and pricing plans (for selects)
$services = [];
$plans = [];
try {
  if ($pdo instanceof PDO) {
    $stmt = $pdo->query('SELECT id, name FROM services WHERE is_active = 1 ORDER BY name ASC');
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } else {
    $services = [];
  }
} catch (Throwable $e) {
  $services = [];
}
try {
  if ($pdo instanceof PDO) {
    $stmt = $pdo->query('SELECT id, name, price, service_id FROM pricing_plans WHERE is_active = 1 ORDER BY service_id ASC, price ASC');
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } else {
    $plans = [];
  }
} catch (Throwable $e) {
  $plans = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    $message = 'Sesi kadaluarsa. Silakan refresh halaman.';
    $message_type = 'danger';
  } else {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $service_id = isset($_POST['service_id']) && $_POST['service_id'] !== '' ? (int)$_POST['service_id'] : null;
    $pricing_plan_id = isset($_POST['pricing_plan_id']) && $_POST['pricing_plan_id'] !== '' ? (int)$_POST['pricing_plan_id'] : null;
    $project_description = trim($_POST['project_description'] ?? '');
    $requirements_input = trim($_POST['requirements'] ?? '');

    // Basic validation
    if ($customer_name === '' || $customer_email === '' || !$service_id) {
      $message = 'Nama, Email, dan Layanan wajib diisi.';
      $message_type = 'danger';
    } else {
      // Compute total from pricing plan if provided
      $total_amount = 0.0;
      if ($pricing_plan_id) {
        try {
          if ($pdo instanceof PDO) {
            $pp = $pdo->prepare('SELECT price FROM pricing_plans WHERE id = ? LIMIT 1');
            $pp->execute([$pricing_plan_id]);
            $row = $pp->fetch(PDO::FETCH_ASSOC);
            if ($row) {
              $total_amount = (float)$row['price'];
            }
          }
        } catch (Throwable $e) { /* fallback keep 0 */
        }
      }

      // Normalize requirements to JSON
      if ($requirements_input === '') {
        $requirements = '[]';
      } else {
        $firstChar = substr($requirements_input, 0, 1);
        if ($firstChar === '{' || $firstChar === '[') {
          $requirements = $requirements_input;
        } else {
          $requirements = json_encode($requirements_input, JSON_UNESCAPED_UNICODE);
        }
      }

      // Generate order number
      $order_number = 'ORD-' . date('Ymd') . '-' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);

      try {
        $stmt = $pdo->prepare('INSERT INTO orders (order_number, user_id, service_id, pricing_plan_id, customer_name, customer_email, customer_phone, project_description, total_amount, status, payment_status, requirements, created_at) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([
          $order_number,
          $service_id,
          $pricing_plan_id,
          $customer_name,
          $customer_email,
          $customer_phone,
          $project_description,
          $total_amount,
          'pending',
          'unpaid',
          $requirements,
        ]);
        // Kirim WhatsApp otomatis berisi invoice dan opsi pembayaran (parsial/penuh)
        try {
          $fonnteToken = getSetting('fonnte_token', '', true);
          $phone = trim($customer_phone);
          if (!empty($fonnteToken) && !empty($phone)) {
            // Normalisasi nomor telepon ke format internasional Indonesia
            $normalized = preg_replace('/[^0-9+]/', '', $phone);
            if (strpos($normalized, '+') === 0) {
              $normalized = substr($normalized, 1);
            }
            if (strpos($normalized, '0') === 0) {
              $normalized = '62' . substr($normalized, 1);
            }
            // Bangun URL absolut untuk tautan pembayaran dan pelacakan
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base = $scheme . '://' . $host;
            $payFullUrl = $base . '/pay.php?order_number=' . urlencode($order_number) . '&mode=full';
            $payPartialUrl = $base . '/pay.php?order_number=' . urlencode($order_number) . '&mode=partial&percent=50';
            $trackUrl = $base . '/order-tracking.php?order_number=' . urlencode($order_number);
            // Format total
            $totalFormatted = 'Rp ' . number_format((float)$total_amount, 0, ',', '.');
            // Susun pesan WhatsApp
            $message = "Halo " . $customer_name . ", terima kasih telah melakukan checkout di SyntaxTrust.\n\n" .
              "Invoice Pesanan Anda:\n" .
              "• Nomor: " . $order_number . "\n" .
              "• Total: " . $totalFormatted . "\n" .
              "• Status: menunggu pembayaran\n\n" .
              "Opsi Pembayaran:\n" .
              "• Bayar Penuh: " . $payFullUrl . "\n" .
              "• Bayar DP 50%: " . $payPartialUrl . "\n\n" .
              "Lacak pesanan: " . $trackUrl . "\n\n" .
              "Jika perlu bantuan, silakan balas pesan ini atau hubungi kami melalui WhatsApp.";
            // Kirim ke Fonnte
            $ch = curl_init('https://api.fonnte.com/send');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
              'Authorization: ' . $fonnteToken,
              'Content-Type: application/x-www-form-urlencoded'
            ]);
            $postFields = http_build_query([
              'target' => $normalized,
              'message' => $message,
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            $resp = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);
            if ($resp === false || $httpCode < 200 || $httpCode >= 300) {
              // Log error secara silent agar tidak mengganggu UX
              error_log('Fonnte send error: ' . ($curlErr ?: ('HTTP ' . $httpCode)));
              // Catat notifikasi supaya admin mengetahui adanya kegagalan
              try {
                $n = $pdo->prepare('INSERT INTO notifications (user_id, title, message, type, related_url) VALUES (?, ?, ?, ?, ?)');
                $n_user = null; // public checkout (tanpa sesi admin)
                $n_title = 'Auto WhatsApp failed';
                $n_msg = 'Gagal mengirim WhatsApp untuk pesanan ' . $order_number . ' ke ' . $normalized . '. ' . ($curlErr ?: ('HTTP ' . $httpCode));
                $n_url = 'admin/manage_orders.php?search=' . urlencode($order_number);
                $n->execute([$n_user, $n_title, $n_msg, 'warning', $n_url]);
              } catch (Throwable $e2) { /* abaikan kegagalan notifikasi */
              }
            }
          }
        } catch (Throwable $eSend) {
          // Jangan blokir flow checkout
          error_log('WhatsApp send exception: ' . $eSend->getMessage());
        }
        $message = 'Order berhasil dibuat. Nomor pesanan: ' . htmlspecialchars($order_number);
        $message_type = 'success';

        // Redirect to Thank You page with order summary and payment options
        $redirectUrl = 'thank_you.php?order_number=' . urlencode($order_number);
        header('Location: ' . $redirectUrl);
        exit();
      } catch (Throwable $e) {
        $message = 'Gagal membuat order. Silakan coba lagi.';
        $message_type = 'danger';
      }
    }
  }
}

// Prepare plans grouped by service for dynamic client-side filtering
$plansByService = [];
foreach ($plans as $p) {
  $sid = (int)($p['service_id'] ?? 0);
  if (!$sid) continue;
  if (!isset($plansByService[$sid])) $plansByService[$sid] = [];
  $plansByService[$sid][] = [
    'id' => (int)$p['id'],
    'name' => (string)$p['name'],
    'price' => (float)$p['price'],
  ];
}

echo renderPageStart($pageTitle, 'Lakukan pemesanan layanan dengan cepat dan aman.', $currentPage);
?>

<main class="max-w-6xl mx-auto px-4 py-10">
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Main Form -->
    <div class="lg:col-span-2">
      <h1 class="text-3xl font-bold mb-6">Checkout</h1>

      <?php if ($message): ?>
        <div class="mb-6 p-4 rounded border <?= $message_type === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700' ?>">
          <?= $message ?>
          <?php if ($message_type === 'success' && $order_number): ?>
            <div class="mt-2">
              <a href="order-tracking.php?order_number=<?= urlencode($order_number) ?>" class="text-blue-600 hover:underline">Lacak pesanan</a>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($selected_plan): ?>
        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
          <div class="flex items-center mb-2">
            <i class="fas fa-info-circle text-blue-600 mr-2"></i>
            <h3 class="font-semibold text-blue-900">Paket Terpilih</h3>
          </div>
          <div class="text-sm text-blue-800">
            <strong><?= h($selected_plan['name']) ?></strong>
            <?php if ($selected_plan['service_name']): ?>
              - <?= h($selected_plan['service_name']) ?>
            <?php endif; ?>
            <?php if ($selected_plan['price'] > 0): ?>
              <span class="ml-2 font-semibold"><?= $selected_plan['currency'] ?> <?= number_format($selected_plan['price'], 0, ',', '.') ?></span>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- AJAX error placeholder -->
      <div id="ajaxError" class="mb-6 p-4 rounded border bg-red-50 border-red-200 text-red-700 hidden"></div>

      <form method="post" id="checkoutForm" class="bg-white shadow rounded p-6" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <!-- Honeypot (should remain empty) -->
        <input type="text" name="website" id="hp_website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;opacity:0;width:1px;height:1px;" aria-hidden="true">
        <!-- Form start timestamp (ms) -->
        <input type="hidden" name="form_started_at" id="form_started_at" value="">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nama *</label>
            <input type="text" name="customer_name" required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
            <input type="email" name="customer_email" required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Telepon</label>
            <input type="tel" name="customer_phone" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Layanan *</label>
            <select name="service_id" id="service_id" required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring">
              <option value="">Pilih Layanan</option>
              <?php foreach ($services as $s): ?>
                <option value="<?= (int)$s['id'] ?>" <?= $preselected_service_id == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Paket (opsional)</label>
            <select name="pricing_plan_id" id="pricing_plan_id" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring">
              <option value="">Pilih Paket</option>
              <?php if ($selected_plan): ?>
                <option value="<?= $selected_plan['id'] ?>" selected><?= h($selected_plan['name']) ?> - <?= $selected_plan['currency'] ?> <?= number_format($selected_plan['price'], 0, ',', '.') ?></option>
              <?php endif; ?>
            </select>
            <p id="plan_hint" class="text-xs text-gray-500 mt-1 <?= $selected_plan ? '' : 'hidden' ?>">
              <?= $selected_plan ? 'Harga akan dihitung otomatis dari paket terpilih saat submit.' : '' ?>
            </p>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi Proyek</label>
            <textarea name="project_description" rows="4" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring" placeholder="Ceritakan kebutuhan Anda"></textarea>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Kebutuhan Tambahan (opsional)</label>
            <textarea name="requirements" rows="3" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring" placeholder="Bisa JSON atau teks bebas"></textarea>
          </div>
        </div>

        <div class="mt-6 flex items-center gap-3">
          <button type="submit" class="inline-flex items-center bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 font-semibold">
            <i class="fas fa-shopping-cart mr-2"></i> Buat Pesanan
          </button>
          <button type="button" class="inline-flex items-center bg-gray-100 text-gray-800 px-6 py-3 rounded-lg hover:bg-gray-200 font-semibold" onclick="if (document.referrer) { window.history.back(); } else { window.location.href='index.php'; }">
            <i class="fas fa-arrow-left mr-2"></i> Kembali
          </button>
        </div>
      </form>
    </div>

    <!-- Order Summary Sidebar -->
    <div class="lg:col-span-1">
      <div class="bg-white shadow rounded-lg p-6 sticky top-6">
        <h3 class="text-lg font-semibold mb-4">Ringkasan Pesanan</h3>

        <?php if ($selected_plan): ?>
          <div class="border-b pb-4 mb-4">
            <div class="flex justify-between items-start mb-2">
              <div>
                <h4 class="font-medium"><?= h($selected_plan['name']) ?></h4>
                <p class="text-sm text-gray-600"><?= h($selected_plan['service_name']) ?></p>
              </div>
              <?php if ($selected_plan['price'] > 0): ?>
                <span class="font-semibold"><?= $selected_plan['currency'] ?> <?= number_format($selected_plan['price'], 0, ',', '.') ?></span>
              <?php else: ?>
                <span class="text-gray-500">Custom</span>
              <?php endif; ?>
            </div>

            <?php if ($selected_plan['delivery_time']): ?>
              <div class="text-sm text-gray-600 mb-2">
                <i class="fas fa-clock mr-1"></i>
                Estimasi: <?= h($selected_plan['delivery_time']) ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($selected_plan['features'])):
              $features = json_decode($selected_plan['features'], true) ?: [];
              if (!empty($features)):
            ?>
                <div class="mt-3">
                  <h5 class="text-sm font-medium text-gray-900 mb-2">Fitur Termasuk:</h5>
                  <ul class="text-sm text-gray-600 space-y-1">
                    <?php foreach (array_slice($features, 0, 3) as $feature): ?>
                      <li class="flex items-center">
                        <i class="fas fa-check text-green-500 mr-2 text-xs"></i>
                        <?= h($feature) ?>
                      </li>
                    <?php endforeach; ?>
                    <?php if (count($features) > 3): ?>
                      <li class="text-blue-600 font-medium">+<?= count($features) - 3 ?> fitur lainnya</li>
                    <?php endif; ?>
                  </ul>
                </div>
            <?php endif;
            endif; ?>
          </div>

          <?php if ($selected_plan['price'] > 0): ?>
            <div class="flex justify-between items-center text-lg font-semibold">
              <span>Total:</span>
              <span><?= $selected_plan['currency'] ?> <?= number_format($selected_plan['price'], 0, ',', '.') ?></span>
            </div>
          <?php else: ?>
            <div class="text-center text-gray-600">
              <i class="fas fa-calculator text-2xl mb-2"></i>
              <p class="text-sm">Harga akan ditentukan setelah konsultasi</p>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <div class="text-center text-gray-500 py-8">
            <i class="fas fa-shopping-cart text-3xl mb-3"></i>
            <p>Pilih paket untuk melihat ringkasan</p>
          </div>
        <?php endif; ?>

        <!-- Contact Support -->
        <div class="mt-6 pt-6 border-t">
          <h4 class="font-medium mb-3">Butuh Bantuan?</h4>
          <div class="space-y-2">
            <a href="https://wa.me/<?= str_replace(['+', '-', ' '], '', getSetting('company_whatsapp', '6285156553226')) ?>?text=Halo, saya butuh bantuan dengan checkout"
              target="_blank"
              class="w-full block text-center bg-green-600 text-white py-2 px-4 rounded-lg text-sm font-medium hover:bg-green-700 transition-colors">
              <i class="fab fa-whatsapp mr-2"></i>Chat WhatsApp
            </a>
            <a href="contact.php" class="w-full block text-center bg-gray-100 text-gray-800 py-2 px-4 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
              <i class="fas fa-envelope mr-2"></i>Kontak Kami
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<script>
  // Initialize form started timestamp
  (function() {
    const ts = document.getElementById('form_started_at');
    if (ts && !ts.value) {
      ts.value = String(Date.now());
    }
  })();

  // Enhance form to use public API checkout_create_order.php via AJAX
  (function() {
    const form = document.getElementById('checkoutForm');
    if (!form) return;
    // Restore from localStorage
    try {
      const saved = JSON.parse(localStorage.getItem('checkout_form') || '{}');
      ['customer_name', 'customer_email', 'customer_phone', 'service_id', 'pricing_plan_id', 'project_description', 'requirements']
      .forEach(k => {
        if (saved[k] && form.elements[k]) form.elements[k].value = saved[k];
      });
    } catch (_) {}

    // Persist to localStorage on input
    form.addEventListener('input', function(e) {
      try {
        const fd = new FormData(form);
        const toSave = {};
        ['customer_name', 'customer_email', 'customer_phone', 'service_id', 'pricing_plan_id', 'project_description', 'requirements']
        .forEach(k => {
          toSave[k] = fd.get(k) || '';
        });
        localStorage.setItem('checkout_form', JSON.stringify(toSave));
      } catch (_) {}
    });

    function showError(msg) {
      const box = document.getElementById('ajaxError');
      if (box) {
        box.textContent = msg || 'Terjadi kesalahan.';
        box.classList.remove('hidden');
        box.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      } else {
        alert(msg);
      }
    }

    form.addEventListener('submit', async function(e) {
      try {
        e.preventDefault();
        const fd = new FormData(form);
        // Basic inline validation
        const name = (fd.get('customer_name') || '').trim();
        const email = (fd.get('customer_email') || '').trim();
        const service = (fd.get('service_id') || '').trim();
        const phone = (fd.get('customer_phone') || '').trim();
        if (!name) return showError('Nama wajib diisi');
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return showError('Email tidak valid');
        if (!service) return showError('Silakan pilih layanan');
        if (phone && phone.replace(/\D+/g, '').length < 9) return showError('Nomor telepon terlalu pendek');

        const payload = {
          customer_name: fd.get('customer_name') || '',
          customer_email: fd.get('customer_email') || '',
          customer_phone: fd.get('customer_phone') || '',
          service_id: fd.get('service_id') || '',
          pricing_plan_id: fd.get('pricing_plan_id') || '',
          project_description: fd.get('project_description') || '',
          requirements: fd.get('requirements') || '',
          // Anti-bot
          hp: fd.get('website') || '',
          form_started_at: fd.get('form_started_at') || String(Date.now())
        };
        const btn = form.querySelector('button[type="submit"]');
        if (btn) {
          btn.disabled = true;
          btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Memproses...';
        }
        const res = await fetch('api/checkout_create_order.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(payload),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data || !data.success) {
          throw new Error((data && data.error) ? data.error : 'Gagal membuat order.');
        }
        const orderNo = data.order_number;
        if (orderNo) {
          // Clear saved draft
          localStorage.removeItem('checkout_form');
          window.location.href = 'thank_you.php?order_number=' + encodeURIComponent(orderNo);
        } else {
          throw new Error('Order number tidak ditemukan.');
        }
      } catch (err) {
        showError((err && err.message) ? err.message : String(err));
      } finally {
        const btn = form.querySelector('button[type="submit"]');
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-shopping-cart mr-2"></i> Buat Pesanan';
        }
      }
    });
  })();
  // Plans grouped by service from PHP
  const plansByService = <?= json_encode($plansByService, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const serviceSelect = document.getElementById('service_id');
  const planSelect = document.getElementById('pricing_plan_id');
  const planHint = document.getElementById('plan_hint');

  function renderPlans(serviceId) {
    while (planSelect.firstChild) planSelect.removeChild(planSelect.firstChild);
    const opt = document.createElement('option');
    opt.value = '';
    opt.textContent = 'Pilih Paket';
    planSelect.appendChild(opt);
    planHint.classList.add('hidden');
    planHint.textContent = '';
    const sid = parseInt(serviceId || 0, 10);
    const list = plansByService[sid] || [];
    list.forEach(p => {
      const o = document.createElement('option');
      o.value = p.id;
      o.textContent = `${p.name} - Rp ${new Intl.NumberFormat('id-ID').format(p.price)}`;
      planSelect.appendChild(o);
    });
    if (list.length) {
      planHint.textContent = 'Harga akan dihitung otomatis dari paket terpilih saat submit.';
      planHint.classList.remove('hidden');
    } else if (sid) {
      // No plans for this service; show friendly placeholder
      const none = document.createElement('option');
      none.value = '';
      none.disabled = true;
      none.textContent = 'Belum ada paket untuk layanan ini (opsional)';
      planSelect.appendChild(none);
    }
  }

  serviceSelect && serviceSelect.addEventListener('change', (e) => renderPlans(e.target.value));

  // Populate plans on initial load if a service is already selected
  (function initPlansOnLoad(){
    if (!serviceSelect || !planSelect) return;
    const currentService = serviceSelect.value;
    if (currentService) {
      renderPlans(currentService);
      <?php if (!empty($selected_plan['id'])): ?>
      // Re-select preselected plan after re-rendering options
      planSelect.value = '<?= (int)$selected_plan['id'] ?>';
      if (planHint) {
        planHint.textContent = 'Harga akan dihitung otomatis dari paket terpilih saat submit.';
        planHint.classList.remove('hidden');
      }
      <?php endif; ?>
    }
  })();
</script>

<?php echo renderPageEnd(); ?>