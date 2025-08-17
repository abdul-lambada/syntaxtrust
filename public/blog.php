<?php
// blog.php - Archive and detail view for blog posts
// Depends on: config/database.php, public/includes/header.php, public/includes/footer.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php'; // ensures consistent session name if needed

// Basic helpers
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 9;
$hasNext = false;
$post = null;
$posts = [];

try {
  if ($slug !== '') {
    $stmt = $pdo->prepare("SELECT id, title, slug, excerpt, content, featured_image, published_at FROM blog_posts WHERE slug = :slug AND status = 'published' LIMIT 1");
    $stmt->execute([':slug' => $slug]);
    $post = $stmt->fetch();
  }

  if (!$post) {
    // Archive listing with pagination
    $offset = ($page - 1) * $perPage;
    $stmt = $pdo->prepare("SELECT id, title, slug, excerpt, featured_image, published_at FROM blog_posts WHERE status = 'published' ORDER BY published_at DESC, id DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $perPage + 1, PDO::PARAM_INT); // fetch one extra to infer next page
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    if (count($rows) > $perPage) {
      $hasNext = true;
      $posts = array_slice($rows, 0, $perPage);
    } else {
      $posts = $rows;
    }
  }
} catch (Exception $e) {
  // fail silently to simple empty states
  $post = $post ?: null;
  $posts = $posts ?: [];
}

// SEO meta: title & description
if ($post) {
  $pageTitle = ($post['title'] ?? 'Artikel') . ' | Blog | SyntaxTrust';
  $desc = trim((string)($post['excerpt'] ?? ''));
  if ($desc === '') {
    // derive from content (strip tags, limit length)
    $raw = (string)($post['content'] ?? '');
    $raw = trim(preg_replace('/\s+/', ' ', strip_tags($raw)) ?? '');
    $desc = mb_substr($raw, 0, 160);
  }
  $pageDesc = $desc;
  // Reading time estimation (~200 wpm)
  $contentText = trim(preg_replace('/\s+/', ' ', strip_tags((string)($post['content'] ?? ''))) ?? '');
  $wordCount = $contentText !== '' ? str_word_count($contentText) : str_word_count((string)($post['excerpt'] ?? ''));
  $readingMinutes = max(1, (int)ceil(($wordCount ?: 0) / 200));
  // Canonical and OG image for detail view
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $canonicalUrl = $scheme . '://' . $host . '/syntaxtrust/public/blog.php?slug=' . urlencode($post['slug'] ?? '');
  $ogImage = !empty($post['featured_image']) ? (strpos($post['featured_image'], 'http') === 0 ? $post['featured_image'] : ($scheme . '://' . $host . $post['featured_image'])) : null;
  // Previous/Next for detail view
  try {
    $prevStmt = $pdo->prepare("SELECT slug, title FROM blog_posts WHERE status='published' AND (published_at > :pa OR (published_at = :pa AND id > :id)) ORDER BY published_at ASC, id ASC LIMIT 1");
    $prevStmt->execute([':pa' => $post['published_at'], ':id' => $post['id']]);
    $prevPost = $prevStmt->fetch() ?: null;
    $nextStmt = $pdo->prepare("SELECT slug, title FROM blog_posts WHERE status='published' AND (published_at < :pa OR (published_at = :pa AND id < :id)) ORDER BY published_at DESC, id DESC LIMIT 1");
    $nextStmt->execute([':pa' => $post['published_at'], ':id' => $post['id']]);
    $nextPost = $nextStmt->fetch() ?: null;
  } catch (Exception $e) {
    $prevPost = $prevPost ?? null;
    $nextPost = $nextPost ?? null;
  }
} else {
  $pageTitle = 'Blog | SyntaxTrust';
  $pageDesc = 'Artikel terbaru dari SyntaxTrust.';
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $canonicalUrl = $scheme . '://' . $host . '/syntaxtrust/public/blog.php' . ($page > 1 ? ('?page=' . (int)$page) : '');
  $ogImage = null;
  // rel prev/next for archive pagination
  if ($page > 1) {
    $relPrevUrl = $scheme . '://' . $host . '/syntaxtrust/public/blog.php' . ($page - 1 > 1 ? ('?page=' . (int)($page - 1)) : '');
  }
  if ($hasNext) {
    $relNextUrl = $scheme . '://' . $host . '/syntaxtrust/public/blog.php?page=' . (int)($page + 1);
  }
}

include __DIR__ . '/includes/header.php';
?>

<main class="min-h-screen py-16 bg-slate-50 dark:bg-slate-900/30">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <?php if (!empty($post)): ?>
      <!-- Article JSON-LD for blog detail -->
      <script type="application/ld+json">
        <?php
          $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
          $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
          $postUrl = $scheme . '://' . $host . '/syntaxtrust/public/blog.php?slug=' . urlencode($post['slug'] ?? '');
          $imageAbs = null;
          if (!empty($post['featured_image'])) {
            $imageAbs = (strpos($post['featured_image'], 'http') === 0) ? $post['featured_image'] : ($scheme . '://' . $host . $post['featured_image']);
          }
          $articleJson = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'mainEntityOfPage' => [
              '@type' => 'WebPage',
              '@id' => $postUrl,
            ],
            'headline' => (string)($post['title'] ?? 'Artikel'),
            'image' => $imageAbs ? [$imageAbs] : [],
            'datePublished' => !empty($post['published_at']) ? date('c', strtotime($post['published_at'])) : null,
            'dateModified' => !empty($post['published_at']) ? date('c', strtotime($post['published_at'])) : null,
            'author' => [
              '@type' => 'Organization',
              'name' => 'SyntaxTrust',
            ],
            'publisher' => [
              '@type' => 'Organization',
              'name' => 'SyntaxTrust',
            ],
            'description' => (string)($pageDesc ?? ''),
          ];
          echo json_encode($articleJson, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        ?>
      </script>
    <?php endif; ?>
    <?php if ($post): ?>
      <nav class="text-sm mb-6 text-slate-500" data-reveal="down"><a class="hover:text-slate-700" href="/syntaxtrust/public/blog.php">Blog</a> <span class="mx-1">/</span> <span class="text-slate-700 dark:text-slate-300"><?php echo h($post['title'] ?? ''); ?></span></nav>
      <article class="mx-auto max-w-3xl overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900" data-reveal="up">
        <header>
          <h1 class="text-3xl font-bold tracking-tight"><?php echo h($post['title'] ?? ''); ?></h1>
          <?php if (!empty($post['published_at'])): ?>
            <p class="mt-2 text-sm text-slate-500">Dipublikasikan: <?php echo h(date('d M Y', strtotime($post['published_at']))); ?> • Estimasi baca: <?php echo (int)$readingMinutes; ?> menit</p>
          <?php else: ?>
            <p class="mt-2 text-sm text-slate-500">Estimasi baca: <?php echo (int)$readingMinutes; ?> menit</p>
          <?php endif; ?>
        </header>
        <?php if (!empty($post['featured_image'])): ?>
          <img class="mt-6 h-72 w-full rounded-xl object-cover" src="<?php echo h($post['featured_image']); ?>" alt="<?php echo h($post['title'] ?? ''); ?>" loading="lazy" decoding="async" width="1200" height="675" />
        <?php endif; ?>
        <!-- Table of Contents -->
        <aside x-data="{ open: window.matchMedia('(min-width: 768px)').matches }" x-init="(() => { try { const mq = window.matchMedia('(min-width: 768px)'); const fn = () => open = mq.matches; mq.addEventListener ? mq.addEventListener('change', fn) : mq.addListener(fn); } catch(e) {} })()" class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-700 dark:bg-slate-800/40">
          <div class="flex items-center justify-between">
            <div class="font-semibold text-slate-700 dark:text-slate-200">Daftar Isi</div>
            <button type="button" class="rounded-md px-2 py-1 text-slate-600 hover:bg-white dark:text-slate-300 dark:hover:bg-slate-700 md:hidden" @click="open = !open" :aria-expanded="open.toString()" aria-controls="toc">Toggle</button>
          </div>
          <ul id="toc" x-show="open" x-collapse class="mt-2 space-y-1 list-disc pl-5 text-slate-600 dark:text-slate-300"></ul>
          <div class="mt-3">
            <a href="#content" class="text-sky-600 hover:underline">Kembali ke atas ↑</a>
          </div>
        </aside>
        <div id="article-content" class="prose prose-slate mt-6 max-w-none dark:prose-invert">
          <?php
            // Render content. If stored as HTML, echo raw; otherwise fallback to escaped.
            $content = $post['content'] ?? '';
            if ($content !== '') {
              echo $content; // assume trusted HTML from admin
            } else {
              echo '<p>' . h($post['excerpt'] ?? '') . '</p>';
            }
          ?>
        </div>
        <style>
          /* Offset for anchor jumps and active state for TOC */
          #article-content h2, #article-content h3 { scroll-margin-top: 90px; }
          #toc a.toc-active { color: rgb(2 132 199); font-weight: 600; }
          #toc a { text-underline-offset: 2px; }
        </style>
        <script>
          (function() {
            const container = document.getElementById('article-content');
            const toc = document.getElementById('toc');
            if (!container || !toc) return;
            const headings = container.querySelectorAll('h2, h3');
            if (!headings.length) { toc.parentElement.style.display = 'none'; return; }
            const linkMap = new Map();
            headings.forEach((h, idx) => {
              if (!h.id) {
                const base = (h.textContent || 'section').trim().toLowerCase().replace(/[^a-z0-9\s-]/g,'').replace(/\s+/g,'-').slice(0,60);
                let id = base || 'bagian-' + (idx+1);
                let dup = 1;
                while (document.getElementById(id)) { id = base + '-' + (++dup); }
                h.id = id;
              }
              const li = document.createElement('li');
              if (h.tagName.toLowerCase() === 'h3') li.style.marginLeft = '1rem';
              const a = document.createElement('a');
              a.href = '#' + h.id;
              a.textContent = h.textContent;
              a.className = 'hover:underline';
              a.addEventListener('click', function(ev) {
                ev.preventDefault();
                const target = document.getElementById(h.id);
                if (!target) return;
                const offset = 80; // adjust for sticky header if any
                const top = target.getBoundingClientRect().top + window.pageYOffset - offset;
                const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                window.scrollTo({ top, behavior: reduce ? 'auto' : 'smooth' });
                history.replaceState(null, '', '#' + h.id);
              });
              li.appendChild(a);
              toc.appendChild(li);
              linkMap.set(h.id, a);
            });

            // Active section highlighting
            const io = new IntersectionObserver((entries) => {
              entries.forEach(entry => {
                const id = entry.target.id;
                const link = linkMap.get(id);
                if (!link) return;
                if (entry.isIntersecting) {
                  // remove previous
                  toc.querySelectorAll('a.toc-active').forEach(el => el.classList.remove('toc-active'));
                  toc.querySelectorAll('a[aria-current="true"]').forEach(el => el.removeAttribute('aria-current'));
                  link.classList.add('toc-active');
                  link.setAttribute('aria-current','true');
                }
              });
            }, { rootMargin: '-60% 0px -35% 0px', threshold: [0, 1] });
            headings.forEach(h => io.observe(h));
          })();
        </script>
      </article>
      <div class="mx-auto max-w-3xl mt-8 grid grid-cols-3 items-center">
        <div class="justify-self-start">
          <?php if (!empty($prevPost)): ?>
            <a href="/syntaxtrust/public/blog.php?slug=<?php echo h($prevPost['slug']); ?>" class="text-sm text-blue-600 hover:underline">← <?php echo h($prevPost['title']); ?></a>
          <?php endif; ?>
        </div>
        <div class="justify-self-center">
          <a href="/syntaxtrust/public/blog.php" class="text-sm text-slate-500 hover:underline">Kembali ke Blog</a>
        </div>
        <div class="justify-self-end text-right">
          <?php if (!empty($nextPost)): ?>
            <a href="/syntaxtrust/public/blog.php?slug=<?php echo h($nextPost['slug']); ?>" class="text-sm text-blue-600 hover:underline">Berikutnya →</a>
          <?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <div class="mx-auto max-w-2xl text-center" data-reveal="down">
        <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Blog</h2>
        <p class="mt-3 text-slate-600 dark:text-slate-400">Artikel terbaru dari SyntaxTrust.</p>
      </div>
      <div class="mt-10 grid gap-6 md:grid-cols-3">
        <?php if (!empty($posts)): foreach ($posts as $bp): ?>
          <?php
            $img = $bp['featured_image'] ?? '';
            $title = $bp['title'] ?? 'Untitled';
            $excerpt = $bp['excerpt'] ?? '';
            $slugRow = $bp['slug'] ?? '';
            $url = $slugRow ? '/syntaxtrust/public/blog.php?slug=' . urlencode($slugRow) : '#';
          ?>
          <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md dark:border-slate-700 dark:bg-slate-900" data-reveal="up">
            <a href="<?php echo h($url); ?>">
              <?php if (!empty($img)): ?>
                <img class="h-40 w-full object-cover" src="<?php echo h($img); ?>" alt="<?php echo h($title); ?>" loading="lazy" decoding="async" width="1200" height="675" />
              <?php else: ?>
                <img class="h-40 w-full object-cover"
                  src="https://images.unsplash.com/photo-1498050108023-c5249f4df085?q=80&w=1200&auto=format&fit=crop"
                  srcset="https://images.unsplash.com/photo-1498050108023-c5249f4df085?q=80&w=800&auto=format&fit=crop 800w, https://images.unsplash.com/photo-1498050108023-c5249f4df085?q=80&w=1200&auto=format&fit=crop 1200w, https://images.unsplash.com/photo-1498050108023-c5249f4df085?q=80&w=1600&auto=format&fit=crop 1600w"
                  sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw"
                  alt="<?php echo h($title); ?>" loading="lazy" decoding="async" width="1200" height="675" />
              <?php endif; ?>
            </a>
            <div class="p-4">
              <h3 class="font-semibold"><a href="<?php echo h($url); ?>"><?php echo h($title); ?></a></h3>
              <?php if (!empty($excerpt)): ?><p class="mt-1 text-sm text-slate-500"><?php echo h($excerpt); ?></p><?php endif; ?>
            </div>
          </article>
        <?php endforeach; else: ?>
          <div class="col-span-3 text-center text-slate-400 text-sm">Belum ada artikel yang dipublikasikan.</div>
        <?php endif; ?>
      </div>
      <div class="mt-10 flex items-center justify-between">
        <div>
          <?php if ($page > 1): ?>
            <a class="text-sm text-blue-600 hover:underline" href="/syntaxtrust/public/blog.php?page=<?php echo (int)($page - 1); ?>">← Sebelumnya</a>
          <?php endif; ?>
        </div>
        <div class="text-sm text-slate-500">Halaman <?php echo (int)$page; ?></div>
        <div>
          <?php if ($hasNext): ?>
            <a class="text-sm text-blue-600 hover:underline" href="/syntaxtrust/public/blog.php?page=<?php echo (int)($page + 1); ?>">Berikutnya →</a>
          <?php endif; ?>
        </div>
      </div>
      <div class="mt-6 text-center">
        <a href="/syntaxtrust/public/index.php#blog" class="text-sm text-slate-500 hover:underline">Ke Beranda</a>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
