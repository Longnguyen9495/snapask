@extends('layouts.guest')

@section('title', __('Sign in · SnapAsk'))
@section('description', __('Sign in to SnapAsk to manage your connected services.'))

@section('content')
  <h1>{{ __('Sign in') }}</h1>
  <p class="lead">{{ __('Manage your conversations, AI models and connected services.') }}</p>

  <form method="POST" action="{{ route('login.store') }}" class="form panel" novalidate>
    @csrf

    <x-error-summary />

    <x-form-field name="email" type="email" :label="__('Email')" :value="old('email')"
                  autocomplete="username" required autofocus />

    <x-form-field name="password" type="password" :label="__('Password')"
                  autocomplete="current-password" required />

    <label class="check">
      <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
      {{ __('Remember me on this device') }}
    </label>

    <button type="submit" class="btn">{{ __('Sign in') }}</button>
  </form>

  <p class="guest__foot">
    {{ __('No account yet?') }}
    <a href="{{ route('register') }}">{{ __('Create account') }}</a>
  </p>
@endsection
