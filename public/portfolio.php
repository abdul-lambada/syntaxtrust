<?php
// portfolio.php - Archive and detail view for portfolio items
// Depends on: config/database.php, public/includes/header.php, public/includes/footer.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/app.php';

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 9;
$hasNext = false;
$item = null;
$items = [];

try {
  if ($id > 0) {
    $stmt = $pdo->prepare("SELECT id, title, category, short_description, description, image_main, images, project_url, created_at FROM portfolio WHERE id = :id AND is_active = 1 LIMIT 1");
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch();
  }

  if (!$item) {
    // Archive listing with pagination
    $offset = ($page - 1) * $perPage;
    $stmt = $pdo->prepare("SELECT id, title, category, short_description, image_main, project_url FROM portfolio WHERE is_active = 1 ORDER BY id DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $perPage + 1, PDO::PARAM_INT); // fetch one extra to infer next page
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    if (count($rows) > $perPage) {
      $hasNext = true;
      $items = array_slice($rows, 0, $perPage);
    } else {
      $items = $rows;
    }
  }
} catch (Exception $e) {
  $item = $item ?: null;
  $items = $items ?: [];
}

// SEO meta: title & description
if ($item) {
  $pageTitle = ($item['title'] ?? 'Proyek') . ' | Portofolio | SyntaxTrust';
  $desc = trim((string)($item['short_description'] ?? ''));
  if ($desc === '') {
    $raw = (string)($item['description'] ?? '');
    $raw = trim(preg_replace('/\s+/', ' ', strip_tags($raw)) ?? '');
    $desc = mb_substr($raw, 0, 160);
  }
  $pageDesc = $desc;
  // Canonical and OG image for detail view
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $canonicalUrl = $scheme . '://' . $host . PUBLIC_BASE_PATH . '/portfolio.php?id=' . (int)($item['id'] ?? 0);
  $img = $item['image_main'] ?? '';
  // Build absolute OG image URL without mediaUrl (header not included yet)
  if (!empty($img)) {
    $p = (string)$img;
    if (preg_match('/^https?:\/\//i', $p)) {
      $ogImage = $p;
    } elseif (strlen($p) && $p[0] === '/') {
      $ogImage = $scheme . '://' . $host . $p;
    } elseif (preg_match('/^(?:admin\/)?uploads\//i', $p)) {
      $ogImage = $scheme . '://' . $host . APP_BASE_PATH . '/' . $p;
    } elseif (preg_match('/^assets\//i', $p)) {
      $ogImage = $scheme . '://' . $host . PUBLIC_BASE_PATH . '/' . $p;
    } else {
      $ogImage = $scheme . '://' . $host . PUBLIC_BASE_PATH . '/' . ltrim($p, '/');
    }
  } else {
    $ogImage = null;
  }
  // Previous/Next for detail view (by created_at, then id)
  try {
    $prevStmt = $pdo->prepare("SELECT id, title FROM portfolio WHERE is_active=1 AND (created_at > :ca OR (created_at = :ca AND id > :id)) ORDER BY created_at ASC, id ASC LIMIT 1");
    $prevStmt->execute([':ca' => $item['created_at'], ':id' => $item['id']]);
    $prevItem = $prevStmt->fetch() ?: null;
    $nextStmt = $pdo->prepare("SELECT id, title FROM portfolio WHERE is_active=1 AND (created_at < :ca OR (created_at = :ca AND id < :id)) ORDER BY created_at DESC, id DESC LIMIT 1");
    $nextStmt->execute([':ca' => $item['created_at'], ':id' => $item['id']]);
    $nextItem = $nextStmt->fetch() ?: null;
  } catch (Exception $e) {
    $prevItem = $prevItem ?? null;
    $nextItem = $nextItem ?? null;
  }
} else {
  $pageTitle = 'Portofolio | SyntaxTrust';
  $pageDesc = 'Kumpulan proyek yang pernah kami kerjakan.';
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $canonicalUrl = $scheme . '://' . $host . PUBLIC_BASE_PATH . '/portfolio.php' . ($page > 1 ? ('?page=' . (int)$page) : '');
  $ogImage = null;
  // rel prev/next for archive pagination
  if ($page > 1) {
    $relPrevUrl = $scheme . '://' . $host . PUBLIC_BASE_PATH . '/portfolio.php' . ($page - 1 > 1 ? ('?page=' . (int)($page - 1)) : '');
  }
  if ($hasNext) {
    $relNextUrl = $scheme . '://' . $host . PUBLIC_BASE_PATH . '/portfolio.php?page=' . (int)($page + 1);
  }
}

include __DIR__ . '/includes/header.php';
?>

<main class="min-h-screen py-16 bg-slate-50 dark:bg-slate-900/30">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <?php if ($item): ?>
      <nav class="text-sm mb-6 text-slate-500" data-reveal="down"><a class="hover:text-slate-700" href="<?php echo PUBLIC_BASE_PATH; ?>/portfolio.php">Portofolio</a> <span class="mx-1">/</span> <span class="text-slate-700 dark:text-slate-300"><?php echo h($item['title'] ?? ''); ?></span></nav>
      <article class="mx-auto max-w-4xl overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900" data-reveal="up">
        <header class="flex flex-col gap-2">
          <h1 class="text-3xl font-bold tracking-tight"><?php echo h($item['title'] ?? ''); ?></h1>
          <?php if (!empty($item['category'])): ?><p class="text-sm text-slate-500">Kategori: <?php echo h($item['category']); ?></p><?php endif; ?>
        </header>
        <?php if (!empty($item['image_main'])): ?>
          <?php
            $img = (string)$item['image_main'];
            $imgPath = $img;
            if ($img && strpos($img, '/') === false) {
              $try1 = 'admin/uploads/portfolio/' . ltrim($img, '/');
              $try2 = 'admin/uploads/portofolio/' . ltrim($img, '/');
              $try3 = 'admin/uploads/' . ltrim($img, '/'); // fallback: root uploads
              $abs1 = __DIR__ . '/../' . str_replace(['..', chr(92)], ['', '/'], $try1);
              $abs2 = __DIR__ . '/../' . str_replace(['..', chr(92)], ['', '/'], $try2);
              $abs3 = __DIR__ . '/../' . str_replace(['..', chr(92)], ['', '/'], $try3);
              if (is_file($abs1)) { $imgPath = $try1; }
              elseif (is_file($abs2)) { $imgPath = $try2; }
              elseif (is_file($abs3)) { $imgPath = $try3; }
              else { $imgPath = $try1; }
            }
          ?>
          <img class="mt-6 h-80 w-full rounded-xl object-cover" src="<?php echo h(mediaUrl($imgPath)); ?>" alt="<?php echo h($item['title'] ?? ''); ?>" loading="lazy" decoding="async" width="1200" height="675" />
        <?php endif; ?>
        <?php if (!empty($item['short_description'])): ?>
          <p class="mt-6 text-slate-600 dark:text-slate-300"><?php echo h($item['short_description']); ?></p>
        <?php endif; ?>
        <div class="prose prose-slate mt-6 max-w-none dark:prose-invert">
          <?php
            $desc = $item['description'] ?? '';
            if ($desc !== '') {
              echo $desc; // assume HTML from admin
            }
          ?>
        </div>
        <?php
          // Render gallery thumbnails if available
          $gallery = json_decode($item['images'] ?? '[]', true);
          if (is_array($gallery) && count($gallery) > 0):
        ?>
          <div class="mt-6" x-data="{ open:false, photo:'' }">
            <h3 class="mb-3 text-sm font-semibold text-slate-500">Galeri</h3>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
              <?php foreach ($gallery as $g): $src = (string)$g; if (!$src) continue;
                $srcPath = $src;
                if ($src && strpos($src, '/') === false) {
                  $g1 = 'admin/uploads/portfolio/' . ltrim($src, '/');
                  $g2 = 'admin/uploads/portofolio/' . ltrim($src, '/');
                  $g3 = 'admin/uploads/' . ltrim($src, '/'); // fallback: root uploads
                  $ga1 = __DIR__ . '/../' . str_replace(['..', chr(92)], ['', '/'], $g1);
                  $ga2 = __DIR__ . '/../' . str_replace(['..', chr(92)], ['', '/'], $g2);
                  $ga3 = __DIR__ . '/../' . str_replace(['..', chr(92)], ['', '/'], $g3);
                  if (is_file($ga1)) { $srcPath = $g1; }
                  elseif (is_file($ga2)) { $srcPath = $g2; }
                  elseif (is_file($ga3)) { $srcPath = $g3; }
                  else { $srcPath = $g1; }
                }
                $srcAbs = mediaUrl($srcPath);
              ?>
                <button type="button" class="group overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700" @click="open=true; photo='<?php echo h($srcAbs); ?>'" data-reveal="up">
                  <img class="h-28 w-full object-cover transition group-hover:scale-[1.03]" src="<?php echo h($srcAbs); ?>" alt="<?php echo h(($item['title'] ?? '')); ?> - gallery" loading="lazy" decoding="async" width="600" height="338" />
                </button>
              <?php endforeach; ?>
            </div>
            <!-- Lightbox -->
            <div x-show="open" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @keydown.escape.window="open=false" @click.self="open=false" style="display:none">
              <div class="relative max-h-[85vh] w-full max-w-5xl">
                <button type="button" class="absolute -top-10 right-0 rounded-md bg-white/90 px-3 py-1 text-sm shadow hover:bg-white" @click="open=false">Tutup</button>
                <img :src="photo" alt="Lightbox" class="max-h-[85vh] w-full rounded-xl object-contain" />
              </div>
            </div>
          </div>
        <?php endif; ?>
        <?php if (!empty($item['project_url'])): ?>
          <div class="mt-6">
            <a class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700" href="<?php echo h($item['project_url']); ?>" target="_blank" rel="noopener">Kunjungi Proyek</a>
          </div>
        <?php endif; ?>
      </article>
      <div class="mx-auto max-w-4xl mt-8 grid grid-cols-3 items-center">
        <div class="justify-self-start">
          <?php if (!empty($prevItem)): ?>
            <a href="<?php echo PUBLIC_BASE_PATH; ?>/portfolio.php?id=<?php echo (int)$prevItem['id']; ?>" class="text-sm text-blue-600 hover:underline">← <?php echo h($prevItem['title']); ?></a>
          <?php endif; ?>
        </div>
        <div class="justify-self-center">
          <a href="<?php echo PUBLIC_BASE_PATH; ?>/portfolio.php" class="text-sm text-slate-500 hover:underline">Kembali ke Portofolio</a>
        </div>
        <div class="justify-self-end text-right">
          <?php if (!empty($nextItem)): ?>
            <a href="<?php echo PUBLIC_BASE_PATH; ?>/portfolio.php?id=<?php echo (int)$nextItem['id']; ?>" class="text-sm text-blue-600 hover:underline">Berikutnya →</a>
          <?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <div class="mx-auto max-w-2xl text-center" data-reveal="down">
        <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Portofolio</h2>
        <p class="mt-3 text-slate-600 dark:text-slate-400">Beberapa proyek yang pernah kami kerjakan.</p>
      </div>
      <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <?php if (!empty($items)): foreach ($items as $pf): ?>
          <?php
            $img = $pf['image_main'] ?? '';
            $imgPath = $img;
            if ($img && strpos($img, '/') === false) {
              $try1 = 'admin/uploads/portfolio/' . ltrim($img, '/');
              $try2 = 'admin/uploads/portofolio/' . ltrim($img, '/');
              $try3 = 'admin/uploads/' . ltrim($img, '/'); // fallback: root uploads
              $abs1 = __DIR__ . '/../' . str_replace(['..', chr(92)], ['', '/'], $try1);
              $abs2 = __DIR__ . '/../' . str_replace(['..', chr(92)], ['', '/'], $try2);
              $abs3 = __DIR__ . '/../' . str_replace(['..', chr(92)], ['', '/'], $try3);
              if (is_file($abs1)) { $imgPath = $try1; }
              elseif (is_file($abs2)) { $imgPath = $try2; }
              elseif (is_file($abs3)) { $imgPath = $try3; }
              else { $imgPath = $try1; }
            }
            $title = $pf['title'] ?? 'Project';
            $desc = $pf['short_description'] ?? '';
            $url = !empty($pf['project_url']) ? $pf['project_url'] : (PUBLIC_BASE_PATH . '/portfolio.php?id=' . (int)($pf['id'] ?? 0));
          ?>
          <a href="<?php echo h($url); ?>" class="group block overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md dark:border-slate-700 dark:bg-slate-900" <?php echo !empty($pf['project_url']) ? 'target="_blank" rel="noopener"' : ''; ?> data-reveal="up">
            <?php if (!empty($img)): ?>
              <img class="h-44 w-full object-cover transition group-hover:scale-[1.02]" src="<?php echo h(mediaUrl($imgPath)); ?>" alt="<?php echo h($title); ?>" loading="lazy" decoding="async" width="1200" height="675" />
            <?php else: ?>
              <img class="h-44 w-full object-cover transition group-hover:scale-[1.02]"
                   src="https://images.unsplash.com/photo-1557800636-894a64c1696f?q=80&w=1200&auto=format&fit=crop"
                   srcset="https://images.unsplash.com/photo-1557800636-894a64c1696f?q=80&w=800&auto=format&fit=crop 800w, https://images.unsplash.com/photo-1557800636-894a64c1696f?q=80&w=1200&auto=format&fit=crop 1200w, https://images.unsplash.com/photo-1557800636-894a64c1696f?q=80&w=1600&auto=format&fit=crop 1600w"
                   sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw"
                   alt="<?php echo h($title); ?>" loading="lazy" decoding="async" width="1200" height="675" />
            <?php endif; ?>
            <div class="p-4">
              <h3 class="font-semibold"><?php echo h($title); ?></h3>
              <?php if (!empty($desc)): ?><p class="mt-1 text-xs text-slate-500"><?php echo h($desc); ?></p><?php endif; ?>
            </div>
          </a>
        <?php endforeach; else: ?>
          <div class="col-span-3 text-center text-slate-400 text-sm">Belum ada portofolio aktif.</div>
        <?php endif; ?>
      </div>
      <div class="mt-10 flex items-center justify-between">
        <div>
          <?php if ($page > 1): ?>
            <a class="text-sm text-blue-600 hover:underline" href="<?php echo PUBLIC_BASE_PATH; ?>/portfolio.php?page=<?php echo (int)($page - 1); ?>">← Sebelumnya</a>
          <?php endif; ?>
        </div>
        <div class="text-sm text-slate-500">Halaman <?php echo (int)$page; ?></div>
        <div>
          <?php if ($hasNext): ?>
            <a class="text-sm text-blue-600 hover:underline" href="<?php echo PUBLIC_BASE_PATH; ?>/portfolio.php?page=<?php echo (int)($page + 1); ?>">Berikutnya →</a>
          <?php endif; ?>
        </div>
      </div>
      <div class="mt-6 text-center">
        <a href="<?php echo PUBLIC_BASE_PATH; ?>/index.php#portofolio" class="text-sm text-slate-500 hover:underline">Ke Beranda</a>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
