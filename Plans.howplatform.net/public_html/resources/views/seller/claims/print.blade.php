<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>مطالبة {{ $claim->claim_code ?? $claim->id }}</title>
  <link href="https://fonts.googleapis.com/css2?family=Almarai:wght@400;700;800&display=swap" rel="stylesheet">
  <style>
    *{font-family:'Almarai',system-ui,'Segoe UI',Tahoma,sans-serif;box-sizing:border-box;}
    body{margin:0;padding:24px;color:#013046;background:#fff;}
    .top{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #f99e1c;padding-bottom:12px;margin-bottom:18px;}
    .top h1{margin:0;font-size:1.4rem;}
    .muted{color:#6b7c84;font-size:.85rem;}
    .meta{display:flex;gap:24px;flex-wrap:wrap;margin-bottom:18px;}
    .meta div b{display:block;color:#6b7c84;font-weight:700;font-size:.78rem;}
    table{width:100%;border-collapse:collapse;font-size:.85rem;}
    th,td{border:1px solid #d6dee2;padding:7px 9px;text-align:center;}
    thead th{background:#013046;color:#fff;font-weight:700;}
    .total{margin-top:16px;text-align:left;font-size:1.05rem;font-weight:800;}
    .st{font-size:.78rem;font-weight:700;}
    .paid{color:#1c7a45;} .unpaid{color:#c0392b;}
    @media print{.noprint{display:none;}}
    .noprint{margin-bottom:16px;}
    .btn{background:#f99e1c;color:#fff;border:none;border-radius:8px;padding:.5rem 1rem;font-weight:700;cursor:pointer;}
  </style>
</head>
<body>
  <div class="noprint"><button class="btn" onclick="window.print()">طباعة</button></div>

  <div class="top">
    <div>
      <h1>مطالبة مبيعات</h1>
      <div class="muted">منصّة الاشتراكات والنقاط — Plans</div>
    </div>
    <div style="text-align:left">
      <div style="font-size:1.2rem;font-weight:800">{{ $claim->claim_code }}</div>
      <div class="muted">{{ \Illuminate\Support\Str::of((string)$claim->created_at)->limit(16,'') }}</div>
    </div>
  </div>

  <div class="meta">
    <div><b>المندوب</b>{{ $claim->user_name }} (UID {{ $claim->user_id }})</div>
    <div><b>الفترة</b><span dir="ltr">{{ $claim->date_from ?: '—' }} → {{ $claim->date_to ?: '—' }}</span></div>
    <div><b>عدد البطاقات</b>{{ number_format($claim->invoices_count) }}</div>
    <div><b>الحالة</b>{{ $claim->status === 'paid' ? 'مدفوعة' : 'مفتوحة' }}</div>
  </div>

  <table>
    <thead>
      <tr><th>#</th><th>كود البطاقة</th><th>الخطة</th><th>المشتري</th><th>المبلغ</th><th>المسدّد</th><th>الحالة</th></tr>
    </thead>
    <tbody>
      @forelse($items as $row)
        <tr>
          <td>{{ $row->id }}</td>
          <td dir="ltr">{{ $row->card_code ?? '—' }}</td>
          <td>{{ $row->plan_name }}</td>
          <td>{{ $row->buyer_name ?: '—' }}</td>
          <td>{{ number_format((float)$row->amount,2) }}</td>
          <td>{{ number_format((float)$row->paid_amount,2) }}</td>
          <td class="st {{ (int)$row->is_paid === 1 ? 'paid' : 'unpaid' }}">{{ (int)$row->is_paid === 1 ? 'مدفوع' : 'غير مدفوع' }}</td>
        </tr>
      @empty
        <tr><td colspan="7">لا توجد بطاقات ضمن هذه المطالبة.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="total">
    الإجمالي: {{ number_format((float)$claim->invoices_total,2) }} JOD &nbsp;|&nbsp; المسدّد: {{ number_format($paidTotal,2) }} JOD
  </div>
</body>
</html>
