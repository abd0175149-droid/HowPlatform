@extends('layouts.user')
@section('title','الخطط وأسعار النقاط | Plans')

@section('content')
  {{-- الخطط --}}
  <div class="profile-section-card active">
    <div class="section-card-header">
      <h3 class="section-card-title"><span class="section-card-title-icon"><i class="fas fa-crown"></i></span><span>خطط الاشتراك</span></h3>
    </div>
    <div class="section-card-body">
      @if($plans->isEmpty())
        <div class="text-center text-muted py-4">لا توجد خطط متاحة حاليًا.</div>
      @else
        <div class="plan-grid">
          @foreach($plans as $p)
            @php $isCurrent = $currentPlanId && (int)$p->id === (int)$currentPlanId; @endphp
            <div class="plan-card {{ $isCurrent ? 'is-current' : '' }}">
              @if($isCurrent)<span class="plan-current-badge"><i class="fas fa-check"></i> خطتك الحالية</span>@endif
              <span class="plan-level">المستوى {{ $p->level }}</span>
              <div class="plan-name">{{ $p->name }}</div>
              <div>
                <span class="plan-price">
                  {{ rtrim(rtrim(number_format((float)$p->price, 2), '0'), '.') }}
                  <small>{{ $p->currency ?? 'JOD' }}</small>
                </span>
              </div>
              <div class="plan-duration"><i class="fas fa-calendar-days"></i> لمدة {{ $p->duration_days }} يوم</div>
              @if($p->grant_points > 0)
                <div class="plan-grant"><i class="fas fa-coins"></i> +{{ number_format($p->grant_points) }} نقطة عند الاشتراك</div>
              @endif

              @php $feats = $p->features->filter(fn($f)=> trim((string)($f->label ?: $f->feature_key)) !== ''); @endphp
              @if($feats->count())
                <ul class="plan-features">
                  @foreach($feats as $f)
                    <li>
                      <i class="fas fa-circle-check"></i>
                      <span>
                        {{ $f->label ?: $f->feature_key }}
                        @if(!is_null($f->value) && $f->value !== '')
                          <strong>: {{ $f->value }}</strong>
                        @endif
                        @if(!empty($platformLabels[$f->platform]) && $f->platform !== 'all')
                          <span class="text-muted" style="font-size:.75rem;">({{ $platformLabels[$f->platform] }})</span>
                        @endif
                      </span>
                    </li>
                  @endforeach
                </ul>
              @endif

              @if($isCurrent)
                <a href="{{ route('user.subscriptions') }}" class="profile-btn profile-btn-secondary mt-auto">تفاصيل اشتراكي</a>
              @else
                <a href="#redeem-box" class="profile-btn profile-btn-primary mt-auto"><i class="fas fa-ticket"></i> فعّل عبر بطاقة</a>
              @endif
            </div>
          @endforeach
        </div>
      @endif
    </div>
  </div>

  {{-- حزم النقاط --}}
  <div class="profile-section-card active">
    <div class="section-card-header">
      <h3 class="section-card-title"><span class="section-card-title-icon"><i class="fas fa-coins"></i></span><span>أسعار حزم النقاط</span></h3>
    </div>
    <div class="section-card-body">
      @if($packages->isEmpty())
        <div class="text-center text-muted py-4">لا توجد حزم نقاط متاحة حاليًا.</div>
      @else
        <div class="pkg-grid">
          @foreach($packages as $pkg)
            <div class="pkg-card">
              <div class="pkg-points">{{ number_format($pkg->points) }} <small>نقطة</small></div>
              <div class="pkg-price">{{ rtrim(rtrim(number_format((float)$pkg->price_amount, 2), '0'), '.') }} {{ $pkg->price_currency }}</div>
              @if($pkg->label)<div class="pkg-label">{{ $pkg->label }}</div>@endif
            </div>
          @endforeach
        </div>
      @endif
    </div>
  </div>

  {{-- طريقة الشراء + استبدال كود --}}
  <div class="profile-section-card active" id="redeem-box">
    <div class="section-card-header">
      <h3 class="section-card-title"><span class="section-card-title-icon"><i class="fas fa-ticket"></i></span><span>الترقية واستبدال كود</span></h3>
    </div>
    <div class="section-card-body">
      <div class="profile-alert profile-alert-info mb-3" style="animation:none;">
        <i class="fa fa-circle-info profile-alert-icon"></i>
        <div>لترقية حسابك أو إضافة نقاط، احصل على بطاقة/كود من نقاط البيع المعتمدة، ثم أدخِل الكود هنا لتفعيله فورًا.</div>
      </div>
      <form method="post" action="{{ route('user.redeem') }}" class="row g-2">@csrf
        <div class="col-12 col-md-8">
          <input type="text" name="code" class="profile-form-input" placeholder="أدخل كود البطاقة" maxlength="60" required>
        </div>
        <div class="col-12 col-md-4">
          <button class="profile-btn profile-btn-primary w-100"><i class="fas fa-check"></i> تفعيل الكود</button>
        </div>
      </form>
    </div>
  </div>
@endsection
