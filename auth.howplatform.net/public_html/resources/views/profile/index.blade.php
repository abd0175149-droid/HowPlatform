{{-- resources/views/profile/index.blade.php --}}
@extends('layouts.app')
@section('title','الملف الشخصي')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
<link rel="stylesheet" href="{{ asset('css/profile.css') }}">
<style>
  /* أقسام النقاط/المحفظة/الاشتراكات/الخطط داخل الواجهة الموحّدة */
  .pl-hero{display:flex;align-items:center;gap:1rem;padding:1.15rem 1.25rem;border-radius:16px;}
  .pl-hero .ic{width:52px;height:52px;border-radius:14px;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;}
  .pl-hero .v{font-size:2rem;font-weight:800;color:var(--text-strong);line-height:1;}
  .pl-hero .l{color:var(--text-muted);font-size:.82rem;font-weight:700;}
  .pl-hero.points{background:linear-gradient(135deg,var(--accent-50),#fff);border:1.5px solid var(--accent-200);}
  .pl-hero.points .ic{background:linear-gradient(135deg,var(--accent-400),var(--accent-600));}
  .pl-hero.plan{background:linear-gradient(135deg,var(--primary-50),#fff);border:1.5px solid var(--primary-200);}
  .pl-hero.plan .ic{background:linear-gradient(135deg,var(--primary-500),var(--primary-700));}

  /* ودجت سلسلة الدخول: 5 أيام → +50 نقطة */
  .pl-streak{padding:1.15rem 1.25rem;border-radius:16px;background:linear-gradient(135deg,var(--primary-50),#fff);border:1.5px solid var(--primary-200);}
  .pl-streak .head{display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;margin-bottom:.85rem;}
  .pl-streak .ttl{display:flex;align-items:center;gap:.6rem;font-weight:800;color:var(--text-strong);}
  .pl-streak .ttl .ic{width:38px;height:38px;border-radius:11px;background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.05rem;}
  .pl-streak .prize{display:inline-flex;align-items:center;gap:.4rem;background:var(--accent-50);border:1.5px solid var(--accent-200);color:var(--accent-700);font-weight:800;font-size:.8rem;padding:.3rem .8rem;border-radius:999px;white-space:nowrap;}
  .pl-days{display:flex;align-items:center;gap:.35rem;}
  .pl-day{flex:1;display:flex;flex-direction:column;align-items:center;gap:.35rem;min-width:0;}
  .pl-day .dot{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.85rem;background:var(--neutral-100);border:2px solid var(--neutral-200);color:var(--text-muted);transition:all .2s;}
  .pl-day.done .dot{background:linear-gradient(135deg,#fbbf24,#f59e0b);border-color:#f59e0b;color:#fff;box-shadow:0 4px 10px rgba(245,158,11,.35);}
  .pl-day.today .dot{border-color:#f59e0b;border-style:dashed;color:#b45309;}
  .pl-day.prize-day .dot{width:44px;height:44px;font-size:1rem;}
  .pl-day.prize-day.done .dot{background:linear-gradient(135deg,var(--accent-400),var(--accent-600));}
  .pl-day .lbl{font-size:.68rem;font-weight:700;color:var(--text-muted);white-space:nowrap;}
  .pl-connector{height:3px;flex:0 0 12px;border-radius:2px;background:var(--neutral-200);margin-bottom:1.2rem;}
  .pl-connector.done{background:#f59e0b;}
  .pl-streak .note{margin-top:.85rem;color:var(--text-muted);font-size:.82rem;font-weight:600;line-height:1.7;}
  .pl-streak .note strong{color:var(--accent-700);}

  /* سقف مكافآت الدعوة */
  .pl-ref-cap{margin-top:.9rem;padding:.85rem 1rem;border-radius:12px;background:var(--neutral-50);border:1.5px solid var(--neutral-200);}
  .pl-ref-cap .row1{display:flex;justify-content:space-between;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:.5rem;}
  .pl-ref-cap .t{font-weight:800;color:var(--text-strong);font-size:.85rem;}
  .pl-ref-cap .c{font-weight:800;color:var(--accent-700);font-size:.85rem;}
  .pl-ref-bar{height:8px;border-radius:999px;background:var(--neutral-200);overflow:hidden;}
  .pl-ref-bar > span{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,var(--accent-400),var(--accent-600));transition:width .3s;}

  .pl-table{width:100%;border-collapse:collapse;}
  .pl-table thead th{color:var(--text-muted);font-weight:700;font-size:.8rem;text-align:start;padding:.55rem .5rem;border-bottom:1.5px solid var(--neutral-200);}
  .pl-table tbody td{padding:.6rem .5rem;border-bottom:1px solid var(--neutral-100);font-size:.88rem;}
  .pl-pill{display:inline-flex;align-items:center;gap:.3rem;padding:.15rem .6rem;border-radius:999px;font-size:.72rem;font-weight:800;}
  .pl-pill.ok{background:var(--success-100);color:var(--success-600);}
  .pl-pill.muted{background:var(--neutral-100);color:var(--text-muted);}
  .pl-pill.warn{background:var(--warning-100);color:var(--warning-600);}

  .pl-sub-current{background:linear-gradient(135deg,var(--primary-500),var(--primary-800));color:#fff;border-radius:16px;padding:1.5rem;}
  .pl-sub-current .p{font-size:1.5rem;font-weight:800;}

  .pl-plan-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:1rem;}
  .pl-plan{position:relative;background:#fff;border:1.5px solid var(--neutral-200);border-radius:16px;padding:1.25rem;display:flex;flex-direction:column;gap:.7rem;transition:all .2s ease;}
  .pl-plan:hover{transform:translateY(-3px);box-shadow:var(--shadow-md);border-color:var(--primary-200);}
  .pl-plan.cur{border-color:var(--accent-500);box-shadow:0 8px 22px rgba(253,158,2,.16);}
  .pl-plan .lvl{align-self:flex-start;font-size:.7rem;font-weight:800;padding:.2rem .65rem;border-radius:999px;background:var(--primary-50);color:var(--primary-700);}
  .pl-plan .nm{font-size:1.2rem;font-weight:800;color:var(--text-strong);}
  .pl-plan .pr{font-size:1.7rem;font-weight:800;color:var(--primary-700);line-height:1;}
  .pl-plan .pr small{font-size:.8rem;font-weight:600;color:var(--text-muted);}
  .pl-plan .dur{color:var(--text-muted);font-size:.8rem;}
  .pl-plan ul{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.45rem;}
  .pl-plan li{display:flex;gap:.5rem;align-items:flex-start;font-size:.85rem;color:var(--text);font-weight:600;}
  .pl-plan li i{color:var(--success-500);margin-top:.2rem;}
  .pl-plan .cur-badge{position:absolute;top:-10px;inset-inline-start:1.1rem;background:var(--accent-500);color:#fff;font-size:.7rem;font-weight:800;padding:.15rem .7rem;border-radius:999px;}
  .pl-grant{font-size:.8rem;color:var(--accent-600);font-weight:700;}

  .pl-pkg-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.8rem;}
  .pl-pkg{background:linear-gradient(135deg,var(--primary-800),var(--primary-900));color:#fff;border-radius:14px;padding:1.1rem;text-align:center;}
  .pl-pkg .pts{font-size:1.5rem;font-weight:800;}
  .pl-pkg .pts small{font-size:.75rem;opacity:.85;}
  .pl-pkg .prc{margin-top:.3rem;font-weight:700;color:var(--accent-300);}

  /* شبكة المنصّات */
  .pl-apps{margin-inline-start:auto;}
  .pl-apps .app-launcher-menu{min-width:280px;padding:.75rem;border:none;border-radius:16px;box-shadow:var(--shadow-lg);}
  .pl-apps .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.4rem;}
  .pl-apps .tile{display:flex;flex-direction:column;align-items:center;gap:.4rem;padding:.8rem .4rem;border-radius:12px;color:var(--text-strong);text-align:center;border:1.5px solid transparent;transition:all .18s ease;}
  .pl-apps .tile:hover{background:var(--primary-50);border-color:var(--primary-100);color:var(--primary-700);transform:translateY(-2px);}
  .pl-apps .tile .ic{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#fff;background:linear-gradient(135deg,var(--primary-500),var(--primary-700));}
  .pl-apps .tile .lbl{font-size:.72rem;font-weight:700;line-height:1.2;}
</style>
@endpush

@section('content')

<div class="profile-wrapper">
  <div class="profile-main-container">
    
    {{-- Profile Banner Header --}}
    <div class="profile-banner">
      <div class="profile-banner-content">
        <div class="profile-avatar-container">
          <img src="{{ $avatarUrl }}" alt="Avatar" class="profile-avatar-main" id="profileAvatarMain">
          <div class="avatar-edit-badge" id="avatarEditBadge">
            <i class="fa fa-camera"></i>
          </div>
        </div>
        
        <div class="profile-info-main">
          <h1 class="profile-name">{{ $user->name }}</h1>
          <div class="profile-email">{{ $user->email }}</div>
          <div class="profile-status-badges">
            @if($isVerified)
              <span class="status-badge">
                <i class="fa fa-check-circle"></i>
                <span>البريد مُفعّل</span>
              </span>
            @else
              <span class="status-badge">
                <i class="fa fa-exclamation-triangle"></i>
                <span>البريد غير مُفعّل</span>
              </span>
              <form action="{{ route('verification.send') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="status-badge" style="cursor: pointer; border: 2px solid rgba(255,255,255,.4);">
                  <i class="fa fa-paper-plane"></i>
                  <span>إعادة إرسال</span>
                </button>
              </form>
            @endif
          </div>
        </div>
      </div>
    </div>

    {{-- Alerts --}}
    @if (session('ok'))
      <div class="profile-alert profile-alert-success auto-dismiss">
        <i class="fa fa-check-circle profile-alert-icon"></i>
        <div>{{ session('ok') }}</div>
      </div>
    @endif

    @if (session('err'))
      <div class="profile-alert profile-alert-danger auto-dismiss">
        <i class="fa fa-exclamation-circle profile-alert-icon"></i>
        <div>{{ session('err') }}</div>
      </div>
    @endif

    @if (session('status') === 'verification-link-sent')
      <div class="profile-alert profile-alert-success auto-dismiss">
        <i class="fa fa-check-circle profile-alert-icon"></i>
        <div>تم إرسال رابط التفعيل إلى بريدك الإلكتروني.</div>
      </div>
    @endif

    @if (session('ok_diploma'))
      <div class="profile-alert profile-alert-success auto-dismiss">
        <i class="fa fa-check-circle profile-alert-icon"></i>
        <div>{{ session('ok_diploma') }}</div>
      </div>
    @endif

    @if (session('err_diploma'))
      <div class="profile-alert profile-alert-danger auto-dismiss">
        <i class="fa fa-exclamation-circle profile-alert-icon"></i>
        <div>{{ session('err_diploma') }}</div>
      </div>
    @endif

    @if ($errors->diploma->any())
      <div class="profile-alert profile-alert-danger auto-dismiss">
        <i class="fa fa-exclamation-circle profile-alert-icon"></i>
        <div>
          <strong>تحقق من الأخطاء التالية:</strong>
          <ul style="margin: .75rem 0 0 0; padding-right: 1.5rem;">
            @foreach ($errors->diploma->all() as $msg)
              <li>{{ $msg }}</li>
            @endforeach
          </ul>
        </div>
      </div>
    @endif

    {{-- Auto Redirect Banner --}}
    @if(!empty($redirectCountdown) && !empty($safeRedirect))
      <div class="profile-alert profile-alert-info" id="auto-redirect-banner">
        <i class="fa fa-info-circle profile-alert-icon"></i>
        <div style="flex: 1;">
          <div>تم اكتمال الملف. سيتم تحويلك لمنصة الدبلوم خلال <strong id="redirectSec">{{ $redirectCountdown }}</strong> ثانية.</div>
          <div style="margin-top: 1rem; display: flex; gap: .75rem; flex-wrap: wrap;">
            <a href="{{ $safeRedirect }}" class="profile-btn profile-btn-primary profile-btn-sm">
              <i class="fa fa-arrow-right"></i> الذهاب الآن
            </a>
            <button type="button" class="profile-btn profile-btn-secondary profile-btn-sm" id="cancelRedirect">
              إلغاء التحويل
            </button>
          </div>
        </div>
      </div>
    @endif

    @php $P = is_array($plans ?? null) ? $plans : ['available'=>false]; @endphp

    {{-- Sections Navigation Chips --}}
    <div class="profile-sections-nav">
      <a href="#" class="section-chip active" data-section="auth">
        <span class="chip-icon"><i class="fa fa-user-circle"></i></span>
        <span class="chip-text">معلومات الحساب</span>
      </a>
      @if(!empty($P['available']))
      <a href="#" class="section-chip" data-section="points">
        <span class="chip-icon"><i class="fa fa-coins"></i></span>
        <span class="chip-text">النقاط والمحفظة</span>
      </a>
      <a href="#" class="section-chip" data-section="plans">
        <span class="chip-icon"><i class="fa fa-crown"></i></span>
        <span class="chip-text">الاشتراكات والخطط</span>
      </a>
      @endif
      <a href="#" class="section-chip" data-section="diploma">
        <span class="chip-icon"><i class="fa fa-graduation-cap"></i></span>
        <span class="chip-text">بيانات الدبلوم</span>
      </a>
      <a href="#" class="section-chip" data-section="security">
        <span class="chip-icon"><i class="fa fa-lock"></i></span>
        <span class="chip-text">الأمان</span>
      </a>
      <a href="#" class="section-chip" data-section="danger">
        <span class="chip-icon"><i class="fa fa-trash-alt"></i></span>
        <span class="chip-text">حذف الحساب</span>
      </a>
    </div>

    {{-- Content Area --}}
    <main class="profile-content-area">
        
        {{-- Auth Section --}}
        <section class="profile-section-card active" id="section-auth">
          <div class="section-card-header">
            <h3 class="section-card-title">
              <span class="section-card-title-icon">
                <i class="fa fa-user-circle"></i>
              </span>
              <span>معلومات الحساب</span>
            </h3>
            <div class="section-card-actions">
              <button class="profile-btn profile-btn-ghost profile-btn-sm js-edit" data-form="form-auth">
                <i class="fa fa-edit"></i> تعديل
              </button>
              <button class="profile-btn profile-btn-primary profile-btn-sm d-none js-save" data-form="form-auth">
                <i class="fa fa-save"></i> حفظ
              </button>
              <button class="profile-btn profile-btn-secondary profile-btn-sm d-none js-cancel" data-form="form-auth">
                <i class="fa fa-times"></i> إلغاء
              </button>
            </div>
          </div>
          <div class="section-card-body">
            <form id="form-auth" class="locked" method="POST" action="{{ route('profile.update') }}">
              @csrf @method('PATCH')
              <input type="hidden" name="avatar" id="avatarUrlHidden" value="{{ old('avatar', $user->avatar) }}">
              
              <div class="profile-form-grid">
                <div class="profile-form-group">
                  <label class="profile-form-label">الاسم الكامل</label>
                  <input type="text" class="profile-form-input js-lock" name="name" value="{{ old('name', $user->name) }}" readonly>
                  @error('name')
                    <div class="profile-form-error">{{ $message }}</div>
                  @enderror
                </div>
                
                <div class="profile-form-group">
                  <label class="profile-form-label">البريد الإلكتروني</label>
                  <input type="email" class="profile-form-input js-lock" name="email" value="{{ old('email', $user->email) }}" readonly>
                  @error('email')
                    <div class="profile-form-error">{{ $message }}</div>
                  @enderror
                </div>
              </div>
            </form>
          </div>
        </section>

        {{-- Points & Wallet Section (موحّد من منصّة النقاط) --}}
        @if(!empty($P['available']))
        <section class="profile-section-card" id="section-points">
          <div class="section-card-header">
            <h3 class="section-card-title"><span class="section-card-title-icon"><i class="fa fa-coins"></i></span><span>النقاط والمحفظة</span></h3>
            <div class="section-card-actions">
              <button type="button" class="profile-btn profile-btn-primary profile-btn-sm" data-section="plans"><i class="fa fa-arrow-up-right-dots"></i> ترقية الحساب</button>
            </div>
          </div>
          <div class="section-card-body">
            <div class="profile-form-grid">
              <div class="pl-hero points">
                <div class="ic"><i class="fa fa-coins"></i></div>
                <div><div class="v">{{ number_format($P['points']) }}</div><div class="l">رصيد النقاط</div></div>
              </div>

              {{-- سلسلة الدخول: 5 أيام متتالية → +50 نقطة --}}
              @php $streak = max(0, min(5, (int) $P['streak'])); @endphp
              <div class="pl-streak">
                <div class="head">
                  <div class="ttl"><span class="ic"><i class="fa fa-fire"></i></span> سلسلة الدخول اليومي</div>
                  <span class="prize"><i class="fa fa-gift"></i> أكمل 5 أيام = +50 نقطة</span>
                </div>
                <div class="pl-days">
                  @for($d = 1; $d <= 5; $d++)
                    @if($d > 1)<span class="pl-connector {{ $streak >= $d ? 'done' : '' }}"></span>@endif
                    <div class="pl-day {{ $streak >= $d ? 'done' : '' }} {{ $streak + 1 === $d ? 'today' : '' }} {{ $d === 5 ? 'prize-day' : '' }}">
                      <span class="dot">@if($d === 5)<i class="fa fa-gift"></i>@elseif($streak >= $d)<i class="fa fa-check"></i>@else{{ $d }}@endif</span>
                      <span class="lbl">{{ $d === 5 ? '+50 نقطة' : 'يوم ' . $d }}</span>
                    </div>
                  @endfor
                </div>
                <div class="note">
                  سجّل دخولك <strong>5 أيام متتالية</strong> لتحصل على <strong>50 نقطة</strong> تلقائيًا، ثم تبدأ سلسلة جديدة.
                  @if($streak > 0) أنت الآن في اليوم <strong>{{ $streak }}</strong> — واصل! @endif
                  الانقطاع يومًا واحدًا يُعيد السلسلة من البداية.
                </div>
              </div>
            </div>

            @if(!empty($P['referral']['code']))
            @php
              $refRewarded = (int) ($P['referral']['rewarded'] ?? 0);
              $refMax      = max(1, (int) ($P['referral']['max'] ?? 5));
              $refPct      = min(100, (int) round($refRewarded * 100 / $refMax));
            @endphp
            <div style="margin-top:1.5rem;">
              <h4 style="font-size:1rem;font-weight:800;color:var(--text-strong);margin-bottom:.75rem;"><i class="fa fa-user-plus" style="color:var(--primary-500);"></i> ادعُ صديقًا</h4>
              <div class="profile-form-grid">
                <div class="profile-form-group">
                  <label class="profile-form-label">كود الإحالة</label>
                  <input type="text" class="profile-form-input" value="{{ $P['referral']['code'] }}" readonly onclick="this.select()">
                </div>
                <div class="profile-form-group">
                  <label class="profile-form-label">رابط الدعوة</label>
                  <input type="text" class="profile-form-input" value="{{ $P['referral']['link'] }}" readonly onclick="this.select()">
                </div>
              </div>
              <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.75rem;">
                <button type="button" class="profile-btn profile-btn-secondary profile-btn-sm" onclick="plCopy('{{ $P['referral']['link'] }}', this)"><i class="fa fa-copy"></i> نسخ الرابط</button>
                <a class="profile-btn profile-btn-primary profile-btn-sm" style="background:linear-gradient(135deg,#25d366,#128c7e);border-color:#128c7e;" href="https://wa.me/?text={{ rawurlencode('انضم إلى منصّة HOW عبر رابط دعوتي: '.$P['referral']['link']) }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> مشاركة عبر واتساب</a>
              </div>

              {{-- سقف مكافآت الدعوة: 100 نقطة × 5 أصدقاء كحد أقصى --}}
              <div class="pl-ref-cap">
                <div class="row1">
                  <span class="t"><i class="fa fa-coins" style="color:var(--accent-500);"></i> مكافآت الدعوة المُحصَّلة</span>
                  <span class="c">{{ $refRewarded }} من {{ $refMax }}</span>
                </div>
                <div class="pl-ref-bar"><span style="width:{{ $refPct }}%;"></span></div>
                <div style="margin-top:.5rem;color:var(--text-muted);font-size:.8rem;font-weight:600;line-height:1.7;">
                  تحصل على <strong style="color:var(--accent-700);">100 نقطة</strong> عن كل صديق يسجّل عبر رابطك ويُكمل ملفّه —
                  بحدّ أقصى <strong style="color:var(--accent-700);">{{ $refMax }} أصدقاء</strong>.
                  @if($refRewarded >= $refMax) وصلتَ للحدّ الأقصى، شكرًا لنشرك المنصّة! 🎉 @endif
                </div>
              </div>

              <div style="margin-top:.75rem;display:flex;gap:1.5rem;flex-wrap:wrap;color:var(--text-muted);font-size:.85rem;font-weight:700;">
                <span>دعوات: <strong style="color:var(--text-strong);">{{ number_format($P['referral']['total']) }}</strong></span>
                <span>مكتملة: <strong style="color:var(--text-strong);">{{ number_format($P['referral']['completed']) }}</strong></span>
                <span>قيد الانتظار: <strong style="color:var(--text-strong);">{{ number_format($P['referral']['pending']) }}</strong></span>
              </div>
            </div>
            @endif

            <div style="margin-top:1.5rem;">
              <h4 style="font-size:1rem;font-weight:800;color:var(--text-strong);margin-bottom:.75rem;"><i class="fa fa-ticket" style="color:var(--accent-500);"></i> استبدال بطاقة/كود</h4>
              <form method="post" action="{{ route('profile.redeem') }}" style="display:flex;gap:.6rem;flex-wrap:wrap;">
                @csrf
                <input type="text" name="code" class="profile-form-input" style="flex:1;min-width:220px;" placeholder="أدخل كود البطاقة" maxlength="60" required>
                <button class="profile-btn profile-btn-primary"><i class="fa fa-check"></i> تفعيل</button>
              </form>
              <div style="color:var(--text-muted);font-size:.8rem;margin-top:.5rem;">بطاقة الاشتراك تُفعّل خطّة، وبطاقة النقاط تضيف رصيدًا إلى محفظتك.</div>
            </div>

            <div style="margin-top:1.5rem;">
              <h4 style="font-size:1rem;font-weight:800;color:var(--text-strong);margin-bottom:.5rem;"><i class="fa fa-clock-rotate-left" style="color:var(--text-muted);"></i> آخر حركات المحفظة</h4>
              <div style="overflow-x:auto;">
                <table class="pl-table">
                  <thead><tr><th>الوصف</th><th>النوع</th><th>النقاط</th><th>الرصيد</th><th>التاريخ</th></tr></thead>
                  <tbody>
                    @forelse($P['transactions'] as $t)
                      <tr>
                        <td>{{ $t['description'] ?? '—' }}</td>
                        <td><span class="pl-pill muted">{{ $t['kind'] ?? '' }}</span></td>
                        <td style="font-weight:800;color:{{ (int)($t['points'] ?? 0) >= 0 ? 'var(--success-600)' : 'var(--error-600)' }};">{{ (int)($t['points'] ?? 0) >= 0 ? '+' : '' }}{{ number_format((int)($t['points'] ?? 0)) }}</td>
                        <td>{{ number_format((int)($t['balance_after'] ?? 0)) }}</td>
                        <td style="color:var(--text-muted);font-size:.8rem;">{{ !empty($t['created_at']) ? \Illuminate\Support\Carbon::parse($t['created_at'])->format('Y-m-d H:i') : '—' }}</td>
                      </tr>
                    @empty
                      <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:1rem;">لا توجد حركات بعد.</td></tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </section>

        {{-- Subscriptions & Plans Section --}}
        @php $curPlanId = (!empty($P['has_plan']) && isset($P['plan']['id'])) ? (int)$P['plan']['id'] : null; @endphp
        <section class="profile-section-card" id="section-plans">
          <div class="section-card-header">
            <h3 class="section-card-title"><span class="section-card-title-icon"><i class="fa fa-crown"></i></span><span>الاشتراكات والخطط</span></h3>
          </div>
          <div class="section-card-body">
            @if(!empty($P['current_sub']))
              <div class="pl-sub-current">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">
                  <div>
                    <div style="opacity:.8;font-size:.82rem;font-weight:700;">اشتراكك الحالي</div>
                    <div class="p">{{ $P['current_sub']['name'] ?? '—' }}</div>
                    <div style="opacity:.9;font-weight:600;">المستوى {{ $P['current_sub']['level'] ?? 0 }}</div>
                  </div>
                  @if(!empty($P['current_sub']['end_at']))
                  <div style="text-align:start;">
                    <div style="opacity:.8;font-size:.8rem;">ينتهي في</div>
                    <div style="font-weight:800;font-size:1.1rem;">{{ \Illuminate\Support\Carbon::parse($P['current_sub']['end_at'])->translatedFormat('Y-m-d') }}</div>
                  </div>
                  @endif
                </div>
              </div>
            @else
              <div style="text-align:center;padding:1.25rem;border:1.5px dashed var(--neutral-300);border-radius:14px;color:var(--text-muted);">
                <i class="fa fa-id-card" style="font-size:1.5rem;color:var(--primary-400);"></i>
                <div style="margin-top:.5rem;font-weight:700;">لا يوجد اشتراك فعّال — اختر خطة بالأسفل وفعّلها عبر كود.</div>
              </div>
            @endif

            @if(!empty($P['plans']))
            <h4 style="font-size:1rem;font-weight:800;color:var(--text-strong);margin:1.5rem 0 .75rem;"><i class="fa fa-layer-group" style="color:var(--primary-500);"></i> الخطط المتاحة</h4>
            <div class="pl-plan-grid">
              @foreach($P['plans'] as $pl)
                @php $isCur = $curPlanId && (int)($pl['id'] ?? 0) === $curPlanId; @endphp
                <div class="pl-plan {{ $isCur ? 'cur' : '' }}">
                  @if($isCur)<span class="cur-badge"><i class="fa fa-check"></i> خطتك</span>@endif
                  <span class="lvl">المستوى {{ $pl['level'] ?? 0 }}</span>
                  <div class="nm">{{ $pl['name'] ?? '' }}</div>
                  <div class="pr">{{ rtrim(rtrim(number_format((float)($pl['price'] ?? 0), 2), '0'), '.') }} <small>{{ $pl['currency'] ?? 'JOD' }}</small></div>
                  <div class="dur"><i class="fa fa-calendar-days"></i> لمدة {{ $pl['duration_days'] ?? 0 }} يوم</div>
                  @if(!empty($pl['grant_points']))<div class="pl-grant"><i class="fa fa-coins"></i> +{{ number_format($pl['grant_points']) }} نقطة عند الاشتراك</div>@endif
                  @php $feats = collect($pl['features'] ?? [])->filter(fn($f)=> trim((string)($f['feature_text'] ?? ($f['feature_key'] ?? ''))) !== ''); @endphp
                  @if($feats->count())
                    <ul>
                      @foreach($feats as $f)
                        <li><i class="fa fa-circle-check"></i><span>{{ $f['feature_text'] ?: $f['feature_key'] }}@if(!empty($f['value'])) <strong>: {{ $f['value'] }}</strong>@endif</span></li>
                      @endforeach
                    </ul>
                  @endif
                  @if(!$isCur)<button type="button" class="profile-btn profile-btn-primary profile-btn-sm" data-section="points" style="margin-top:auto;"><i class="fa fa-ticket"></i> فعّل عبر كود</button>@endif
                </div>
              @endforeach
            </div>
            @endif

            @if(!empty($P['packages']))
            <h4 style="font-size:1rem;font-weight:800;color:var(--text-strong);margin:1.5rem 0 .75rem;"><i class="fa fa-coins" style="color:var(--accent-500);"></i> أسعار حزم النقاط</h4>
            <div class="pl-pkg-grid">
              @foreach($P['packages'] as $pkg)
                <div class="pl-pkg">
                  <div class="pts">{{ number_format($pkg['points'] ?? 0) }} <small>نقطة</small></div>
                  <div class="prc">{{ rtrim(rtrim(number_format((float)($pkg['price_amount'] ?? 0), 2), '0'), '.') }} {{ $pkg['price_currency'] ?? 'JOD' }}</div>
                  @if(!empty($pkg['label']))<div style="font-size:.75rem;opacity:.85;margin-top:.25rem;">{{ $pkg['label'] }}</div>@endif
                </div>
              @endforeach
            </div>
            @endif

            @if(!empty($P['subscriptions']))
            <h4 style="font-size:1rem;font-weight:800;color:var(--text-strong);margin:1.5rem 0 .5rem;"><i class="fa fa-clock-rotate-left" style="color:var(--text-muted);"></i> سجلّ الاشتراكات</h4>
            <div style="overflow-x:auto;">
              <table class="pl-table">
                <thead><tr><th>الخطة</th><th>الحالة</th><th>البداية</th><th>الانتهاء</th></tr></thead>
                <tbody>
                  @foreach($P['subscriptions'] as $s)
                    @php
                      $end = !empty($s['end_at']) ? \Illuminate\Support\Carbon::parse($s['end_at']) : null;
                      $activeNow = ($s['status'] ?? '') === 'active' && ($end === null || $end->isFuture());
                      $label = ['active'=>'نشط','scheduled'=>'مجدول','canceled'=>'ملغى','cancelled'=>'ملغى','expired'=>'منتهٍ'][$s['status'] ?? ''] ?? ($s['status'] ?? '—');
                      $cls = $activeNow ? 'ok' : ((($s['status'] ?? '')==='scheduled') ? 'warn' : 'muted');
                      if(($s['status'] ?? '')==='active' && !$activeNow){ $label='منتهٍ'; $cls='muted'; }
                    @endphp
                    <tr>
                      <td style="font-weight:700;">{{ $s['name'] ?? '—' }}</td>
                      <td><span class="pl-pill {{ $cls }}">{{ $label }}</span></td>
                      <td style="color:var(--text-muted);font-size:.8rem;">{{ !empty($s['start_at']) ? \Illuminate\Support\Carbon::parse($s['start_at'])->format('Y-m-d') : '—' }}</td>
                      <td style="color:var(--text-muted);font-size:.8rem;">{{ $end ? $end->format('Y-m-d') : '—' }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
            @endif
          </div>
        </section>
        @endif

        {{-- Diploma Section --}}
        <section class="profile-section-card" id="section-diploma">
          <div class="section-card-header">
            <h3 class="section-card-title">
              <span class="section-card-title-icon">
                <i class="fa fa-graduation-cap"></i>
              </span>
              <span>بيانات الدبلوم العالي</span>
            </h3>
            <div class="section-card-actions">
              <button class="profile-btn profile-btn-ghost profile-btn-sm js-edit" data-form="form-diploma">
                <i class="fa fa-edit"></i> تعديل
              </button>
              <button class="profile-btn profile-btn-primary profile-btn-sm d-none js-save" data-form="form-diploma">
                <i class="fa fa-save"></i> حفظ
              </button>
              <button class="profile-btn profile-btn-secondary profile-btn-sm d-none js-cancel" data-form="form-diploma">
                <i class="fa fa-times"></i> إلغاء
              </button>
            </div>
          </div>
          <div class="section-card-body">
            <form id="form-diploma" class="locked" method="POST" action="{{ route('profile.diploma.update') }}">
              @csrf @method('PUT')
              
              <div class="profile-form-grid">
                <div class="profile-form-group">
                  <label class="profile-form-label">رقم الهاتف</label>
                  <input type="text" class="profile-form-input js-lock" name="phone_number" value="{{ old('phone_number', $du->phone_number) }}" readonly>
                  @error('phone_number','diploma')
                    <div class="profile-form-error">{{ $message }}</div>
                  @enderror
                </div>

                <div class="profile-form-group">
                  <label class="profile-form-label">الجامعة</label>
                  <select class="profile-form-select js-lock" name="university_id" id="university-select" disabled>
                    <option value="">— اختر —</option>
                    @foreach($universities as $u)
                      <option value="{{ $u->id }}" @selected(old('university_id', $du->university_id)==$u->id)>{{ $u->name }}</option>
                    @endforeach
                  </select>
                  @error('university_id','diploma')
                    <div class="profile-form-error">{{ $message }}</div>
                  @enderror
                </div>

                <div class="profile-form-group">
                  <label class="profile-form-label">التخصص</label>
                  <select class="profile-form-select js-lock" name="specialty_id" id="specialty-select" disabled>
                    <option value="">— اختر —</option>
                    @foreach($specialties as $s)
                      <option value="{{ $s->id }}" @selected(old('specialty_id', $du->specialty_id)==$s->id)>{{ $s->name }}</option>
                    @endforeach
                  </select>
                  @error('specialty_id','diploma')
                    <div class="profile-form-error">{{ $message }}</div>
                  @enderror
                </div>

                <div class="profile-form-group">
                  <label class="profile-form-label">نوع الدبلوم</label>
                  <select class="profile-form-select js-lock" name="diploma_type_id" disabled>
                    <option value="">— اختر —</option>
                    @foreach($diplomaTypes as $t)
                      <option value="{{ $t->id }}" @selected(old('diploma_type_id', $du->diploma_type_id)==$t->id)>{{ $t->name }}</option>
                    @endforeach
                  </select>
                  @error('diploma_type_id','diploma')
                    <div class="profile-form-error">{{ $message }}</div>
                  @enderror
                </div>

                <div class="profile-form-group">
                  <label class="profile-form-label">السنة الدراسية</label>
                  <select class="profile-form-select js-lock" name="academic_year_id" disabled>
                    <option value="">— اختر —</option>
                    @foreach($years as $y)
                      <option value="{{ $y->id }}" @selected(old('academic_year_id', $du->academic_year_id)==$y->id)>{{ $y->label }}</option>
                    @endforeach
                  </select>
                  @error('academic_year_id','diploma')
                    <div class="profile-form-error">{{ $message }}</div>
                  @enderror
                </div>

                <div class="profile-form-group">
                  <label class="profile-form-label">الجنس</label>
                  <select class="profile-form-select js-lock" name="gender" disabled>
                    <option value="">— اختر —</option>
                    <option value="male" @selected(old('gender', $du->gender)=='male')>ذكر</option>
                    <option value="female" @selected(old('gender', $du->gender)=='female')>أنثى</option>
                  </select>
                  @error('gender','diploma')
                    <div class="profile-form-error">{{ $message }}</div>
                  @enderror
                </div>

                <div class="profile-form-group">
                  <label class="profile-form-label">تاريخ الميلاد</label>
                  <input type="date" class="profile-form-input js-lock" name="birth_date" value="{{ old('birth_date', $du->birth_date) }}" readonly>
                  @error('birth_date','diploma')
                    <div class="profile-form-error">{{ $message }}</div>
                  @enderror
                </div>
              </div>
            </form>
          </div>
        </section>

        {{-- Security Section --}}
        <section class="profile-section-card" id="section-security">
          <div class="section-card-header">
            <h3 class="section-card-title">
              <span class="section-card-title-icon">
                <i class="fa fa-lock"></i>
              </span>
              <span>الأمان وكلمة المرور</span>
            </h3>
            <div class="section-card-actions">
              <button class="profile-btn profile-btn-ghost profile-btn-sm js-edit" data-form="form-security">
                <i class="fa fa-edit"></i> تعديل
              </button>
              <button class="profile-btn profile-btn-primary profile-btn-sm d-none js-save" data-form="form-security">
                <i class="fa fa-save"></i> حفظ
              </button>
              <button class="profile-btn profile-btn-secondary profile-btn-sm d-none js-cancel" data-form="form-security">
                <i class="fa fa-times"></i> إلغاء
              </button>
            </div>
          </div>
          <div class="section-card-body">
            <form id="form-security" class="locked" method="POST" action="{{ route('profile.password.update') }}">
              @csrf @method('PUT')
              
              <div class="profile-form-grid">
                <div class="profile-form-group">
                  <label class="profile-form-label">كلمة المرور الحالية</label>
                  <input type="password" class="profile-form-input js-lock" name="current_password" placeholder="••••••••" readonly>
                  @error('current_password')
                    <div class="profile-form-error">{{ $message }}</div>
                  @enderror
                </div>
                
                <div class="profile-form-group">
                  <label class="profile-form-label">كلمة المرور الجديدة</label>
                  <input type="password" class="profile-form-input js-lock" name="password" placeholder="••••••••" readonly>
                  @error('password')
                    <div class="profile-form-error">{{ $message }}</div>
                  @enderror
                </div>
                
                <div class="profile-form-group">
                  <label class="profile-form-label">تأكيد كلمة المرور الجديدة</label>
                  <input type="password" class="profile-form-input js-lock" name="password_confirmation" placeholder="••••••••" readonly>
                </div>
              </div>
            </form>
          </div>
        </section>

        {{-- Danger Zone --}}
        <section class="profile-section-card danger-zone" id="section-danger">
          <div class="section-card-header">
            <h3 class="section-card-title">
              <span class="section-card-title-icon">
                <i class="fa fa-exclamation-triangle"></i>
              </span>
              <span>حذف الحساب</span>
            </h3>
          </div>
          <div class="section-card-body">
            <p style="color: var(--error-600); margin-bottom: 1.5rem; font-weight: 700; line-height: 1.8;">
              <i class="fa fa-exclamation-circle"></i>
              سيؤدي حذف الحساب إلى حذف بياناتك وإنهاء الجلسة. لا يمكن التراجع عن هذا الإجراء.
            </p>
            <form method="POST" action="{{ route('profile.destroy') }}" onsubmit="return confirm('تأكيد حذف الحساب نهائيًا؟')">
              @csrf @method('DELETE')
              <div class="profile-form-grid">
                <div class="profile-form-group">
                  <label class="profile-form-label">أدخل كلمة المرور للتأكيد</label>
                  <input type="password" name="password" class="profile-form-input" required>
                  @error('password','userDeletion')
                    <div class="profile-form-error">{{ $message }}</div>
                  @enderror
                </div>
              </div>
              <div style="margin-top: 1.5rem;">
                <button type="submit" class="profile-btn profile-btn-danger">
                  <i class="fa fa-trash-alt"></i> حذف نهائي
                </button>
              </div>
            </form>
          </div>
        </section>

      </main>
  </div>
</div>

{{-- Avatar Modal - Complete Redesign --}}
<div class="modal fade" id="avatarModal" tabindex="-1" aria-labelledby="avatarModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content avatar-modal-content">
      <div class="modal-header avatar-modal-header">
        <h5 class="modal-title avatar-modal-title" id="avatarModalLabel">
          <i class="fa fa-image"></i> تعديل الصورة الشخصية
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body avatar-modal-body">
        {{-- Upload Section --}}
        <div class="avatar-upload-section">
          <label class="avatar-upload-label">اختر صورة من جهازك</label>
          <div class="avatar-file-input-wrapper">
            <input type="file" accept="image/*" id="avatarInputFile" class="avatar-file-input">
            <div class="avatar-tool-buttons">
              <button type="button" class="avatar-tool-btn" id="rotateLeftBtn" title="دوران يسار">
                <i class="fa fa-rotate-left"></i>
              </button>
              <button type="button" class="avatar-tool-btn" id="rotateRightBtn" title="دوران يمين">
                <i class="fa fa-rotate-right"></i>
              </button>
              <button type="button" class="avatar-tool-btn" id="flipHBtn" title="قلب أفقي">
                <i class="fa fa-arrows-left-right"></i>
              </button>
              <button type="button" class="avatar-tool-btn" id="flipVBtn" title="قلب عمودي">
                <i class="fa fa-arrows-up-down"></i>
              </button>
            </div>
          </div>
        </div>

        {{-- Crop & Preview Section --}}
        <div class="avatar-crop-preview-wrapper">
          <div class="avatar-crop-area">
            <img id="cropImageMain" src="{{ $avatarUrl }}" alt="للقص">
          </div>
          <div class="avatar-preview-section">
            <label class="avatar-preview-label">المعاينة</label>
            <img id="cropPreviewMain" class="avatar-preview-box" src="{{ $avatarUrl }}" alt="معاينة">
          </div>
        </div>
      </div>
      <div class="modal-footer avatar-modal-footer">
        <p class="avatar-modal-note">
          <i class="fa fa-info-circle"></i> يتم الرفع فور الضغط على "تطبيق"
        </p>
        <div class="avatar-modal-actions">
          <button type="button" class="profile-btn profile-btn-secondary" data-bs-dismiss="modal">
            <i class="fa fa-times"></i> إلغاء
          </button>
          <button type="button" class="profile-btn profile-btn-primary" id="applyCropBtn">
            <i class="fa fa-check"></i> تطبيق
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
  // نسخ إلى الحافظة (رابط الدعوة) مع تأكيد بصري
  function plCopy(text, btn){
    var done = function(){ if(!btn) return; var o=btn.innerHTML; btn.innerHTML='<i class="fa fa-check"></i> تم النسخ'; setTimeout(function(){btn.innerHTML=o;},1500); };
    if(navigator.clipboard && navigator.clipboard.writeText){
      navigator.clipboard.writeText(text).then(done).catch(function(){ plCopyFb(text); done(); });
    } else { plCopyFb(text); done(); }
  }
  function plCopyFb(text){
    var ta=document.createElement('textarea'); ta.value=text; ta.style.position='fixed'; ta.style.opacity='0';
    document.body.appendChild(ta); ta.select(); try{document.execCommand('copy');}catch(e){} document.body.removeChild(ta);
  }
</script>
<script>
  const $ = s => document.querySelector(s);
  const $$ = s => document.querySelectorAll(s);
  const CSRF = document.querySelector('meta[name="csrf-token"]').content;

  // ===== Section Navigation =====
  const ACTIVE_KEY = 'hp:profile:active';
  
  function activateSection(key) {
    $$('.profile-section-card').forEach(s => s.classList.remove('active'));
    const sec = document.getElementById('section-' + key);
    if (sec) sec.classList.add('active');

    $$('.section-chip').forEach(chip => {
      if (chip.dataset.section === key) {
        chip.classList.add('active');
      } else {
        chip.classList.remove('active');
      }
    });

    try {
      localStorage.setItem(ACTIVE_KEY, key);
    } catch (e) {}
  }

  document.addEventListener('click', (e) => {
    const chip = e.target.closest('[data-section]');
    if (!chip) return;
    e.preventDefault();
    activateSection(chip.dataset.section);
  });

  // ===== Form Lock/Unlock =====
  function lockForm(id, lock = true) {
    const form = document.getElementById(id);
    if (!form) return;
    
    const inputs = form.querySelectorAll('.js-lock');
    const card = form.closest('.profile-section-card');
    const btnSave = card?.querySelector('.js-save');
    const btnCancel = card?.querySelector('.js-cancel');
    const btnEdit = card?.querySelector('.js-edit');

    if (lock) {
      form.classList.add('locked');
      inputs.forEach(el => {
        if (el.tagName === 'SELECT') {
          el.disabled = true;
        } else {
          el.readOnly = true;
        }
      });
      btnSave?.classList.add('d-none');
      btnCancel?.classList.add('d-none');
      btnEdit?.classList.remove('d-none');
    } else {
      form.classList.remove('locked');
      inputs.forEach(el => {
        if (el.tagName === 'SELECT') {
          el.disabled = false;
        } else {
          el.readOnly = false;
        }
      });
      btnSave?.classList.remove('d-none');
      btnCancel?.classList.remove('d-none');
      btnEdit?.classList.add('d-none');
    }
  }

  document.addEventListener('click', (e) => {
    const edit = e.target.closest('.js-edit');
    if (edit) {
      lockForm(edit.dataset.form, false);
      return;
    }

    const cancel = e.target.closest('.js-cancel');
    if (cancel) {
      e.preventDefault();
      const form = document.getElementById(cancel.dataset.form);
      if (form) {
        form.reset();
        lockForm(cancel.dataset.form, true);
      }
      return;
    }

    const save = e.target.closest('.js-save');
    if (save) {
      e.preventDefault();
      document.getElementById(save.dataset.form)?.submit();
      return;
    }
  });

  // ===== Avatar Cropper =====
  let cropper = null;
  let scaleX = 1, scaleY = 1;
  const avatarImg = $('#profileAvatarMain');
  const avatarUrlInForm = $('#avatarUrlHidden');
  const modalEl = $('#avatarModal');
  const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
  const cropImage = $('#cropImageMain');
  const preview = $('#cropPreviewMain');
  const fileInput = $('#avatarInputFile');
  const btnApply = $('#applyCropBtn');

  function initCropper() {
    if (!cropImage) return;
    if (btnApply) btnApply.disabled = true;
    if (cropper) {
      try {
        cropper.destroy();
      } catch (_) {}
      cropper = null;
    }
    cropper = new Cropper(cropImage, {
      aspectRatio: 1,
      viewMode: 2,
      background: false,
      autoCropArea: 1,
      ready() {
        updatePreview();
        if (btnApply) btnApply.disabled = false;
      },
      crop() {
        updatePreview();
      }
    });
    scaleX = 1;
    scaleY = 1;
  }

  function updatePreview() {
    if (!cropper || !preview) return;
    try {
      const canvas = cropper.getCroppedCanvas({ width: 512, height: 512 });
      if (canvas) preview.src = canvas.toDataURL('image/webp', 0.95);
    } catch (_) {}
  }

  function openCropper() {
    if (!modal || !cropImage) return;
    cropImage.src = avatarImg ? avatarImg.src : '';
    modal.show();
  }

  avatarImg?.addEventListener('click', openCropper);
  $('#avatarEditBadge')?.addEventListener('click', openCropper);

  if (modalEl) {
    modalEl.addEventListener('shown.bs.modal', () => {
      if (cropImage.complete) {
        initCropper();
      } else {
        cropImage.onload = () => initCropper();
      }
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
      if (cropper) {
        try {
          cropper.destroy();
        } catch (_) {}
        cropper = null;
      }
      if (fileInput) fileInput.value = '';
    });
  }

  fileInput?.addEventListener('change', (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) {
      alert('الملف المختار ليس صورة');
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      alert('الحجم الأقصى 5MB');
      return;
    }

    const reader = new FileReader();
    reader.onload = () => {
      const dataUrl = reader.result;
      if (cropper) {
        cropper.replace(dataUrl);
      } else {
        cropImage.src = dataUrl;
        initCropper();
      }
      if (preview) preview.src = dataUrl;
    };
    reader.readAsDataURL(file);
  });

  $('#rotateLeftBtn')?.addEventListener('click', (e) => {
    e.preventDefault();
    cropper?.rotate(-90);
  });

  $('#rotateRightBtn')?.addEventListener('click', (e) => {
    e.preventDefault();
    cropper?.rotate(90);
  });

  $('#flipHBtn')?.addEventListener('click', (e) => {
    e.preventDefault();
    if (cropper) {
      scaleX *= -1;
      cropper.scaleX(scaleX);
    }
  });

  $('#flipVBtn')?.addEventListener('click', (e) => {
    e.preventDefault();
    if (cropper) {
      scaleY *= -1;
      cropper.scaleY(scaleY);
    }
  });

  btnApply?.addEventListener('click', () => {
    if (!cropper) return;

    const src = cropImage?.src || '';
    const isData = src.startsWith('data:');
    const isSame = src.startsWith(location.origin + '/') || src === '' || isData;
    if (!isSame) {
      alert('لا يمكن قص صورة خارجية. الرجاء اختيار صورة من جهازك.');
      return;
    }

    let canvas;
    try {
      canvas = cropper.getCroppedCanvas({ width: 512, height: 512 });
    } catch (_) {
      alert('تعذّر تجهيز الصورة.');
      return;
    }
    if (!canvas) return;

    const original = btnApply.innerHTML;
    btnApply.disabled = true;
    btnApply.innerHTML = '<i class="fa fa-spinner fa-spin"></i> جاري الرفع...';

    canvas.toBlob(async (blob) => {
      if (!blob) {
        btnApply.disabled = false;
        btnApply.innerHTML = original;
        alert('تعذّر تجهيز الصورة.');
        return;
      }
      try {
        const fd = new FormData();
        fd.append('avatar', blob, 'avatar.webp');

        const res = await fetch("{{ route('profile.avatar.upload') }}", {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': CSRF },
          body: fd
        });
        const json = await res.json();
        if (!res.ok || !json.success) {
          throw new Error(json.message || 'فشل الرفع');
        }

        if (avatarImg && json.url) avatarImg.src = json.url;
        if (avatarUrlInForm && json.url) avatarUrlInForm.value = json.url;
        modal?.hide();
      } catch (err) {
        alert(err.message || 'تعذّر رفع الصورة');
      } finally {
        btnApply.disabled = false;
        btnApply.innerHTML = original;
      }
    }, 'image/webp', 0.95);
  });

  // ===== Auto Redirect Countdown =====
  @if(!empty($redirectCountdown) && !empty($safeRedirect))
    (function() {
      let sec = {{ (int)$redirectCountdown }};
      const url = @json($safeRedirect);
      let cancelled = false;
      const secEl = $('#redirectSec');
      const cancelBtn = $('#cancelRedirect');
      const banner = $('#auto-redirect-banner');

      if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
          cancelled = true;
          if (banner) {
            banner.classList.remove('profile-alert-info');
            banner.classList.add('profile-alert-warning');
            const contentWrapper = banner.querySelector('div[style*="flex: 1"]');
            if (contentWrapper) {
              const textDiv = contentWrapper.querySelector('div:first-child');
              const buttonsDiv = contentWrapper.querySelector('div:last-child');
              if (textDiv) {
                textDiv.innerHTML = 'تم إلغاء التحويل التلقائي. يمكنك المتابعة هنا أو الضغط على "الذهاب الآن".';
              }
              // إخفاء زر الإلغاء بعد الضغط عليه
              if (cancelBtn) {
                cancelBtn.style.display = 'none';
              }
            }
          }
        });
      }

      function tick() {
        if (cancelled) return;
        if (sec <= 0) {
          window.location.assign(url);
          return;
        }
        sec--;
        if (secEl) secEl.textContent = sec;
        setTimeout(tick, 1000);
      }
      setTimeout(tick, 1000);
    })();
  @endif

  // ===== Load Specialties by University =====
  const universitySelect = $('#university-select');
  const specialtySelect = $('#specialty-select');
  
  if (universitySelect && specialtySelect) {
    universitySelect.addEventListener('change', async function() {
      const universityId = this.value;
      
      // إعادة تعيين التخصص
      specialtySelect.innerHTML = '<option value="">— اختر —</option>';
      specialtySelect.disabled = true;
      
      if (!universityId) {
        return;
      }
      
      try {
        specialtySelect.disabled = true;
        specialtySelect.innerHTML = '<option value="">جاري التحميل...</option>';
        
        const res = await fetch(`{{ route('profile.specialties.by-university') }}?university_id=${universityId}`, {
          method: 'GET',
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': CSRF
          }
        });
        
        const json = await res.json();
        
        if (!res.ok || !json.success) {
          throw new Error(json.message || 'فشل جلب التخصصات');
        }
        
        specialtySelect.innerHTML = '<option value="">— اختر —</option>';
        
        if (json.specialties && json.specialties.length > 0) {
          json.specialties.forEach(specialty => {
            const option = document.createElement('option');
            option.value = specialty.id;
            option.textContent = specialty.name;
            specialtySelect.appendChild(option);
          });
        }
        
        specialtySelect.disabled = false;
      } catch (err) {
        console.error('خطأ في جلب التخصصات:', err);
        specialtySelect.innerHTML = '<option value="">— خطأ في التحميل —</option>';
        alert('تعذّر جلب التخصصات. يرجى المحاولة مرة أخرى.');
      }
    });
  }

  // ===== Initialize =====
  document.addEventListener('DOMContentLoaded', () => {
    const initial = '{{ session('active', 'auth') }}';
    activateSection(initial);

    ['form-auth', 'form-diploma', 'form-security'].forEach(id => {
      lockForm(id, true);
    });

    // تفعيل select الجامعة عند فتح النموذج للتعديل
    const diplomaForm = $('#form-diploma');
    if (diplomaForm) {
      const unlockBtn = diplomaForm.querySelector('.unlock-btn');
      if (unlockBtn) {
        unlockBtn.addEventListener('click', () => {
          if (universitySelect && !universitySelect.disabled) {
            // إذا كانت هناك جامعة مختارة، جلب التخصصات المرتبطة بها
            if (universitySelect.value) {
              universitySelect.dispatchEvent(new Event('change'));
            }
          }
        });
      }
    }

    setTimeout(() => {
      $$('.auto-dismiss').forEach(el => {
        el.style.transition = 'opacity 0.5s ease';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 500);
      });
    }, 10000);
  });
</script>
@endpush
