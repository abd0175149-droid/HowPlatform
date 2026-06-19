@extends('layouts.auth')

@section('title', 'استعادة كلمة المرور')

@section('content')
<div class="login-page">
    
  <div class="login-right">
    <div class="form-box">
        <h1>🔑 استعادة كلمة المرور</h1>
        <p>أدخل بريدك الإلكتروني وسنرسل لك رابط لإعادة تعيين كلمة المرور.</p>

        @if (session('status'))
            <div class="alert">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <label for="email">البريد الإلكتروني</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>

            <button type="submit" class="btn btn-primary">إرسال رابط إعادة التعيين</button>
        </form>
    </div>
  </div>

  <div class="login-left">
    <img src="{{ asset('images/logo.png') }}" alt="شعار المنصة">
    <h2>منصة كيف التعليمية</h2>
  </div>

</div>
@endsection
