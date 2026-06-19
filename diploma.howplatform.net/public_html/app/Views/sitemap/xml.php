<?= '<?xml version="1.0" encoding="UTF-8"?>' . "\n" ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $u): ?>
  <url>
    <loc><?= esc($u['loc']) ?></loc>
    <changefreq><?= esc($u['changefreq']) ?></changefreq>
    <priority><?= esc($u['priority']) ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
