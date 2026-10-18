<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>بطاقة | Voucher</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@400;700;800&display=swap" rel="stylesheet">
  <style>
    * { margin:0; padding:0; box-sizing:border-box; font-family:'Almarai', Tahoma, sans-serif; }
    :root{ --brand-deep:#013046; --brand:#116580; }
    /* البطاقة بحجم 85.6×54mm بلا هوامش (بطاقة قياسية) */
    @page { size: 85.6mm 54mm; margin: 0; }
    html, body { background:#525659; }

    .toolbar{ position:fixed; top:0; inset-inline:0; z-index:50; background:var(--brand-deep); color:#fff;
      display:flex; align-items:center; justify-content:center; gap:14px; padding:9px; font-size:13px; }
    .toolbar button{ background:#f99e1c; color:#03263a; border:0; border-radius:7px; padding:6px 16px; font-weight:800; cursor:pointer; font-family:inherit; }
    .wrap{ padding:58px 0 30px; }

    .card{ position:relative; width:85.6mm; height:54mm; margin:0 auto 10px; background:#fff; overflow:hidden;
      box-shadow:0 6px 22px rgba(0,0,0,.35); page-break-after:always;
      -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .card:last-child{ page-break-after:auto; }
    .card .bg{ position:absolute; inset:0; width:85.6mm; height:54mm; z-index:0; }

    /* رمز QR داخل إطار الأقواس على اليسار */
    .card .qr{ position:absolute; z-index:1; left:5.5mm; top:27mm; width:15mm; height:15mm; }
    .card .qr img{ width:100%; height:100%; display:block; }

    /* الحقول على اليمين (المنطقة البيضاء) */
    .card .fields{ position:absolute; z-index:1; top:15mm; right:7mm; width:49mm; text-align:right; }
    .card .fields .row{ font-size:6.6pt; line-height:1.4; margin-bottom:1.5mm; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .card .fields .lbl{ color:var(--brand-deep); font-weight:800; }
    .card .fields .val{ color:#1f2937; unicode-bidi:plaintext; }

    @media print{
      html, body{ background:#fff; }
      .toolbar{ display:none !important; }
      .wrap{ padding:0; }
      .card{ margin:0; box-shadow:none; }
    }
  </style>
</head>
<body>
  <div class="toolbar">
    <button onclick="window.print()">🖨️ طباعة</button>
    <span>المقاس 85.6×54mm — تُفتح نافذة الطباعة تلقائياً.</span>
  </div>

  <div class="wrap">
    @foreach($vouchers as $v)
      <div class="card">
        <img class="bg" src="{{ $bg }}" alt="">
        @if(!empty($v['qr']))<div class="qr"><img src="{{ $v['qr'] }}" alt="QR"></div>@endif
        <div class="fields">
          <div class="row"><span class="lbl">اسم المشتري:</span> <span class="val">{{ $v['buyerName'] }}</span></div>
          <div class="row"><span class="lbl">رقم هاتف المشتري:</span> <span class="val">{{ $v['phoneNumber'] }}</span></div>
          <div class="row"><span class="lbl">تفاصيل البطاقة:</span> <span class="val">{{ $v['cardDetails'] }}</span></div>
          <div class="row"><span class="lbl">التاريخ والوقت:</span> <span class="val">{{ $v['datetime'] }}</span></div>
        </div>
      </div>
    @endforeach
  </div>

  <script>
    window.addEventListener('load', function () {
      var printed = false;
      function go(){ if (printed) return; printed = true; window.print(); }
      if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function () { setTimeout(go, 150); });
        setTimeout(go, 2500);
      } else {
        setTimeout(go, 600);
      }
    });
  </script>
</body>
</html>
