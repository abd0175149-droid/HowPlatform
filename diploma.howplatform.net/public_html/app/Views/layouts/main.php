<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title><?= esc($page_title ?? 'منصة كيف التعليمية') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- الخطوط -->
  <link href="https://fonts.googleapis.com/css2?family=Almarai&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- التنسيقات العامة -->
  <link rel="stylesheet" href="<?= base_url('assets/css/global.css') ?>">

  <!-- تنسيقات الصفحة الخاصة -->
  <?= $this->renderSection('styles') ?>
  <?php if (isset($isProfilePage) && $isProfilePage): ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/survey.css') ?>">
  <?php endif; ?>
</head>
<body style="font-family: 'Almarai', sans-serif;">



  <main>
    <?= $this->renderSection('content') ?>
  </main>



  <!-- سكربتات عامة -->
  <script defer src="<?= base_url('assets/js/global.js') ?>"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <!-- سكربتات خاصة -->
  <?= $this->renderSection('scripts') ?>
  <script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "منصة كيف التعليمية",
  "url": "https://diploma.howplatform.net/",
  "logo": "https://diploma.howplatform.net/assets/images/Logo_how_gr.svg",
  "sameAs": []
}
</script>

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "name": "منصة كيف التعليمية",
  "url": "https://diploma.howplatform.net/",
  "potentialAction": {
    "@type": "SearchAction",
    "target": "https://diploma.howplatform.net/search?q={search_term_string}",
    "query-input": "required name=search_term_string"
  }
}
</script>

</body>
</html>
