@extends('layouts.user')
@section('title','تفعيل بطاقة | Plans')

@section('content')
  <div class="profile-section-card active mx-auto" style="max-width:520px;">
    <div class="section-card-header">
      <h3 class="section-card-title"><span class="section-card-title-icon"><i class="fas fa-qrcode"></i></span><span>تأكيد تفعيل البطاقة</span></h3>
    </div>
    <div class="section-card-body text-center">
      <p class="text-muted small mb-3">راجِع الكود ثم اضغط «تفعيل» لإضافة الاشتراك/النقاط إلى حسابك.</p>
      <form method="post" action="{{ route('user.redeem') }}">
        @csrf
        <div class="profile-form-group mb-3 text-start">
          <label class="profile-form-label">كود البطاقة</label>
          <input name="code" class="profile-form-input text-center" style="letter-spacing:2px; font-size:1.15rem;" value="{{ $code }}" required autofocus>
        </div>
        <button class="profile-btn profile-btn-primary w-100"><i class="fas fa-circle-check"></i> تفعيل</button>
      </form>
      <a href="{{ route('user.dashboard') }}" class="d-inline-block mt-3 small" style="color:var(--primary-600);">&larr; الذهاب إلى لوحتي</a>
    </div>
  </div>
@endsection
