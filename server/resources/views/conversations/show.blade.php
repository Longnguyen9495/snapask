@extends('layouts.app')

@section('title', __('Conversation · SnapAsk'))
@section('section', __('Conversations'))

@section('content')
  @php($title = $conversation->title ?: __('Untitled'))

  <a href="{{ route('web.conversations.index') }}" class="back"><x-icon name="chevron-left" :size="16" />{{ __('Back to history') }}</a>

  <x-page-header :title="$title">
    <x-slot:actions>
      <form method="POST" action="{{ route('web.conversations.destroy', $conversation) }}"
            data-confirm="{{ __('“:title” and all of its messages will be deleted for good, together with its screenshot. This cannot be undone.', ['title' => $title]) }}"
            data-confirm-title="{{ __('Delete this conversation?') }}"
            data-confirm-action="{{ __('Delete conversation') }}">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn--danger"><x-icon name="trash" :size="16" />{{ __('Delete') }}</button>
      </form>
    </x-slot:actions>
  </x-page-header>

  <div class="detail">
    <section aria-label="{{ __('Messages') }}">
      @if ($messages->isEmpty())
        <x-empty-state :title="__('No messages in this conversation')" icon="message" level="h2">
          {{ __('The question may still have been on its way when the connection dropped.') }}
        </x-empty-state>
      @else
        <ol class="thread" role="list">
          @foreach ($messages as $message)
            @php($isUser = $message->role === 'user')
            <li @class(['msg', 'msg--user' => $isUser, 'msg--assistant' => ! $isUser])>
              <p class="msg__head">
                <span class="msg__who">{{ $isUser ? __('You') : __('SnapAsk') }}</span>
                <x-time :at="$message->created_at" />
              </p>

              @if ($isUser)
                {{-- Chữ người dùng gõ: để Blade thoát HTML như thường, giữ nguyên xuống dòng. --}}
                <div class="msg__body">{{ $message->content }}</div>
              @else
                <x-markdown :content="$message->content" />

                @if (filled($message->tools_used))
                  <details class="disclosure disclosure--quiet tools">
                    <summary>
                      <x-icon name="wrench" :size="14" />
                      {{ trans_choice('Looked up :count tool|Looked up :count tools', count($message->tools_used), ['count' => count($message->tools_used)]) }}
                      <x-icon name="chevron-down" :size="14" class="icon--chevron" />
                    </summary>
                    <div class="disclosure__body">
                      <ul>
                        @foreach ($message->tools_used as $call)
                          <li>
                            <span class="tag">{{ $call['tool'] ?? $call['name'] ?? '?' }}</span>
                            @if (filled($call['arguments'] ?? null))
                              <pre>{{ json_encode($call['arguments'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                            @endif
                          </li>
                        @endforeach
                      </ul>
                    </div>
                  </details>
                @endif
              @endif
            </li>
          @endforeach
        </ol>
      @endif
    </section>

    <aside class="detail__rail" aria-label="{{ __('Conversation details') }}">
      {{--
        Ảnh chụp, nếu chưa quá hạn lưu trữ. Đã bị `snapask:prune` xoá thì nói thẳng
        một câu, chứ không để lại một khung ảnh vỡ.
      --}}
      <section aria-labelledby="shot-title">
        <h2 id="shot-title" class="sr-only">{{ __('Screenshot') }}</h2>
        <figure class="attachment">
          @if ($imageAvailable)
            <a href="{{ route('web.conversations.image', $conversation) }}" class="attachment__image" target="_blank" rel="noopener">
              <img src="{{ route('web.conversations.image', $conversation) }}"
                   alt="{{ __('Screenshot for “:title”', ['title' => $title]) }}"
                   @if ($conversation->image_width) width="{{ $conversation->image_width }}" height="{{ $conversation->image_height }}" @endif
                   loading="lazy">
            </a>
            <figcaption class="attachment__meta">
              <x-status-badge status="ok">{{ __('Screenshot kept') }}</x-status-badge>
              @if ($conversation->image_width)
                <span class="tabular">{{ $conversation->image_width }} × {{ $conversation->image_height }} px</span>
              @endif
              @if ($conversation->image_expires_at)
                <span>{{ __('Deleted on :date', ['date' => $conversation->image_expires_at->translatedFormat('j F')]) }}</span>
              @endif
            </figcaption>
          @elseif ($conversation->startedWithImage())
            <div class="attachment__gone"><x-icon name="image" :size="18" /><p>{{ __('The screenshot has been deleted.') }}</p></div>
          @else
            <div class="attachment__gone"><x-icon name="message" :size="18" /><p>{{ __('Asked by text, without a screenshot.') }}</p></div>
          @endif
        </figure>
      </section>

      <section class="panel panel--pad" aria-labelledby="info-title">
        <h2 id="info-title" class="sr-only">{{ __('Details') }}</h2>
        <dl class="kv">
          <dt>{{ __('Started') }}</dt>
          <dd><x-time :at="$conversation->created_at" :relative="false" /></dd>
          <dt>{{ __('Last activity') }}</dt>
          <dd><x-time :at="$conversation->updated_at" /></dd>
          <dt>{{ __('Messages') }}</dt>
          <dd class="tabular">{{ $conversation->messages_count }}</dd>
          <dt>{{ __('Model') }}</dt>
          <dd>@if ($conversation->model)<span class="tag">{{ $conversation->model }}</span>@else<span class="faint">—</span>@endif</dd>
        </dl>
      </section>

      <details class="disclosure disclosure--panel" @if ($errors->rename->any()) open @endif>
        <summary><x-icon name="pencil" :size="16" />{{ __('Rename') }}<x-icon name="chevron-down" :size="16" class="icon--chevron" /></summary>
        <div class="disclosure__body">
          <form method="POST" action="{{ route('web.conversations.update', $conversation) }}" class="form">
            @csrf
            @method('PATCH')
            <x-form-field name="title" id="rename-title" bag="rename" :label="__('Title')"
                          :value="old('title', $conversation->title)" maxlength="{{ \App\Models\Conversation::TITLE_MAX_LENGTH }}" required />
            <div class="form__actions">
              <button type="submit" class="btn btn--secondary">{{ __('Save title') }}</button>
            </div>
          </form>
        </div>
      </details>
    </aside>
  </div>
@endsection
