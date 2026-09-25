@extends('layouts.guest')

@section('title', __('Verify your email · SnapAsk'))

@section('content')
  <h1>{{ __('Check your inbox') }}</h1>
  <p class="lead">
    {{ __('We sent a verification link to :email. Open it to start using SnapAsk.', ['email' => auth()->user()->email]) }}
  </p>

  <x-flash />

  <div class="form panel">
    <p class="muted">{{ __('No email after a few minutes? Check your spam folder, or send it again.') }}</p>

    <form method="POST" action="{{ route('verification.send') }}">
      @csrf
      <button type="submit" class="btn">{{ __('Send the link again') }}</button>
    </form>
  </div>

  <form method="POST" action="{{ route('logout') }}" class="guest__foot">
    @csrf
    <button type="submit" class="btn btn--ghost">{{ __('Sign out') }}</button>
  </form>
@endsection
