@extends('layouts.public')

@section('title', __('Download · SnapAsk'))
@section('description', __('Download SnapAsk for Windows or macOS.'))

@push('styles')
<style>
  .head { padding: 76px 0 12px; }
  .head h1 {
    font-size: clamp(30px, 5vw, 42px); font-weight: 700;
    letter-spacing: -.032em; line-height: 1.1; text-wrap: balance;
  }
  .head p { margin-top: 14px; color: var(--text-dim); max-width: 48ch; text-wrap: pretty; }

  .builds { display: grid; gap: 16px; padding: 38px 0 0; }
  @media (min-width: 760px) { .builds { grid-template-columns: 1fr 1fr; } }

  .build {
    display: flex; flex-direction: column;
    padding: 28px 26px; border: 1px solid var(--line-soft);
    border-radius: var(--r-md); background: var(--ink-1);
  }
  .build--soon { opacity: .62; }
  .build h2 { font-size: 19px; font-weight: 600; letter-spacing: -.016em; }

  .facts { list-style: none; display: grid; gap: 1px; margin: 18px 0 0; }
  .fact { display: flex; gap: 16px; padding: 10px 0; border-top: 1px solid var(--line-soft); font-size: 14px; }
  .fact:last-child { border-bottom: 1px solid var(--line-soft); }
  .fact dt { flex: none; width: 10ch; color: var(--text-faint); }
  .fact dd { color: var(--text-dim); font-variant-numeric: tabular-nums; }

  .build .btn { margin-top: 22px; align-self: flex-start; }
  .build__soon { margin-top: 20px; font-size: 14px; color: var(--text-dim); text-wrap: pretty; }

  /* Mã băm dài và không xuống dòng theo nghĩa nào cả — cho nó tự ngắt ở mọi ký tự. */
  .sum {
    margin-top: 10px; padding: 12px 14px;
    border: 1px solid var(--line-soft); border-radius: var(--r-sm); background: var(--ink-0);
    font: 400 12px/1.6 ui-monospace, monospace; color: var(--text-dim); word-break: break-all;
  }
  .sum__label { display: block; margin-bottom: 4px; font-family: inherit; color: var(--text-faint); }

  .warn {
    margin-top: 44px; padding: 20px 22px;
    border: 1px solid var(--line-soft); border-radius: var(--r-md); background: var(--ink-1);
  }
  .warn h2 { font-size: 16px; font-weight: 600; letter-spacing: -.012em; margin-bottom: 8px; }
  .warn p { font-size: 14.5px; color: var(--text-dim); max-width: 70ch; text-wrap: pretty; }
</style>
@endpush

@section('content')
  <section class="shell head">
    <h1>{{ __('Get SnapAsk') }}</h1>
    <p>{{ __('Snap a region of your screen and ask AI right there.') }}</p>
  </section>

  <section class="shell">
    <div class="builds">
      @foreach (['windows' => __('Windows'), 'mac' => __('macOS')] as $platform => $label)
        @php($build = $release['builds'][$platform])

        <article @class(['build', 'build--soon' => blank($build['file'])])>
          <h2>{{ $label }}</h2>

          <dl class="facts">
            <div class="fact">
              <dt>{{ __('Version') }}</dt>
              <dd>{{ $release['version'] }}</dd>
            </div>

            @if ($build['size'] > 0)
              <div class="fact">
                <dt>{{ __('Size') }}</dt>
                <dd>{{ round($build['size'] / 1048576) }} MB</dd>
              </div>
            @endif

            <div class="fact">
              <dt>{{ __('Requires') }}</dt>
              <dd>{{ __(':os or later', ['os' => $build['min_os']]) }}</dd>
            </div>
          </dl>

          @if (filled($build['file']))
            <a href="{{ route('download.file', $platform) }}" class="btn">{{ __('Download') }}</a>

            @if (filled($build['sha256']))
              {{-- Bộ cài chưa ký số, nên mã băm là cách duy nhất khách tự kiểm được file. --}}
              <p class="sum">
                <span class="sum__label">{{ __('Checksum') }} · {{ __('Compare this against the file you downloaded.') }}</span>
                {{ $build['sha256'] }}
              </p>
            @endif
          @else
            <p class="build__soon">{{ __('This build is not published yet. Run it from source in the meantime.') }}</p>
          @endif
        </article>
      @endforeach
    </div>

    <div class="warn">
      <h2>{{ __('Why does Windows or macOS warn me when I open it?') }}</h2>
      <p>{{ __('The installer is not code-signed yet. On Windows choose More info → Run anyway; on macOS right-click the app and choose Open.') }}</p>
    </div>
  </section>
@endsection
