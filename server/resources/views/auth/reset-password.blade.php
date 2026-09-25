@extends('layouts.guest')

@section('title', __('New password · SnapAsk'))

@section('content')
  <h1>{{ __('Set a new password') }}</h1>
  <p class="lead">{{ __('Choose a password you do not use anywhere else.') }}</p>

  <form method="POST" action="{{ route('password.store') }}" class="form panel" novalidate>
    @csrf

    <x-error-summary />

    {{-- Token và email đi cùng biểu mẫu: broker cần cả hai để nhận ra link này
         thuộc về ai và có còn hạn không. --}}
    <input type="hidden" name="token" value="{{ $token }}">

    <x-form-field name="email" type="email" :label="__('Email')" :value="old('email', $email)"
                  autocomplete="username" required readonly />

    <x-form-field name="password" type="password" :label="__('New password')" :hint="__('At least 8 characters.')"
                  autocomplete="new-password" required autofocus />

    <x-form-field name="password_confirmation" type="password" :label="__('Confirm password')"
                  autocomplete="new-password" required />

    <button type="submit" class="btn">{{ __('Change password') }}</button>
  </form>
@endsection
