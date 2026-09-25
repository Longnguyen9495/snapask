@extends('layouts.guest')

@section('title', __('Create account · SnapAsk'))
@section('description', __('Create a SnapAsk account and try it free.'))

@section('content')
  <h1>{{ __('Create account') }}</h1>
  <p class="lead">{{ __('Free to try, no card required.') }}</p>

  <form method="POST" action="{{ route('register.store') }}" class="form panel" novalidate>
    @csrf

    <x-error-summary />

    <x-form-field name="name" :label="__('Your name')" :value="old('name')" autocomplete="name" required autofocus />

    <x-form-field name="email" type="email" :label="__('Email')" :value="old('email')" autocomplete="username" required />

    <x-form-field name="password" type="password" :label="__('Password')" :hint="__('At least 8 characters.')"
                  autocomplete="new-password" required />

    <x-form-field name="password_confirmation" type="password" :label="__('Confirm password')"
                  autocomplete="new-password" required />

    <button type="submit" class="btn">{{ __('Create account') }}</button>
  </form>

  <p class="guest__foot">
    {{ __('Already have an account?') }}
    <a href="{{ route('login') }}">{{ __('Sign in') }}</a>
  </p>
@endsection
