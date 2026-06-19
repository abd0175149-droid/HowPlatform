<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>رسوم الترقية | منصة كيف؟ التعليمية</title>
</head>
<body style="font-family:'Almarai',sans-serif;background:#f9f9f9;margin:0;padding:0">
  <div style="max-width:600px;margin:auto;background:#fff;padding:40px;border-radius:8px;box-shadow:0 0 10px rgba(0,0,0,.05)">

    <!-- ـــــــ رأس الرسالة ـــــــ -->
    <div style="text-align:center">
      <img src="https://diploma.howplatform.net/assets/images/logo.png"
           alt="شعار منصة كيف؟" style="width:120px;margin-bottom:20px">
      <h2 style="color:#002147">رسوم ترقية الحساب على
        <span style="color:#1da1f2">منصة كيف؟ التعليمية</span>
      </h2>
    </div>

    <!-- ـــــــ محتوى ديناميكي ـــــــ -->
    <p style="font-size:1.1rem;color:#333;margin-top:30px">
      مرحبًا <strong><?= esc($name) ?></strong>،
    </p>

    <p style="font-size:1rem;color:#333;line-height:1.8">
      شكرًا لاهتمامك بترقية حسابك إلى الخطة
      <strong><?= esc($planName) ?></strong>.<br>
      يرجى سداد رسوم الترقية وقدرها
      <span style="color:#1da1f2;font-weight:bold"><?= esc($amount) ?> دينار</span>
      عبر إحدى طرق الدفع التالية:
    </p>

    <!-- طرق الدفع -->
    <div style="background:#f2f7ff;border:1px solid #c9e1ff;padding:20px;border-radius:6px">
      <?= $methods /* مثال يوضَّح أدناه */ ?>
    </div>

    <p style="font-size:0.95rem;color:#555;margin-top:25px">
      بعد الدفع، يُرجى الرد على هذا البريد أو التواصل عبر واتساب لسرعة تفعيل الترقية.<br>
      فريقنا جاهز لمساعدتك في أي وقت.
    </p>

    <!-- ـــــــ تذييل ـــــــ -->
    <hr style="margin:30px 0;border:none;border-top:1px solid #eee">

    <p style="font-size:0.9rem;color:#999;text-align:center">
      لأي استفسار إضافي تواصل معنا عبر
      <a href="mailto:support@howplatform.net">support@howplatform.net</a>
    </p>

    <p style="font-size:0.8rem;color:#aaa;text-align:center">
      هذا البريد أُرسل تلقائيًا، لا ترد عليه.
    </p>
  </div>
</body>
</html>
