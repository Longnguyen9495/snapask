@props(['conversation', 'actions' => false])

@php
  use App\Http\Resources\ConversationResource;

  $preview = ConversationResource::preview($conversation->last_message_content);
@endphp

{{--
  Một dòng hội thoại. Cả dòng bấm được nhờ link tiêu đề trải rộng ra, nhưng các
  nút bên phải vẫn nằm trên lớp đó và bấm riêng được.
--}}
<li class="row row--link">
  <div class="row__main">
    <h3 class="row__title">
      <a href="{{ route('web.conversations.show', $conversation) }}" class="truncate">{{ $conversation->title ?: __('Untitled') }}</a>
    </h3>

    @if ($preview)
      <p class="row__preview">{{ $preview }}</p>
    @endif

    <p class="row__meta">
      <span><x-icon name="clock" :size="13" /><x-time :at="$conversation->updated_at" /></span>
      <span class="tabular">{{ trans_choice(':count message|:count messages', $conversation->messages_count, ['count' => $conversation->messages_count]) }}</span>
      @if ($conversation->model)
        <span class="tag">{{ $conversation->model }}</span>
      @endif
      @if ($conversation->imageIsRetained())
        <span><x-icon name="image" :size="13" />{{ __('Screenshot kept') }}</span>
      @elseif ($conversation->startedWithImage())
        <span><x-icon name="image" :size="13" />{{ __('Screenshot deleted') }}</span>
      @endif
    </p>
  </div>

  @if ($actions)
    <div class="row__side">
      <form method="POST" action="{{ route('web.conversations.destroy', $conversation) }}"
            data-confirm="{{ __('“:title” and all of its messages will be deleted for good, together with its screenshot. This cannot be undone.', ['title' => $conversation->title ?: __('Untitled')]) }}"
            data-confirm-title="{{ __('Delete this conversation?') }}"
            data-confirm-action="{{ __('Delete conversation') }}">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn--ghost btn--icon btn--sm"
                aria-label="{{ __('Delete “:title”', ['title' => $conversation->title ?: __('Untitled')]) }}">
          <x-icon name="trash" :size="16" />
        </button>
      </form>
    </div>
  @endif
</li>
