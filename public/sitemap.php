<?php
header('Content-Type: application/xml; charset=UTF-8');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base = $scheme . '://' . $host . '/syntaxtrust/public';
$urls = [
  $base . '/index.php',
  $base . '/services.php',
  $base . '/pricing.php',
  $base . '/team.php',
  $base . '/testimonials.php',
  $base . '/faq.php',
  $base . '/contact.php',
  $base . '/blog.php',
  $base . '/portfolio.php',
];

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $u): ?>
  <url>
    <loc><?= htmlspecialchars($u, ENT_QUOTES, 'UTF-8') ?></loc>
    <changefreq>weekly</changefreq>
  </url>
<?php endforeach; ?>
</urlset>
