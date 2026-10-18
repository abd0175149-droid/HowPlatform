@extends('layouts.admin')
@section('title','البطاقات | Plans')
@section('heading','البطاقات')

@section('content')
  <div class="row g-3 mb-3">
    @php $sc=[['الكل',$stats['total'],'#116580'],['متاحة',$stats['active'],'#16a34a'],['مستبدَلة',$stats['redeemed'],'#6366f1'],['معطّلة',$stats['disabled'],'#dc2626']]; @endphp
    @foreach($sc as [$l,$n,$c])
      <div class="col-6 col-md-3"><div class="stat"><div class="text-muted small">{{ $l }}</div><div class="n" style="color:{{ $c }}">{{ number_format($n) }}</div></div></div>
    @endforeach
  </div>

  {{-- شريط أدوات --}}
  <div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('admin.cards.issue') }}" class="btn btn-brand btn-sm"><i class="fas fa-bolt"></i> إصدار دفعة</a>
    <a href="{{ route('admin.cards.redeem.form') }}" class="btn btn-success btn-sm"><i class="fas fa-circle-check"></i> تفعيل بطاقة لمستخدم</a>
    <a href="{{ route('admin.cards.vouchers', $f) }}" onclick="return openVoucher(this.href)" class="btn btn-outline-primary btn-sm"><i class="fas fa-print"></i> طباعة القسائم</a>
  </div>

  {{-- فلاتر (تُطبَّق تلقائيًا بلا زر) --}}
  <form method="get" id="cardFilters" class="row g-2 mb-3">
    <div class="col-md-4"><input type="text" name="q" value="{{ $f['q'] ?? '' }}" class="form-control form-control-sm" placeholder="بحث: كود، اسم/هاتف المشتري، أو مستخدم — ثم Enter"></div>
    <div class="col-md-3"><select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
      <option value="">كل الحالات</option>
      @foreach(['active'=>'متاحة','redeemed'=>'مستبدَلة','disabled'=>'معطّلة'] as $k=>$lbl)
        <option value="{{ $k }}" @selected(($f['status']??'')===$k)>{{ $lbl }}</option>
      @endforeach
    </select></div>
    <div class="col-md-3"><select name="batch" class="form-select form-select-sm" onchange="this.form.submit()">
      <option value="">كل الدفعات</option>
      @foreach($batches as $b)<option value="{{ $b }}" @selected(($f['batch']??'')===$b)>{{ $b }}</option>@endforeach
    </select></div>
    <div class="col-md-2"><select name="plan_id" class="form-select form-select-sm" onchange="this.form.submit()">
      <option value="">كل الخطط</option>
      @foreach($plans as $p)<option value="{{ $p->id }}" @selected((int)($f['plan_id']??0)===$p->id)>{{ $p->name }}</option>@endforeach
    </select></div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>الكود</th><th>النوع</th><th>الخطة</th><th>نقاط</th><th>السعر</th><th>الدفعة</th><th>الحالة</th><th>استُبدلت</th><th></th>
        </tr></thead>
        <tbody>
          @forelse($cards as $c)
            <tr>
              <td><code style="font-size:.95rem;">{{ implode('-', str_split($c->code,4)) }}</code></td>
              <td><span class="badge badge-soft">{{ ['subscription'=>'اشتراك','points'=>'نقاط','mixed'=>'مختلطة'][$c->type] ?? $c->type }}</span></td>
              <td>{{ optional($c->plan)->name ?? '—' }}</td>
              <td>{{ $c->points ? number_format($c->points) : '—' }}</td>
              <td class="small">{{ $c->price_amount !== null ? (rtrim(rtrim(number_format((float)$c->price_amount,2),'0'),'.').' '.$c->price_currency) : '—' }}</td>
              <td class="small text-muted">{{ $c->batch }}</td>
              <td>
                @php $b=['active'=>'success','redeemed'=>'secondary','disabled'=>'danger'][$c->status] ?? 'light'; @endphp
                <span class="badge bg-{{ $b }}">{{ ['active'=>'متاحة','redeemed'=>'مستبدَلة','disabled'=>'معطّلة'][$c->status] ?? $c->status }}</span>
              </td>
              <td class="small">
                @if($c->redeemed_by)
                  <span class="fw-bold">{{ $names[$c->redeemed_by] ?? ('مستخدم '.$c->redeemed_by) }}</span>
                  <div class="text-muted">{{ optional($c->redeemed_at)->diffForHumans() }}</div>
                @else — @endif
              </td>
              <td class="text-nowrap">
                <a href="{{ route('admin.cards.voucher',$c) }}" onclick="return openVoucher(this.href)" class="btn btn-sm btn-outline-primary" title="طباعة القسيمة"><i class="fas fa-print"></i></a>
                @if($c->status !== 'redeemed')
                  <form method="post" action="{{ route('admin.cards.toggle',$c) }}" class="d-inline">@csrf
                    <button class="btn btn-sm border-0 bg-transparent" title="تفعيل/تعطيل">
                      <i class="fas {{ $c->status==='active' ? 'fa-toggle-on text-success' : 'fa-toggle-off text-secondary' }} fa-lg"></i>
                    </button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="9" class="text-center text-muted py-4">لا توجد بطاقات مطابقة.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3">{{ $cards->links() }}</div>

  @push('scripts')
  <script>
  function openVoucher(url){
    var m = url.match(/cards\/(\d+)\/voucher/);
    var name = m ? ('howV_'+m[1]) : 'howV_bulk';
    var w = window.open(url, name, 'width=900,height=1180,scrollbars=yes,resizable=yes');
    if(!w){ window.open(url, '_blank'); } else { w.focus(); }
    return false;
  }
  </script>
  @endpush
@endsection
