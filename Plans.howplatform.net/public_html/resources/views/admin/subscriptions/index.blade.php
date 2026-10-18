@extends('layouts.admin')
@section('title','الاشتراكات | Plans')
@section('heading','اشتراكات المستخدمين')

@section('content')
  <div class="row g-3 mb-3">
    @php $sc=[['الكل',$stats['total'],'#116580'],['فعّالة الآن',$stats['active_now'],'#16a34a'],['مجدولة',$stats['scheduled'],'#f59e0b'],['منتهية',$stats['expired'],'#6b7280'],['ملغاة',$stats['canceled'],'#dc2626']]; @endphp
    @foreach($sc as [$l,$n,$c])
      <div class="col-6 col-md"><div class="stat"><div class="text-muted small">{{ $l }}</div><div class="n" style="color:{{ $c }}">{{ number_format($n) }}</div></div></div>
    @endforeach
  </div>

  <form method="get" class="row g-2 mb-3">
    <div class="col-md-4"><select name="status" class="form-select form-select-sm">
      <option value="">كل الحالات</option>
      @foreach(['active_now'=>'فعّالة الآن','active'=>'فعّال (الحالة)','scheduled'=>'مجدول','expired'=>'منتهٍ','canceled'=>'ملغى'] as $k=>$lbl)
        <option value="{{ $k }}" @selected(($f['status']??'')===$k)>{{ $lbl }}</option>
      @endforeach
    </select></div>
    <div class="col-md-4"><select name="plan_id" class="form-select form-select-sm">
      <option value="">كل الخطط</option>
      @foreach($plans as $p)<option value="{{ $p->id }}" @selected((int)($f['plan_id']??0)===$p->id)>{{ $p->name }}</option>@endforeach
    </select></div>
    <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">تصفية</button></div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>المستخدم</th><th>الخطة</th><th>الحالة</th><th>البداية</th><th>الانتهاء</th><th>المصدر</th><th></th>
        </tr></thead>
        <tbody>
          @forelse($subs as $s)
            @php
              $isActive = $s->status==='active' && (!$s->end_at || $s->end_at->isFuture());
              $badge = $isActive ? 'success' : ($s->status==='canceled' ? 'danger' : ($s->status==='scheduled' ? 'warning' : 'secondary'));
            @endphp
            <tr>
              <td>
                <div class="fw-bold">{{ $names[$s->user_id] ?? ('المستخدم '.$s->user_id) }}</div>
                <div class="text-muted small">معرّف {{ $s->user_id }}</div>
              </td>
              <td>{{ optional($s->plan)->name ?? '—' }} <span class="text-muted small">L{{ optional($s->plan)->level }}</span></td>
              <td><span class="badge bg-{{ $badge }}">{{ ['active'=>'فعّال','scheduled'=>'مجدول','expired'=>'منتهٍ','canceled'=>'ملغى'][$s->status] ?? $s->status }}</span></td>
              <td class="small">{{ optional($s->start_at)->toDateString() ?? '—' }}</td>
              <td class="small">{{ optional($s->end_at)->toDateString() ?? '—' }}</td>
              <td class="small text-muted">{{ $s->source }}</td>
              <td class="text-end"><a href="{{ route('admin.users.index', ['u' => $s->user_id]) }}" class="btn btn-sm btn-outline-secondary">إدارة</a></td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-4">لا اشتراكات مطابقة.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3">{{ $subs->links() }}</div>
@endsection
