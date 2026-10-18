@extends('layouts.user')
@section('title','ملفي | Plans')

@section('content')
  {{-- النقاط والاشتراك --}}
  <div class="profile-section-card active">
    <div class="section-card-header">
      <h3 class="section-card-title"><span class="section-card-title-icon"><i class="fas fa-coins"></i></span><span>النقاط والاشتراك</span></h3>
      <div class="section-card-actions">
        <a href="{{ route('user.plans') }}" class="profile-btn profile-btn-primary profile-btn-sm"><i class="fas fa-arrow-up-right-dots"></i> ترقية الحساب</a>
      </div>
    </div>
    <div class="section-card-body">
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <div class="points-hero">
            <div class="ph-ic"><i class="fas fa-coins"></i></div>
            <div>
              <div class="ph-value">{{ number_format($balance) }}</div>
              <div class="ph-label">رصيد النقاط</div>
            </div>
            <a href="{{ route('user.wallet') }}" class="profile-btn profile-btn-ghost profile-btn-sm ms-auto">المحفظة</a>
          </div>
        </div>
        <div class="col-12 col-md-6">
          <div class="points-hero" style="background:linear-gradient(135deg,var(--primary-50),#fff);border-color:var(--primary-200);">
            <div class="ph-ic" style="background:linear-gradient(135deg,var(--primary-500),var(--primary-700));"><i class="fas fa-crown"></i></div>
            <div>
              @if($ent['has_plan'])
                <div class="ph-value" style="font-size:1.35rem;">{{ $ent['plan']['name'] }}</div>
                <div class="ph-label">
                  المستوى {{ $ent['plan']['level'] }}
                  @if($currentSub && $currentSub->end_at)
                    · حتى {{ $currentSub->end_at->translatedFormat('Y-m-d') }}
                  @endif
                </div>
              @else
                <div class="ph-value" style="font-size:1.35rem;">لا يوجد اشتراك</div>
                <div class="ph-label">فعّل خطة للاستفادة من المزايا</div>
              @endif
            </div>
            <a href="{{ route('user.subscriptions') }}" class="profile-btn profile-btn-ghost profile-btn-sm ms-auto">التفاصيل</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- بيانات الحساب --}}
  <div class="profile-section-card active">
    <div class="section-card-header">
      <h3 class="section-card-title"><span class="section-card-title-icon"><i class="fas fa-id-badge"></i></span><span>بيانات الحساب</span></h3>
      <div class="section-card-actions">
        <a href="https://auth.howplatform.net/profile" target="_blank" rel="noopener" class="profile-btn profile-btn-secondary profile-btn-sm"><i class="fas fa-pen"></i> تعديل</a>
      </div>
    </div>
    <div class="section-card-body">
      <div class="profile-form-grid">
        <div class="profile-form-group">
          <label class="profile-form-label">الاسم</label>
          <input type="text" class="profile-form-input" value="{{ $name ?: '—' }}" readonly>
        </div>
        <div class="profile-form-group">
          <label class="profile-form-label">البريد الإلكتروني</label>
          <input type="text" class="profile-form-input" value="{{ $email ?: '—' }}" readonly style="direction:ltr;text-align:right;">
        </div>
      </div>
    </div>
  </div>

  {{-- الإحالة --}}
  <div class="profile-section-card active">
    <div class="section-card-header">
      <h3 class="section-card-title"><span class="section-card-title-icon"><i class="fas fa-user-plus"></i></span><span>الإحالة</span></h3>
    </div>
    <div class="section-card-body">
      @php $rs = $refStats ?? ['total'=>0,'rewarded'=>0,'pending'=>0,'earned'=>0]; @endphp
      <div class="profile-form-grid">
        <div class="profile-form-group">
          <label class="profile-form-label">كود الإحالة الخاص بك</label>
          <input type="text" class="profile-form-input fw-bold" value="{{ $code }}" readonly onclick="this.select()">
        </div>
        <div class="profile-form-group">
          <label class="profile-form-label">رابط الدعوة</label>
          <input type="text" id="refLink" class="profile-form-input" value="{{ $link }}" readonly onclick="this.select()">
        </div>
      </div>
      <div class="d-flex gap-2 flex-wrap mt-2">
        <button type="button" class="profile-btn profile-btn-secondary profile-btn-sm" onclick="accCopy('{{ $link }}', this)"><i class="fas fa-copy"></i> نسخ الرابط</button>
        <a class="profile-btn profile-btn-primary profile-btn-sm" style="background:linear-gradient(135deg,#25d366,#128c7e);border-color:#128c7e;" href="https://wa.me/?text={{ rawurlencode('انضم إلى منصّة HOW عبر رابط دعوتي: ' . $link) }}" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> مشاركة عبر واتساب</a>
      </div>
      <div class="row text-center g-2 mt-2">
        <div class="col-4"><div class="text-muted small">دعوات</div><div class="fw-bold fs-5">{{ number_format($rs['total']) }}</div></div>
        <div class="col-4"><div class="text-muted small">مكتملة</div><div class="fw-bold fs-5">{{ number_format($rs['rewarded']) }}</div></div>
        <div class="col-4"><div class="text-muted small">نقاط مكتسبة</div><div class="fw-bold fs-5" style="color:var(--accent-600)">{{ number_format($rs['earned']) }}</div></div>
      </div>
      <div class="text-muted small mt-2">تحصل على 100 نقطة عند إكمال صديقٍ دعوتَه عبر رابطك.</div>
    </div>
  </div>

  {{-- استبدال بطاقة --}}
  <div class="profile-section-card active">
    <div class="section-card-header">
      <h3 class="section-card-title"><span class="section-card-title-icon"><i class="fas fa-ticket"></i></span><span>استبدال بطاقة</span></h3>
    </div>
    <div class="section-card-body">
      <form method="post" action="{{ route('user.redeem') }}" class="row g-2">@csrf
        <div class="col-12 col-md-8">
          <input type="text" name="code" class="profile-form-input" placeholder="أدخل كود البطاقة" maxlength="60" required>
        </div>
        <div class="col-12 col-md-4">
          <button class="profile-btn profile-btn-primary w-100"><i class="fas fa-check"></i> استبدال</button>
        </div>
      </form>
      <div class="text-muted small mt-2">بطاقة الاشتراك تُفعّل خطّة، وبطاقة النقاط تضيف رصيدًا إلى محفظتك.</div>
    </div>
  </div>
@endsection
