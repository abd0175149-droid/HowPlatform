@extends('layouts.auth')

@section('title', __('auth.verify_email_title'))

@section('content')
<div class="login-page">
  {{-- يمين: محتوى التأكيد --}}
  <div class="login-right">
    <div class="form-box">

        <h1>📧 {{ __('auth.verify_email_title') }}</h1>
        <p>{{ __('auth.verify_email_message') }}</p>

        {{-- رسالة نجاح عند إرسال رابط جديد --}}
        @if (session('status') == 'verification-link-sent')
            <div class="alert" style="background:#e6ffed; color:#155724; padding:1rem; border-radius:8px; margin:1rem 0;">
                {{ __('auth.verify_email_sent') }}
            </div>
        @endif

        <div class="flex" style="display:flex; gap:1rem; margin-top:1.5rem;align-items: center;">
            {{-- إعادة إرسال رابط التفعيل --}}
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="btn btn-primary">
                    {{ __('auth.resend_verification') }}
                </button>
            </form>

            {{-- تسجيل الخروج --}}
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-ghost">
                    {{ __('auth.logout') }}
                </button>
            </form>
        </div>

    </div>
  </div>
  {{-- يسار: شعار وخلفية --}}
  <div class="login-left">
    <img src="{{ asset('images/logo.png') }}" alt="شعار المنصة">
    <h2>منصة كيف التعليمية</h2>
  </div>


</div>
@endsection
