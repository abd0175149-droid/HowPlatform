@extends('layouts.user')
@section('title','لوحتي | Plans')

@section('content')
  {{-- إحصاءات سريعة --}}
  <div class="stat-grid">
    <div class="stat-tile">
      <div>
        <div class="st-label">رصيد النقاط</div>
        <div class="st-value">{{ number_format($balance) }}</div>
        <div class="st-sub">نقطة</div>
      </div>
      <div class="st-ic" style="background:linear-gradient(135deg,#f472b6,#db2777);"><i class="fas fa-coins"></i></div>
    </div>

    <div class="stat-tile">
      <div>
        <div class="st-label">سلسلة الدخول</div>
        <div class="st-value">{{ $streak }}</div>
        <div class="st-sub">من 5 أيام</div>
      </div>
      <div class="st-ic" style="background:linear-gradient(135deg,#fbbf24,#f59e0b);"><i class="fas fa-fire"></i></div>
    </div>

    <div class="stat-tile">
      <div>
        <div class="st-label">خطتي الحالية</div>
        @if($ent['has_plan'])
          <div class="st-value" style="font-size:1.3rem;">{{ $ent['plan']['name'] }}</div>
          <div class="st-sub">
            المستوى {{ $ent['plan']['level'] }}
            @if(!empty($ent['end_at']))
              · حتى {{ \Illuminate\Support\Carbon::parse($ent['end_at'])->translatedFormat('Y-m-d') }}
            @endif
          </div>
        @else
          <div class="st-value" style="font-size:1.3rem;">لا توجد خطة</div>
          <div class="st-sub"><a href="{{ route('user.plans') }}" style="color:var(--primary-600);font-weight:700;">ترقية الحساب &larr;</a></div>
        @endif
      </div>
      <div class="st-ic" style="background:linear-gradient(135deg,var(--primary-500),var(--primary-700));"><i class="fas fa-crown"></i></div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    {{-- استبدال بطاقة --}}
    <div class="col-12 col-lg-6">
      <div class="profile-section-card active h-100">
        <div class="section-card-header">
          <h3 class="section-card-title"><span class="section-card-title-icon"><i class="fas fa-ticket"></i></span><span>استبدال بطاقة</span></h3>
        </div>
        <div class="section-card-body">
          <form method="post" action="{{ route('user.redeem') }}" class="d-flex gap-2">@csrf
            <input type="text" name="code" class="profile-form-input" placeholder="أدخل كود البطاقة" maxlength="60" required>
            <button class="profile-btn profile-btn-primary"><i class="fas fa-check"></i> استبدال</button>
          </form>
          <div class="text-muted small mt-2">بطاقة الاشتراك تُفعّل خطّة، وبطاقة النقاط تضيف رصيدًا إلى محفظتك.</div>
        </div>
      </div>
    </div>

    {{-- الإحالة --}}
    <div class="col-12 col-lg-6">
      <div class="profile-section-card active h-100">
        <div class="section-card-header">
          <h3 class="section-card-title"><span class="section-card-title-icon"><i class="fas fa-user-plus"></i></span><span>ادعُ صديقًا</span></h3>
        </div>
        <div class="section-card-body">
          <label class="profile-form-label">كود الإحالة الخاص بك</label>
          <input type="text" class="profile-form-input fw-bold mb-3" value="{{ $code }}" readonly onclick="this.select()">
          <label class="profile-form-label">رابط الدعوة</label>
          <input type="text" class="profile-form-input mb-2" value="{{ $link }}" readonly onclick="this.select()">
          <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="profile-btn profile-btn-secondary profile-btn-sm" onclick="accCopy('{{ $link }}', this)"><i class="fas fa-copy"></i> نسخ الرابط</button>
            <a class="profile-btn profile-btn-primary profile-btn-sm" style="background:linear-gradient(135deg,#25d366,#128c7e);border-color:#128c7e;" href="https://wa.me/?text={{ rawurlencode('انضم إلى منصّة HOW عبر رابط دعوتي: ' . $link) }}" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> واتساب</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- آخر الحركات --}}
  <div class="profile-section-card active mt-3">
    <div class="section-card-header">
      <h3 class="section-card-title"><span class="section-card-title-icon"><i class="fas fa-clock-rotate-left"></i></span><span>آخر حركات المحفظة</span></h3>
      <div class="section-card-actions"><a href="{{ route('user.wallet') }}" class="profile-btn profile-btn-ghost profile-btn-sm">عرض الكل</a></div>
    </div>
    <div class="section-card-body">
      <div class="table-responsive">
        <table class="acc-table">
          <thead><tr><th>الوصف</th><th>النوع</th><th>النقاط</th><th>الرصيد بعدها</th><th>التاريخ</th></tr></thead>
          <tbody>
            @forelse($recent as $row)
              <tr>
                <td>{{ $row->description ?: '—' }}</td>
                <td><span class="badge-soft" style="padding:.15rem .6rem;border-radius:999px;font-size:.75rem;font-weight:700;">{{ $row->kind }}</span></td>
                <td class="{{ $row->points >= 0 ? 'text-success' : 'text-danger' }} fw-bold">{{ $row->points >= 0 ? '+' : '' }}{{ number_format($row->points) }}</td>
                <td>{{ number_format($row->balance_after) }}</td>
                <td class="text-muted small">{{ $row->created_at ? \Illuminate\Support\Carbon::parse($row->created_at)->format('Y-m-d H:i') : '—' }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center text-muted py-3">لا توجد حركات بعد.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection
