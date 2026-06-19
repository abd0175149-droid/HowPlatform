@extends('layouts.auth')

@section('title', 'تسجيل الدخول')

@section('content')
<div class="login-page">
    {{-- القسم الأيمن --}}
    <div class="login-right">
        <div class="form-box">
            <h1>👋 أهلاً بك</h1>
            <p>سجّل دخولك للمتابعة إلى المنصة.</p>

            {{-- إظهار الأخطاء --}}
            @if ($errors->any())
                <div class="alert">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div class="social-login">
            <a href="{{ route('google.login') }}" class="btn-google">
                <img src="{{ asset('images/google-icon.svg') }}" alt="Google Icon" class="google-icon">
                تسجيل الدخول باستخدام Google
            </a>
        </div>


            <form method="POST" action="{{ route('login') }}">
                @csrf

                <label for="email">البريد الإلكتروني</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>

                <label for="password">كلمة المرور</label>
                <input id="password" type="password" name="password" required>

                <div class="form-check">
                    <input type="checkbox" name="remember" id="remember">
                    <label for="remember">تذكرني</label>
                </div>

                <button type="submit" class="btn btn-primary">تسجيل الدخول</button>
            </form>

            <div class="extra-links">
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}">نسيت كلمة المرور؟</a>
                @endif
                <p>ليس لديك حساب؟ <a href="{{ route('register') }}">إنشاء حساب جديد</a></p>
            </div>
        </div>
    </div>
    
    {{-- القسم الأيسر --}}
    <div class="login-left">
        <img src="{{ asset('images/logo.png') }}" alt="شعار المنصة">
        <h2>منصة كيف التعليمية</h2>
    </div>

</div>
@endsection
