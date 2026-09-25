@extends('layouts.app')

@section('title', __('Create account · SnapAsk'))
@section('description', __('Create a SnapAsk account and try it free.'))
@section('wrap-modifier', 'wrap--narrow')

@section('content')
  <h1>{{ __('Create account') }}</h1>
  <p class="lead">{{ __('Free to try, no card required.') }}</p>

  <form method="POST" action="{{ route('register.store') }}" class="stack card">
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
      {{ __('Your name') }}
      <input type="text" name="name" value="{{ old('name') }}" required autofocus>
    </label>

    <label>
      {{ __('Email') }}
      <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required>
    </label>

    <label>
      {{ __('Password') }}
      <input type="password" name="password" autocomplete="new-password" required>
    </label>

    <label>
      {{ __('Confirm password') }}
      <input type="password" name="password_confirmation" autocomplete="new-password" required>
    </label>

    <button type="submit">{{ __('Create account') }}</button>
  </form>

  <p class="note center" style="margin-top: 18px">
    {{ __('Already have an account?') }}
    <a href="{{ route('login') }}" style="color: var(--accent)">{{ __('Sign in') }}</a>
  </p>
@endsection
