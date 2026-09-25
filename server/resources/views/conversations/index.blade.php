@extends('layouts.app')

@section('title', __('Conversation history · SnapAsk'))
@section('description', __('Look back at what you asked and what the AI answered.'))
@section('section', __('Conversations'))

@section('content')
  <x-page-header :title="__('Conversation history')"
                 :lead="__('Screenshots are deleted after :days days; the text stays.', ['days' => config('snapask.image.retention_days')])" />

  @if ($hasAny)
    {{-- GET để kết quả tìm có đường dẫn riêng: chia sẻ, bấm Back hay tải lại đều giữ nguyên. --}}
    <form method="GET" action="{{ route('web.conversations.index') }}" class="toolbar" role="search" aria-label="{{ __('Search conversations') }}">
      <div class="field search-field">
        <label class="field__label" for="search">{{ __('Search') }}</label>
        <div class="search">
          <x-icon name="search" :size="16" />
          <input type="search" id="search" name="search" class="input" value="{{ $filters['search'] }}"
                 maxlength="{{ \App\Models\Conversation::SEARCH_MAX_LENGTH }}"
                 placeholder="{{ __('Title or message text') }}" autocomplete="off">
        </div>
      </div>

      <div class="field">
        <label class="field__label" for="period">{{ __('Last activity') }}</label>
        <select id="period" name="period" class="select">
          <option value="">{{ __('Any time') }}</option>
          @foreach ($periods as $period)
            <option value="{{ $period }}" @selected($filters['period'] === $period)>{{ __('Last :days days', ['days' => (int) $period]) }}</option>
          @endforeach
        </select>
      </div>

      <div class="field">
        <label class="field__label" for="screenshot">{{ __('Screenshot') }}</label>
        <select id="screenshot" name="screenshot" class="select">
          <option value="">{{ __('All') }}</option>
          <option value="with" @selected($filters['screenshot'] === 'with')>{{ __('With a screenshot') }}</option>
          <option value="without" @selected($filters['screenshot'] === 'without')>{{ __('Text only') }}</option>
        </select>
      </div>

      <div class="field">
        <label class="field__label" for="model">{{ __('Model') }}</label>
        <select id="model" name="model" class="select">
          <option value="">{{ __('All models') }}</option>
          @foreach ($models as $model)
            <option value="{{ $model }}" @selected($filters['model'] === $model)>{{ $model }}</option>
          @endforeach
        </select>
      </div>

      <div class="toolbar__actions">
        <button type="submit" class="btn btn--secondary"><x-icon name="filter" :size="16" />{{ __('Apply') }}</button>
        @if ($filtering)
          <a href="{{ route('web.conversations.index') }}" class="btn btn--ghost">{{ __('Clear') }}</a>
        @endif
      </div>
    </form>

    <div class="results" role="status">
      <p class="tabular">
        @if ($filtering)
          {{ trans_choice(':count matching conversation|:count matching conversations', $conversations->total(), ['count' => $conversations->total()]) }}
        @else
          {{ trans_choice(':count conversation|:count conversations', $conversations->total(), ['count' => $conversations->total()]) }}
        @endif
      </p>
    </div>
  @endif

  @if ($conversations->isNotEmpty())
    <ul class="list" aria-label="{{ __('Conversations') }}">
      @foreach ($conversations as $conversation)
        <x-conversation-row :conversation="$conversation" :actions="true" />
      @endforeach
    </ul>

    {{ $conversations->links('partials.pagination') }}
  @elseif ($filtering)
    <x-empty-state :title="__('No matching conversations')" icon="search">
      {{ __('Try a shorter search, or clear the filters to see everything.') }}
      <x-slot:actions>
        <a href="{{ route('web.conversations.index') }}" class="btn btn--secondary">{{ __('Clear filters') }}</a>
      </x-slot:actions>
    </x-empty-state>
  @else
    <x-empty-state :title="__('Nothing here yet')" icon="message">
      {{ __('Snap a region with the desktop app and ask something — the conversation shows up here.') }}
      <x-slot:actions>
        <a href="{{ app()->getLocale() === 'vi' ? route('download') : route('download.en') }}" class="btn btn--secondary">
          <x-icon name="download" :size="16" />{{ __('Get the desktop app') }}
        </a>
      </x-slot:actions>
    </x-empty-state>
  @endif
@endsection
