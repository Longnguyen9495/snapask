@extends('layouts.guest')

@section('title', __('Forgot password · SnapAsk'))
@section('description', __('Get a link to set a new SnapAsk password.'))

@section('content')
  <h1>{{ __('Forgot your password?') }}</h1>
  <p class="lead">{{ __('Enter the email you signed up with and we will send a link to set a new one.') }}</p>

  <x-flash />

  <form method="POST" action="{{ route('password.email') }}" class="form panel" novalidate>
    @csrf

    <x-error-summary />

    <x-form-field name="email" type="email" :label="__('Email')" :value="old('email')"
                  autocomplete="username" required autofocus />

    <button type="submit" class="btn">{{ __('Send reset link') }}</button>
  </form>

  <p class="guest__foot">
    {{ __('Remembered it after all?') }}
    <a href="{{ route('login') }}">{{ __('Sign in') }}</a>
  </p>
@endsection
