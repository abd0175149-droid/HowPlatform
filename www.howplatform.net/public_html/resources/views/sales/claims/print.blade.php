<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>مطالبة {{ $claim->claim_code }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{
  --primary:#0a3247; --accent:#fd9e02;
  --text:#0d2a3a; --muted:#6b7280;
}
body{font-family:Tahoma, Arial, sans-serif;color:var(--text);background:#fff;margin:0}
.wrapper{max-width:900px;margin:24px auto;padding:24px;border:1px solid #e5e7eb;border-radius:16px}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px}
.brand{display:flex;align-items:center;gap:12px}
.brand .logo{width:60px;height:60px;border-radius:12px;object-fit:cover;border:1px solid #eee}
.brand .name{font-weight:900;font-size:20px;color:var(--primary)}
.doc-title{font-weight:900;font-size:22px;color:var(--primary);padding-inline-start:.6rem;border-inline-start:8px solid var(--accent)}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:14px 0 18px}
.box{border:1px solid #e5e7eb;border-radius:12px;padding:12px}
.box .label{font-size:12px;color:var(--muted)}
.box .value{font-size:16px;font-weight:700}
.table{width:100%;border-collapse:collapse;margin-top:6px}
.table th,.table td{border:1px solid #e5e7eb;padding:8px;text-align:center}
.table thead th{background:#f3f4f6}
.footer{display:flex;justify-content:space-between;margin-top:18px;color:#555}
@media print{
  .no-print{display:none !important}
  .wrapper{border:none;margin:0;padding:0}
  body{margin:12mm}
}
</style>
</head>
<body>

<div class="wrapper">
  <div class="header">
    <div class="brand">
      <img src="{{ asset('assets/images/logo.png') }}" alt="logo" style="height:48px;">
      <div class="name">HOW Platform — وحدة المبيعات</div>
    </div>
    <div class="no-print">
      <button onclick="window.print()" style="padding:.5rem .9rem;border-radius:10px;border:1px solid #0a3247;background:#0a3247;color:#fff;font-weight:800">طباعة</button>
    </div>
  </div>

  <div class="doc-title">مطالبة مبيعات: {{ $claim->claim_code }}</div>

  <div class="grid">
    <div class="box">
      <div class="label">المندوب</div>
      <div class="value">{{ $claim->user_name ?? 'غير معروف' }} <small class="muted">(#{{ $claim->user_id }})</small></div>
    </div>
    <div class="box">
      <div class="label">الفترة</div>
      <div class="value">{{ $claim->date_from }} → {{ $claim->date_to }}</div>
    </div>
    <div class="box">
      <div class="label">عدد الفواتير</div>
      <div class="value">{{ (int)$claim->invoices_count }}</div>
    </div>
    <div class="box">
      <div class="label">الإجمالي المستحق</div>
      <div class="value">{{ number_format((float)$claim->invoices_total, 2) }} JOD</div>
    </div>
  </div>

  <table class="table">
    <thead>
      <tr>
        <th>#</th>
        <th>رقم العملية (TX)</th>
        <th>كود الفاوتر</th>
        <th>الخطة</th>
        <th>المنصة</th>
        <th>السعر</th>
        <th>الصافي</th>
        <th>تاريخ البيع</th>
      </tr>
    </thead>
    <tbody>
      @forelse($items as $row)
        <tr>
          <td>{{ $row->id }}</td>
          <td>{{ $row->tx_doc_code ?? '—' }}</td>
          <td>{{ $row->voucher_code }}</td>
          <td>{{ $row->plan_name }}</td>
          <td>{{ $row->platform }}</td>
          <td>{{ number_format((float)$row->price_amount, 2) }}</td>
          <td><strong>{{ number_format((float)($row->net_due ?? $row->amount), 2) }}</strong></td>
          <td>{{ $row->sold_at ? \Illuminate\Support\Carbon::parse($row->sold_at)->format('Y-m-d H:i') : '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="8">لا توجد عناصر</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="footer">
    <div>تاريخ الإنشاء: {{ \Illuminate\Support\Carbon::parse($claim->created_at)->format('Y-m-d H:i') }}</div>
    <div>حالة المطالبة: {{ strtoupper($claim->status) }}</div>
  </div>
</div>

</body>
</html>
