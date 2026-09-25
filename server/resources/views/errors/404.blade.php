@extends('layouts.app')

@section('title', __('Page not found · SnapAsk'))
@section('wrap-modifier', 'wrap--narrow')

@section('content')
  <h1>{{ __('Page not found') }}</h1>
  <p class="lead">{{ __('That address does not exist, or it has been removed.') }}</p>

  <a href="{{ url('/') }}" class="btn">{{ __('Back to home') }}</a>
@endsection
