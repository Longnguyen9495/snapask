@extends('layouts.app')

@section('title', __('Conversation history · SnapAsk'))
@section('description', __('Look back at what you asked and what the AI answered.'))

@section('content')
  <div class="row">
    <div>
      <h1>{{ __('Conversation history') }}</h1>
      <p class="note" style="margin-top: 6px">
        {{ __('Screenshots are deleted after :days days; the text stays.', [
            'days' => config('snapask.image.retention_days'),
        ]) }}
      </p>
    </div>
  </div>

  @if (session('status'))
    <p class="flash">{{ session('status') }}</p>
  @endif

  @forelse ($conversations as $conversation)
    <article class="card">
      <div class="row" style="margin-bottom: 4px; align-items: baseline">
        <h2>
          <a href="{{ route('web.conversations.show', $conversation) }}">
            {{ $conversation->title ?: __('Untitled') }}
          </a>
        </h2>
        <span class="tag tabular">{{ $conversation->created_at->diffForHumans() }}</span>
      </div>

      <p class="note tabular">
        {{ trans_choice(':count message|:count messages', $conversation->messages_count, [
            'count' => $conversation->messages_count,
        ]) }}
        @if ($conversation->model)
          · <span class="tag">{{ $conversation->model }}</span>
        @endif
      </p>
    </article>
  @empty
    <div class="card" style="padding: 30px 26px">
      <h2>{{ __('Nothing here yet') }}</h2>
      <p class="note" style="margin-top: 8px; max-width: 54ch">
        {{ __('Snap a region with the desktop app and ask something — the conversation shows up here.') }}
      </p>
    </div>
  @endforelse

  {{-- Chỉ hiện khi thật sự có nhiều trang, để trang đầu không mọc thêm một dải trống. --}}
  @if ($conversations->hasPages())
    <div style="margin-top: 20px">
      {{ $conversations->links() }}
    </div>
  @endif
@endsection
