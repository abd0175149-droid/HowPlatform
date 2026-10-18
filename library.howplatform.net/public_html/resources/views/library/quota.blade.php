<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>استنفدت حصة التحميل اليومية | المكتبة الرقمية</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@400;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *{font-family:'Almarai',system-ui,Tahoma,sans-serif;box-sizing:border-box;}
    body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#eef2f5;padding:1.5rem;}
    .card{background:#fff;border-radius:20px;box-shadow:0 10px 40px rgba(1,48,70,.12);max-width:460px;width:100%;padding:2.25rem 2rem;text-align:center;}
    .ic{width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#ffbe34,#fd9e02);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.9rem;margin:0 auto 1.25rem;}
    h1{font-size:1.35rem;font-weight:800;color:#013046;margin:0 0 .6rem;}
    p{color:#5b6b74;font-size:.95rem;line-height:1.9;margin:0 0 1.5rem;}
    .n{font-weight:800;color:#013046;}
    .btns{display:flex;flex-direction:column;gap:.6rem;}
    .btn{display:flex;align-items:center;justify-content:center;gap:.5rem;padding:.8rem 1.2rem;border-radius:12px;font-weight:700;font-size:.95rem;text-decoration:none;transition:all .2s;}
    .btn-up{background:linear-gradient(135deg,#219ebc,#156683);color:#fff;box-shadow:0 6px 16px rgba(33,158,188,.3);}
    .btn-up:hover{filter:brightness(1.05);transform:translateY(-2px);}
    .btn-back{background:#f1f5f8;color:#013046;}
    .btn-back:hover{background:#e2ebef;}
  </style>
</head>
<body>
  <div class="card">
    <div class="ic"><i class="fas fa-hourglass-end"></i></div>
    <h1>استنفدت حصتك اليومية من التحميلات</h1>
    <p>
      حمّلت اليوم <span class="n">{{ number_format($used) }}</span> من أصل <span class="n">{{ number_format($limit) }}</span> ملفات مسموحة.
      عُد غدًا، أو رقِّ خطتك للحصول على حصة أكبر (حتى 50 تحميلًا يوميًا).
    </p>
    <div class="btns">
      <a class="btn btn-up" href="https://auth.howplatform.net/profile?active=plans">
        <i class="fas fa-crown"></i> ترقية الحساب — عرض الخطط
      </a>
      <a class="btn btn-back" href="{{ $back }}">
        <i class="fas fa-arrow-right"></i> العودة للمكتبة
      </a>
    </div>
  </div>
</body>
</html>
