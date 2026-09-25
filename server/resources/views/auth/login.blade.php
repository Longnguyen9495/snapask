@extends('layouts.app')

@section('title', __('Sign in · SnapAsk'))
@section('description', __('Sign in to SnapAsk to manage your connected services.'))
@section('wrap-modifier', 'wrap--narrow')

@section('content')
  <h1>{{ __('Sign in') }}</h1>
  <p class="lead">{{ __('Manage connected services and check your quota.') }}</p>

  <form method="POST" action="{{ route('login.store') }}" class="stack card">
    @csrf

    @if ($errors->any())
      <div class="errors">
        <ul>
          @foreach ($errors->all() as $message)
            <li>{{ $message }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <label>
      {{ __('Email') }}
      <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
    </label>

    <label>
      {{ __('Password') }}
      <input type="password" name="password" autocomplete="current-password" required>
    </label>

    <label class="inline">
      <input type="checkbox" name="remember" value="1">
      {{ __('Remember me on this device') }}
    </label>

    <button type="submit">{{ __('Sign in') }}</button>
  </form>

  <p class="note center" style="margin-top: 18px">
    {{ __('No account yet?') }}
    <a href="{{ route('register') }}" style="color: var(--accent)">{{ __('Create account') }}</a>
  </p>
@endsection
