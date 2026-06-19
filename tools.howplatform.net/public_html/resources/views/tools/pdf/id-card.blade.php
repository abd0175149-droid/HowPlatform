@php
  // استخدم DejaVu Sans لدعم العربية (أو ثبّت خطك المفضّل في dompdf)
  $font = "'DejaVu Sans', sans-serif";
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 0; size: A6 {{ $orientation ?? 'portrait' }}; }
    html, body { margin:0; padding:0; direction:rtl; font-family: {!! $font !!}; color:#111; }
    .card{
      width:100%; height:100%;
      box-sizing:border-box; padding:12mm 10mm;
      display:grid; grid-template-rows:auto auto 1fr; gap:6mm;
      background:#fff; position:relative;
    }

    /* قوالب */
    .tpl-classic .head{ border-bottom:2px solid #f3a56a; padding-bottom:3mm; }
    .tpl-minimal .head{ background:#faf5ee; padding:4mm 5mm; border-radius:6px; }
    .tpl-gradient .head{ background:linear-gradient(90deg,#fb923c,#ea580c); color:#fff; padding:5mm; border-radius:6px; }
    .tpl-stripe .card{ background:
        linear-gradient( to bottom, #fff 0, #fff calc(100% - 12mm), #fb923c calc(100% - 12mm) 100% ); }
    .tpl-badge .badge{ position:absolute; top:8mm; left:10mm; background:#fb923c; color:#fff; padding:2mm 4mm; border-radius:4mm; font-weight:bold; }

    .title{ font-size:12pt; font-weight:900; text-align:center; }
    .year{ font-size:10pt; font-weight:700; text-align:center; margin-top:1mm; }
    .brand{ display:flex; align-items:center; gap:6mm; justify-content:center; margin-top:2mm; }
    .brand img{ height:16mm; width:auto; }
    .brand .uname{ font-size:11pt; font-weight:800; }

    .fields{ display:grid; gap:4mm; margin-top:2mm; }
    .field{ display:grid; grid-template-columns:auto 1fr; gap:4mm; align-items:center; }
    .label{ font-weight:800; color:#444; }
    .value{ font-weight:900; color:#111; }
  </style>
</head>
<body class="{{ $orientation ?? 'portrait' }}">
  <div class="card tpl-{{ $template ?? 'classic' }}">
    @if(($template ?? '') === 'badge')
      <div class="badge">الدبلوم العالي</div>
    @endif

    <div class="head">
      <div class="title">الدبلوم العالي لإعداد المعلمين</div>
      <div class="year">{{ $year }}</div>
    </div>

    <div class="brand">
      <img src="{{ $universityLogo }}" alt="شعار الجامعة">
      <div class="uname">{{ $universityName }}</div>
    </div>

    <div class="fields">
      <div class="field">
        <div class="label">الاسم:</div>
        <div class="value">{{ $studentName }}</div>
      </div>
      <div class="field">
        <div class="label">التخصص:</div>
        <div class="value">{{ $major }}</div>
      </div>
    </div>
  </div>
</body>
</html>
