@extends('layouts.app')

@section('title', __('Conversation · SnapAsk'))

@section('content')
  <p class="note" style="margin-bottom: 14px">
    <a href="{{ route('web.conversations.index') }}">← {{ __('Back to history') }}</a>
  </p>

  <div class="row">
    <div>
      <h1>{{ $conversation->title ?: __('Untitled') }}</h1>
      <p class="note tabular" style="margin-top: 6px">
        {{ $conversation->created_at->diffForHumans() }}
        @if ($conversation->model)
          · <span class="tag">{{ $conversation->model }}</span>
        @endif
      </p>
    </div>

    <form method="POST"
          action="{{ route('web.conversations.destroy', $conversation) }}"
          onsubmit="return confirm('{{ __('Delete this conversation?') }}')">
      @csrf
      @method('DELETE')
      <button type="submit" class="btn--quiet btn--danger">{{ __('Delete') }}</button>
    </form>
  </div>

  {{--
    Ảnh chụp, nếu chưa quá hạn lưu trữ. Đã bị `snapask:prune` xoá thì nói thẳng
    một câu, chứ không để lại một khung ảnh vỡ.
  --}}
  <article class="card">
    <h2 style="margin-bottom: 12px">{{ __('Screenshot') }}</h2>

    @if ($conversation->image_path && $conversation->image_expires_at?->isFuture())
      <img src="{{ route('web.conversations.image', $conversation) }}"
           alt="{{ __('Screenshot') }}"
           width="{{ $conversation->image_width }}"
           height="{{ $conversation->image_height }}"
           style="max-width: 100%; height: auto; border-radius: var(--r-sm); border: 1px solid var(--line-soft)">
    @else
      <p class="note">{{ __('The screenshot has been deleted.') }}</p>
    @endif
  </article>

  @foreach ($messages as $message)
    <article class="card">
      <div class="row" style="margin-bottom: 8px; align-items: baseline">
        <h2>{{ $message->role === 'user' ? __('You') : __('SnapAsk') }}</h2>
        <span class="tag tabular">{{ $message->created_at->diffForHumans() }}</span>
      </div>

      {{-- Chữ do người dùng và mô hình sinh ra: để Blade thoát HTML như thường. --}}
      <p class="note" style="white-space: pre-wrap">{{ $message->content }}</p>

      @if (filled($message->tools_used))
        <p class="note status--ok" style="margin-top: 10px">
          {{ __('Looked up :tools', [
              'tools' => implode(', ', array_column($message->tools_used, 'name')),
          ]) }}
        </p>
      @endif
    </article>
  @endforeach
@endsection
