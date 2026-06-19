@extends('layouts.auth')

@section('title', __('auth.register'))

@section('content')
<div class="login-page">
    
  <div class="login-right">
    <div class="form-box">

      <h1>📝 {{ __('auth.register') }}</h1>

      <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('auth.name')" />
            <x-text-input id="name" class="block mt-1 w-full"
                type="text" name="name"
                :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('auth.email')" />
            <x-text-input id="email" class="block mt-1 w-full"
                type="email" name="email"
                :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('auth.newpassword')" />
            <x-text-input id="password" class="block mt-1 w-full"
                type="password" name="password"
                required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('auth.confirm_password')" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between mt-6">
            <a style="color: var(--primary-500);text-decoration: unset;"
               href="{{ route('login') }}">
                {{ __('auth.already_registered') }}
            </a>
            <x-primary-button class="ms-4 btn btn-primary">
                {{ __('auth.register') }}
            </x-primary-button>
        </div>
      </form>
    </div>
  </div>

  <div class="login-left">
    <img src="{{ asset('images/logo.png') }}" alt="شعار المنصة">
    <h2>منصة كيف التعليمية</h2>
  </div>

</div>
@endsection
