@extends('layouts.guest')

@section('title', __('Page not found · SnapAsk'))

@section('content')
  <h1>{{ __('Page not found') }}</h1>
  <p class="lead">{{ __('That address does not exist, or it has been removed.') }}</p>

  <div class="actions">
    @auth
      <a href="{{ route('dashboard') }}" class="btn">{{ __('Back to overview') }}</a>
    @else
      <a href="{{ url('/') }}" class="btn">{{ __('Back to home') }}</a>
    @endauth
  </div>
@endsection
