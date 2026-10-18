@extends('layouts.admin')
@section('title','المستخدمون | Plans')
@section('heading','المستخدمون والمحافظ')

@section('content')
  {{-- بحث --}}
  <div class="card mb-3">
    <div class="card-body">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-md-8">
          <label class="form-label small mb-1">ابحث بأي معلومة: الاسم، الإيميل، الهاتف، المعرّف، الجامعة، التخصص، نوع الدبلوم، السنة الدراسية، أو كود بطاقة</label>
          <input type="text" name="q" value="{{ $q ?? '' }}" class="form-control" placeholder="مثال: أحمد، ali@mail.com، 0791234567، 974، اليرموك، رياضيات، ABCD-1234" required>
        </div>
        <div class="col-md-3"><button class="btn btn-brand w-100"><i class="fas fa-search"></i> بحث</button></div>
      </form>
    </div>
  </div>

  {{-- نتائج البحث المتعدّدة --}}
  @if(!empty($results))
    <div class="card mb-3">
      <div class="card-body p-0">
        <div class="px-3 pt-3 small text-muted">نتائج المطابقة ({{ count($results) }}) — اختر مستخدمًا:</div>
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>الاسم</th><th>الإيميل</th><th>الهاتف</th><th>الجامعة</th><th>التخصص</th><th>الدبلوم / السنة</th><th>المعرّف</th><th></th></tr></thead>
          <tbody>
            @foreach($results as $rrow)
              @php $ac = $academic[$rrow['id']] ?? []; @endphp
              <tr>
                <td class="fw-bold">{{ $rrow['name'] ?? '—' }}</td>
                <td class="small text-muted">{{ $rrow['email'] ?? '—' }}</td>
                <td class="small">{{ $rrow['phone'] ?? '—' }}</td>
                <td class="small">{{ $ac['university'] ?? '—' }}</td>
                <td class="small">{{ $ac['specialty'] ?? '—' }}</td>
                <td class="small text-muted">{{ $ac['diploma_type'] ?? '—' }}@if(!empty($ac['academic_year'])) · {{ $ac['academic_year'] }}@endif</td>
                <td class="small text-muted">{{ $rrow['id'] }}</td>
                <td class="text-end"><a href="{{ route('admin.users.index', ['u' => $rrow['id']]) }}" class="btn btn-sm btn-outline-primary">عرض</a></td>
              </tr>
            @endforeach
          </tbody>
        </table>
        </div>
      </div>
    </div>
  @elseif($searched && $uid<=0)
    <div class="alert alert-warning">لا نتائج مطابقة. جرّب معلومة أخرى (اسم، إيميل، هاتف، جامعة، تخصص، كود بطاقة) أو أدخِل المعرّف الرقمي.</div>
  @endif

  @if($uid>0)
    @php $pf = $profile ?? ['name'=>null,'email'=>null,'phone'=>null]; $initial = mb_substr(trim((string)($pf['name'] ?? '')),0,1) ?: '#'; @endphp
    <div class="d-flex align-items-center gap-2 mb-3">
      <span class="av" style="width:44px;height:44px;border-radius:50%;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;">{{ $initial }}</span>
      <div>
        <div class="fw-bold" style="color:var(--brand-deep)">{{ $pf['name'] ?: ('المستخدم ' . $uid) }}</div>
        <div class="text-muted small">
          @if($pf['email'])<span>{{ $pf['email'] }}</span>@endif
          @if($pf['phone'])<span class="ms-2">· {{ $pf['phone'] }}</span>@endif
          <span class="ms-2">· معرّف {{ $uid }}</span>
          <span class="ms-2">· الرصيد: <b>{{ number_format($balance) }}</b> نقطة</span>
        </div>
      </div>
    </div>

    {{-- معلومات المستخدم الكاملة (auth + diploma) --}}
    @php
      $ac  = $acad ?? [];
      $acc = $account ?? [];
      $fmt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d H:i') : '—';
      $yn  = fn ($v) => $v ? 'نعم' : 'لا';
      $info = [
        'الجامعة'          => $ac['university'] ?? '—',
        'التخصص'           => $ac['specialty'] ?? '—',
        'نوع الدبلوم'       => $ac['diploma_type'] ?? '—',
        'السنة الدراسية'    => $ac['academic_year'] ?? '—',
        'نوع الحساب'        => $ac['account_type'] ?? '—',
        'الجنس'            => ['male' => 'ذكر', 'female' => 'أنثى'][$ac['gender'] ?? ''] ?? '—',
        'تاريخ الميلاد'     => $ac['birth_date'] ?? '—',
        'الملف مكتمل'       => $ac ? $yn($ac['is_complete'] ?? 0) : '—',
        'الحساب فعّال'      => $acc ? $yn($acc['is_active'] ?? 0) : '—',
        'الصلاحيات'         => $acc ? (trim((($acc['is_admin'] ?? 0) ? 'مشرف ' : '') . (($acc['is_sales'] ?? 0) ? 'بائع' : '')) ?: 'مستخدم') : '—',
        'طريقة الدخول'      => $acc ? (!empty($acc['google_id']) ? 'Google' : 'إيميل وكلمة مرور') : '—',
        'الإيميل موثّق'     => $acc ? $yn($acc['email_verified_at'] ?? null) : '—',
        'تاريخ التسجيل'     => $fmt($acc['created_at'] ?? ($ac['created_at'] ?? null)),
        'آخر دخول'          => $fmt($acc['last_login_at'] ?? ($ac['last_login_at'] ?? null)),
        'آخر ظهور (الدبلوم)' => $fmt($ac['last_seen_at'] ?? null),
        'آخر IP'            => $acc['last_login_ip'] ?? '—',
      ];
    @endphp
    <div class="card mb-3">
      <div class="card-body">
        <h2 class="h6 mb-3"><i class="fas fa-user-graduate"></i> معلومات المستخدم</h2>
        @if(!$ac)<div class="small text-muted mb-2">لا يوجد ملف دبلوم لهذا المستخدم (لم يسجّل في منصة الدبلوم).</div>@endif
        <div class="row g-2 small">
          @foreach($info as $label => $val)
            <div class="col-6 col-md-4 col-lg-3">
              <div class="text-muted">{{ $label }}</div>
              <div class="fw-semibold">{{ $val }}</div>
            </div>
          @endforeach
        </div>

        @if(isset($cards) && $cards->count())
          <hr>
          <div class="small text-muted mb-1">البطاقات المستبدَلة ({{ $cards->count() }})</div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>الكود</th><th>النوع</th><th>الخطة / النقاط</th><th>الدفعة</th><th>تاريخ الاستبدال</th></tr></thead>
              <tbody>
                @foreach($cards as $c)
                  <tr>
                    <td><a href="{{ route('admin.cards.index', ['q' => $c->code]) }}" class="font-monospace">{{ $c->code }}</a></td>
                    <td>{{ ['subscription'=>'اشتراك','points'=>'نقاط','mixed'=>'مختلطة'][$c->type] ?? $c->type }}</td>
                    <td>{{ optional($c->plan)->name ?? '' }}@if((int)$c->points > 0) {{ optional($c->plan)->name ? '·' : '' }} {{ number_format($c->points) }} نقطة @endif</td>
                    <td class="text-muted">{{ $c->batch ?? '—' }}</td>
                    <td class="text-muted">{{ optional($c->redeemed_at)->format('Y-m-d H:i') ?? '—' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>

    <div class="row g-4">
      {{-- الاشتراكات --}}
      <div class="col-lg-7">
        <div class="card mb-3">
          <div class="card-body">
            <h2 class="h6 mb-3"><i class="fas fa-id-card"></i> الاشتراكات</h2>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead><tr><th>الخطة</th><th>الحالة</th><th>الانتهاء</th><th></th></tr></thead>
                <tbody>
                  @forelse($subs as $s)
                    @php
                      $isActive = $s->status==='active' && $s->end_at && $s->end_at->isFuture();
                      $badge = $isActive ? 'success' : ($s->status==='canceled' ? 'danger' : 'secondary');
                    @endphp
                    <tr>
                      <td>{{ optional($s->plan)->name ?? '—' }} <span class="text-muted small">L{{ optional($s->plan)->level }}</span></td>
                      <td><span class="badge bg-{{ $badge }}">{{ ['active'=>'فعّال','scheduled'=>'مجدول','expired'=>'منتهٍ','canceled'=>'ملغى'][$s->status] ?? $s->status }}</span></td>
                      <td class="small">{{ optional($s->end_at)->toDateString() ?? '—' }}</td>
                      <td class="text-nowrap">
                        @if($s->status!=='canceled')
                          <form method="post" action="{{ route('admin.users.sub.extend',$s) }}" class="d-inline">@csrf
                            <input type="hidden" name="days" value="30">
                            <button class="btn btn-sm btn-outline-primary" title="+30 يوم"><i class="fas fa-plus"></i>30</button>
                          </form>
                          <form method="post" action="{{ route('admin.users.sub.cancel',$s) }}" class="d-inline" onsubmit="return confirm('إلغاء هذا الاشتراك؟');">@csrf
                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-ban"></i></button>
                          </form>
                        @endif
                      </td>
                    </tr>
                  @empty
                    <tr><td colspan="4" class="text-muted text-center py-3">لا اشتراكات.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>

            <hr>
            <h3 class="h6">منح اشتراك</h3>
            <form method="post" action="{{ route('admin.users.grant') }}" class="row g-2 align-items-end">
              @csrf <input type="hidden" name="user_id" value="{{ $uid }}">
              <div class="col-md-5">
                <label class="form-label small mb-1">الخطة</label>
                <select name="plan_id" class="form-select form-select-sm" required>
                  <option value="">— اختر —</option>
                  @foreach($plans as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->duration_days }} يوم)</option>@endforeach
                </select>
              </div>
              <div class="col-md-3"><label class="form-label small mb-1">أيام (اختياري)</label><input type="number" name="days" class="form-control form-control-sm" min="1" placeholder="من الخطة"></div>
              <div class="col-md-4">
                <div class="form-check mb-1"><input type="checkbox" class="form-check-input" name="grant_points" value="1" id="gp" checked><label class="form-check-label small" for="gp">منح نقاط الخطة</label></div>
                <button class="btn btn-sm btn-success w-100"><i class="fas fa-gift"></i> منح</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      {{-- المحفظة --}}
      <div class="col-lg-5">
        <div class="card mb-3">
          <div class="card-body">
            <h2 class="h6 mb-3"><i class="fas fa-wallet"></i> المحفظة — {{ number_format($balance) }} نقطة</h2>
            <form method="post" action="{{ route('admin.users.adjust') }}" class="row g-2 align-items-end mb-3">
              @csrf
              <input type="hidden" name="user_id" value="{{ $uid }}">
              <input type="hidden" name="token" value="{{ $adjustToken }}">
              <div class="col-5"><label class="form-label small mb-1">المقدار (±)</label><input type="number" name="amount" class="form-control form-control-sm" placeholder="مثل 100 أو -50" required></div>
              <div class="col-5"><label class="form-label small mb-1">السبب</label><input name="reason" class="form-control form-control-sm" required></div>
              <div class="col-2"><button class="btn btn-sm btn-brand w-100"><i class="fas fa-pen"></i></button></div>
            </form>

            <div class="small text-muted mb-1">آخر الحركات</div>
            <ul class="list-group list-group-flush" style="max-height:320px; overflow:auto;">
              @forelse($ledger as $l)
                <li class="list-group-item d-flex justify-content-between px-0 py-1">
                  <span class="small">
                    <span class="badge {{ $l->points>=0 ? 'bg-success' : 'bg-danger' }}">{{ $l->points>=0 ? '+' : '' }}{{ $l->points }}</span>
                    {{ ['earn'=>'كسب','spend'=>'صرف','refund'=>'استرجاع','adjust'=>'تعديل','free_unit'=>'مجاني'][$l->kind] ?? $l->kind }}
                    <span class="text-muted">{{ $l->description }}</span>
                  </span>
                  <span class="text-muted small">{{ $l->balance_after }}</span>
                </li>
              @empty
                <li class="list-group-item text-muted px-0">لا حركات.</li>
              @endforelse
            </ul>
          </div>
        </div>
      </div>
    </div>
  @endif
@endsection
