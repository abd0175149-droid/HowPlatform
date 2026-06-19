<!doctype html>
<html lang="ar" dir="rtl">
<head>
      <?= $this->include('partials/header') ?>

  <meta charset="utf-8">
  <title>شكرًا لمساهمتك</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:inherit;background:var(--surface,#fff);color:var(--text,#222);margin:0}
    .wrap{max-width:800px;margin:10vh auto;padding:2rem;text-align:center}
    h1{color:var(--primary-darker,#1e3a8a)}
    p{opacity:.9;line-height:1.8}
    a.btn{display:inline-block;margin-top:1.5rem;padding:.75rem 1.25rem;border-radius:10px;background:var(--primary,#3b82f6);color:#fff;text-decoration:none}
  </style>
</head>
<body>
  <div class="wrap">
    <h1>شكرًا لمساهمتك القيّمة</h1>
    <p>ستُستخدم نتائج هذا الاستبيان في تحسين تجربة طلبة الدبلوم وبناء منصة دعم تُعالج احتياجاتهم الفعلية.</p>
    <a href="<?= site_url() ?>" class="btn">عودة للصفحة الرئيسية</a>
  </div>
  
    <?= $this->include('partials/footer') ?>

</body>
</html>
