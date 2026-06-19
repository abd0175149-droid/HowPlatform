<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>تقرير الأداء</title>
  <style>
    @page { size: A4 portrait; margin: 14mm; }
    body{ font-family: DejaVu Sans, Almarai, sans-serif; color:#111; }
    h2{ margin:0 0 10px; }
    .muted{ color:#6b7280; font-size:12px; margin-bottom:10px; }
    table{ width:100%; border-collapse:collapse; }
    th,td{ border:1px solid #d9e0ea; padding:6px 8px; font-size:12px; }
    thead th{ background:#f2f6fb; text-align:center; }
  </style>
</head>
<body>
  <h2>تقرير الأداء</h2>
  <div class="muted">تم إنشاء التقرير وفق الفلاتر المحددة.</div>

  @php
    $statusMap = ['on_time'=>'في الموعد','late'=>'متأخر','not_submitted'=>'غير مُسلّم'];
    $weekLabel = function($task){
      $w = $task->week ?? null;
      return $w ? ('أسبوع '.$w->week_number.' — '.($w->title ?? '')) : '';
    };
  @endphp

  <table>
    <thead>
      <tr>
        <th>الطالب</th>
        <th>الأسبوع</th>
        <th>المهمة</th>
        <th>حالة التسليم</th>
        <th>العلامة</th>
      </tr>
    </thead>
    <tbody>
    @forelse($rows as $p)
      <tr>
        <td>{{ $p->student->full_name ?? '' }}</td>
        <td>{{ $weekLabel($p->task) }}</td>
        <td>{{ $p->task->title ?? '' }}</td>
        <td>{{ $statusMap[strtolower($p->submission_status ?? '')] ?? ($p->submission_status ?? '') }}</td>
        <td>{{ $p->grade ?? '' }}</td>
      </tr>
    @empty
      <tr><td colspan="5">لا توجد نتائج</td></tr>
    @endforelse
    </tbody>
  </table>

  @if(request()->boolean('auto'))
  <script>
    window.addEventListener('load', function(){
      try{ window.focus(); }catch(e){}
      window.print(); // لا نغلق التبويب، ولا نفتح تبويب جديد — الطباعة تتم داخل IFrame مخفي من الواجهة
    });
  </script>
  @endif
</body>
</html>
