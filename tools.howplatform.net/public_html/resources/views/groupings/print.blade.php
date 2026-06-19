@extends('layouts.app')
@section('title','طباعة المجموعات')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/groupings.css') }}">
<style>
  /* تنسيقات خفيفة للطباعة مع الحفاظ على الهوية */
  .panel{background:var(--surface-card);border:1px solid var(--border-color);border-radius:12px;box-shadow:var(--shadow-sm);padding:1.1rem}
  .panel-title{font-weight:800;margin-bottom:.6rem;color:var(--text-primary);display:flex;align-items:center;gap:.5rem;font-size:1.05rem;padding-bottom:.45rem;border-bottom:1px solid var(--border-color)}
  .btn{display:inline-flex;align-items:center;gap:.4rem;border-radius:10px;padding:.55rem .8rem;border:1px solid var(--border-color);background:var(--surface-2)}
  .btn-primary{border-color:var(--primary-500);background:var(--primary-500);color:#fff}
  .btn-ghost{background:transparent}
  .hint{color:var(--text-secondary);font-size:.9rem}

  .print-shell{max-width:1100px;margin:0 auto;display:flex;flex-direction:column;gap:1rem}
  .print-sheet{background:#fff;border:1px solid var(--border-color);border-radius:12px;padding:1.2cm}
  .print-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:.9cm;border-bottom:1px solid #ddd;padding-bottom:.35cm}
  .print-header .title{font-weight:800;font-size:1.15rem;color:#111}
  .print-meta{color:#555;font-size:.95rem}

  .print-groups{
    display:grid;grid-template-columns:repeat(2,1fr);gap:.8cm;
  }
  @media screen and (max-width: 980px){
    .print-groups{grid-template-columns:1fr}
  }

  .print-card{
    border:1px solid #ddd;border-radius:12px;padding:.7cm;break-inside:avoid;page-break-inside:avoid;background:#fff;
  }
  .print-card h3{margin:0 0 .4cm;font-weight:800;font-size:1.05rem}
  .print-members{display:flex;flex-wrap:wrap;gap:.28cm}
  .print-tag{
    border:1px solid #ccc;border-radius:999px;padding:.1cm .35cm;font-size:.95rem
  }

  /* عناصر لا تُطبع */
  @media print{
    .no-print, .steps-bar, header, nav, footer, .sidebar, .sidebar-section, .sidebar-link{ display:none !important; }
    body{ background:#fff !important; }
    .print-sheet{ border:none; }
  }
</style>
@endpush

@section('content')
  {{-- شريط الخطوات (لا يطبع) --}}
  @include('groupings._steps', ['active'=>'print', 'session'=>$session ?? null])

  @php
    $groups = $groups ?? collect();

    // مساعد اسم المجموعة
    $groupDisplay = function($g, $i){
      $n = $g->display_name ?? $g->name ?? null;
      if ($n) return (string)$n;
      $idx = isset($g->group_index) ? ((int)$g->group_index + 1) : ($i+1);
      return 'مجموعة ' . $idx;
    };

    // استخراج الأعضاء (دعم object/array)
    $membersOf = function($g) use ($groups){
      if (isset($g->members) && $g->members instanceof \Illuminate\Support\Collection) {
        return $g->members;
      }
      if (is_array($g->members ?? null)) {
        return collect($g->members);
      }
      return collect();
    };

    $className = $session->class_name ?? ($session->class ?? $session->name ?? '—');
    $title     = $session->title ?? $session->session_title ?? null;
  @endphp

  <div class="print-shell">
    {{-- شريط علوي للتنقل والطباعة (لا يطبع) --}}
    <div class="panel no-print">
      <div class="panel-title"><i class="fas fa-print"></i> الطباعة</div>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;justify-content:space-between;align-items:center">
        <div class="hint">راجع أسماء المجموعات قبل الطباعة من صفحة المعاينة إن رغبت.</div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
          <a class="btn btn-ghost" href="{{ route('user.groupings.show', $session->id) }}"><i class="fas fa-eye"></i> العودة للمعاينة</a>
          <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> طباعة الآن</button>
        </div>
      </div>
    </div>

    {{-- ورقة الطباعة --}}
    <section class="print-sheet" dir="rtl">
      <header class="print-header">
        <div>
          <div class="title">توزيع المجموعات {{ $title ? ('— ' . e($title)) : '' }}</div>
          <div class="print-meta">الصف: {{ $className }}</div>
        </div>
        <div class="print-meta">{{ now()->format('Y-m-d') }}</div>
      </header>

      @if($groups->isEmpty())
        <div class="hint">لا يوجد توزيع لطباعته. أنشئ التوزيع من صفحة القيود أو اعرضه من المعاينة.</div>
      @else
        <div class="print-groups">
          @foreach($groups as $i => $g)
            @php
              $groupTitle = $groupDisplay($g, $i);
              $mems = $membersOf($g)->map(function($m){
                if (is_object($m)) return $m->student_name_snapshot ?? $m->name ?? '';
                if (is_array($m))  return $m['student_name_snapshot'] ?? $m['name'] ?? '';
                return (string)$m;
              })->filter()->values();
            @endphp
            <article class="print-card">
              <h3>{{ $groupTitle }}</h3>
              <div class="print-members">
                @forelse($mems as $nm)
                  <span class="print-tag">{{ $nm }}</span>
                @empty
                  <em style="color:#999">لا أعضاء</em>
                @endforelse
              </div>
            </article>
          @endforeach
        </div>
      @endif
    </section>
  </div>
@endsection
