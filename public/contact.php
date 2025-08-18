<?php
require_once __DIR__ . '/includes/layout.php';

// Fetch services for dropdown
try {
    $services_stmt = $pdo->prepare("SELECT id, name FROM services WHERE is_active = 1 ORDER BY sort_order, name");
    $services_stmt->execute();
    $services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $services = [];
}

$site_name = getSetting('site_name', 'SyntaxTrust');
$site_description = getSetting('site_description', 'Layanan Pembuatan Website untuk Mahasiswa & UMKM');
$contact_email = getSetting('contact_email', '');
$contact_phone = getSetting('contact_phone', '');
$address = getSetting('address', '');
$whatsapp = str_replace(['+', '-', ' '], '', getSetting('company_whatsapp', '6285156553226'));

echo renderPageStart($site_name . ' - Kontak', 'Hubungi ' . $site_name . ' - ' . $site_description, 'contact.php');
?>

    <!-- Header / Breadcrumb -->
    <section class="bg-gradient-to-br from-blue-600 via-purple-600 to-indigo-700 text-white py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-6xl font-bold mb-6">Kontak Kami</h1>
            <p class="text-xl md:text-2xl mb-8 text-blue-100">Konsultasikan kebutuhan digital Anda dengan tim ahli kami</p>
        </div>
    </section>

    <section class="py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-3 gap-10">
            <!-- Contact Info -->
            <div class="bg-white rounded-xl shadow p-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Informasi Kontak</h2>
                <ul class="space-y-5 text-gray-700">
                    <li class="flex items-start">
                        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center mr-4 flex-shrink-0"><i class="fas fa-envelope text-lg"></i></div>
                        <div>
                            <div class="text-sm text-gray-500">Email</div>
                            <a href="mailto:<?= h($contact_email) ?>" class="font-semibold hover:text-blue-600"><?= h($contact_email) ?></a>
                        </div>
                    </li>
                    <li class="flex items-start">
                        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center mr-4 flex-shrink-0"><i class="fas fa-phone text-lg"></i></div>
                        <div>
                            <div class="text-sm text-gray-500">Telepon</div>
                            <a href="tel:<?= h($contact_phone) ?>" class="font-semibold hover:text-blue-600"><?= h($contact_phone) ?></a>
                        </div>
                    </li>
                    <li class="flex items-start">
                        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center mr-4 flex-shrink-0"><i class="fas fa-location-dot text-lg"></i></div>
                        <div>
                            <div class="text-sm text-gray-500">Alamat</div>
                            <div class="font-semibold break-words leading-relaxed"><?= h($address) ?></div>
                        </div>
                    </li>
                    <li class="flex items-start">
                        <div class="w-10 h-10 rounded-lg bg-green-50 text-green-600 flex items-center justify-center mr-4 flex-shrink-0"><i class="fab fa-whatsapp text-lg"></i></div>
                        <div>
                            <div class="text-sm text-gray-500">WhatsApp</div>
                            <a target="_blank" href="https://wa.me/<?= $whatsapp ?>?text=Halo, saya ingin konsultasi" class="font-semibold text-green-600 hover:text-green-700">Chat Sekarang</a>
                        </div>
                    </li>
                </ul>
                <div class="mt-8 p-4 bg-blue-50 rounded-lg text-blue-700 text-sm">
                    <i class="fas fa-info-circle mr-2"></i> Waktu respons biasa 5-30 menit pada jam kerja.
                </div>
            </div>

            <!-- Contact Form -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow p-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Kirim Pesan</h2>
                <form id="contact-form" class="space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap<span class="text-red-500">*</span></label>
                            <input type="text" name="name" required class="w-full border-gray-300 rounded-lg focus:ring-custom focus:border-blue-500 p-3" placeholder="Nama Anda">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email<span class="text-red-500">*</span></label>
                            <input type="email" name="email" required class="w-full border-gray-300 rounded-lg focus:ring-custom focus:border-blue-500 p-3" placeholder="email@domain.com">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Telepon</label>
                            <input type="text" name="phone" class="w-full border-gray-300 rounded-lg focus:ring-custom focus:border-blue-500 p-3" placeholder="08xxxxxxxxxx">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Pilih Layanan</label>
                            <select name="service_id" class="w-full border-gray-300 rounded-lg focus:ring-custom focus:border-blue-500 p-3">
                                <option value="">- Pilih Layanan -</option>
                                <?php foreach ($services as $s): ?>
                                <option value="<?= (int)$s['id'] ?>"><?= h($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Perkiraan Budget</label>
                            <select name="budget_range" class="w-full border-gray-300 rounded-lg focus:ring-custom focus:border-blue-500 p-3">
                                <option value="">Tidak tahu / diskusikan</option>
                                <option value="< 1jt">< 1jt</option>
                                <option value="1-3jt">1-3jt</option>
                                <option value="3-5jt">3-5jt</option>
                                <option value="> 5jt">> 5jt</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Timeline</label>
                            <select name="timeline" class="w-full border-gray-300 rounded-lg focus:ring-custom focus:border-blue-500 p-3">
                                <option value="">Fleksibel</option>
                                <option value="Secepatnya">Secepatnya</option>
                                <option value="1-2 minggu">1-2 minggu</option>
                                <option value="3-4 minggu">3-4 minggu</option>
                                <option value="> 1 bulan">> 1 bulan</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subjek</label>
                        <input type="text" name="subject" class="w-full border-gray-300 rounded-lg focus:ring-custom focus:border-blue-500 p-3" placeholder="Judul pesan">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Pesan<span class="text-red-500">*</span></label>
                        <textarea name="message" rows="6" required class="w-full border-gray-300 rounded-lg focus:ring-custom focus:border-blue-500 p-3" placeholder="Ceritakan kebutuhan/project Anda..."></textarea>
                    </div>
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-gray-500">Dengan mengirim pesan, Anda menyetujui kebijakan privasi kami.</p>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold flex items-center">
                            <i class="fas fa-paper-plane mr-2"></i>Kirim Pesan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- CTA WhatsApp -->
    <section class="pb-16 -mt-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <a target="_blank" href="https://wa.me/<?= $whatsapp ?>?text=Halo, saya ingin konsultasi" class="block w-full text-center bg-green-600 hover:bg-green-700 text-white px-8 py-4 rounded-lg font-semibold">
                <i class="fab fa-whatsapp mr-2"></i> Konsultasi Cepat via WhatsApp
            </a>
        </div>
    </section>

    <script>
        // Submit contact form via AJAX to public/api/submit_contact.php
        const form = document.getElementById('contact-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(form);
                const payload = Object.fromEntries(formData.entries());

                fetch('api/submit_contact.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        form.reset();
                        alert('Terima kasih! Pesan Anda telah terkirim.');
                    } else {
                        alert(data.message || 'Gagal mengirim pesan. Coba lagi.');
                    }
                })
                .catch(() => alert('Terjadi kesalahan jaringan.'));
            });
        }
    </script>
<?php echo renderPageEnd(); ?>
