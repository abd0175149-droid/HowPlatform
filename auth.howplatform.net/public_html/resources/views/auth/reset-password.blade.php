@extends('layouts.auth')

@section('title', 'تعيين كلمة مرور جديدة')

@section('content')
<div class="login-page">
  <div class="login-right">
    <div class="form-box">
        <h1>🔐 تعيين كلمة مرور جديدة</h1>

        <form method="POST" action="{{ route('password.store') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <input type="hidden" name="email" value="{{ $request->email }}">

            <label for="password">كلمة المرور الجديدة</label>
            <input id="password" type="password" name="password" required>

            <label for="password_confirmation">تأكيد كلمة المرور</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required>

            <button type="submit" class="btn btn-primary">تحديث كلمة المرور</button>
        </form>
    </div>
  </div>

  <div class="login-left">
    <img src="{{ asset('images/logo.png') }}" alt="شعار المنصة">
    <h2>منصة كيف التعليمية</h2>
  </div>

</div>
@endsection
