@extends('layouts.public')

@section('title', __('Download · SnapAsk'))
@section('description', __('Download SnapAsk for Windows or macOS.'))

@push('styles')
<style>
  /*
   * Chỉ đặt lề trên dưới. Viết `padding: 76px 0 12px` sẽ xoá luôn
   * `padding-inline` mà `.shell` đặt, và tiêu đề dính sát mép màn hình điện thoại.
   */
  .head { padding-block: 76px 12px; }
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

  /* Mã băm: đóng sẵn, mở ra mới thấy. Dãy 64 ký tự tự ngắt ở mọi vị trí. */
  .sum { margin-top: 16px; font-size: 13px; }
  .sum summary {
    display: inline-flex; align-items: center; gap: 7px;
    min-height: 40px; color: var(--text-faint); cursor: pointer; list-style: none;
  }
  .sum summary::-webkit-details-marker { display: none; }
  .sum summary::before { content: '+'; width: 12px; color: var(--accent); font-size: 15px; }
  .sum[open] summary::before { content: '−'; }
  .sum summary:hover { color: var(--text-dim); }
  .sum__hint { margin-bottom: 8px; color: var(--text-faint); text-wrap: pretty; }
  .sum code {
    display: block; padding: 11px 13px;
    border: 1px solid var(--line-soft); border-radius: var(--r-sm); background: var(--ink-0);
    font: 400 12px/1.6 ui-monospace, monospace; color: var(--text-dim); word-break: break-all;
  }

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
              {{--
                Mã băm thu vào đây, đóng sẵn.

                Phần lớn người tải về không cần tới nó và một dãy 64 ký tự nằm
                giữa trang chỉ làm họ phân vân. Người muốn tự đối chiếu vẫn mở
                được bằng một cú bấm.
              --}}
              <details class="sum">
                <summary>{{ __('Verify the file') }}</summary>
                <p class="sum__hint">{{ __('Optional. Compare this code with the file you downloaded to be sure it arrived intact.') }}</p>
                <code>{{ $build['sha256'] }}</code>
              </details>
            @endif
          @else
            <p class="build__soon">{{ __('This build is not out yet. Try the other platform, or check back soon.') }}</p>
          @endif
        </article>
      @endforeach
    </div>

    {{--
      Hướng dẫn qua màn cảnh báo của hệ điều hành.

      Không nhắc tới chuyện ký số: người tải về không cần biết vì sao, họ cần
      biết bấm gì để đi tiếp. Thiếu chỉ dẫn này thì phần lớn dừng lại ở đó.
    --}}
    <div class="warn">
      <h2>{{ __('Windows or macOS shows a warning. What do I do?') }}</h2>
      <p>{{ __('That warning appears for every app the system has not seen before. On Windows choose More info, then Run anyway. On macOS right-click SnapAsk and choose Open.') }}</p>
    </div>
  </section>
@endsection
