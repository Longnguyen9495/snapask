@extends('layouts.public')

@push('styles')
<style>
  /* ---- Hero ---- */
  .hero { padding: 86px 0 74px; }
  .hero__grid { display: grid; gap: 46px; align-items: center; }

  .hero h1 {
    font-size: clamp(34px, 6.4vw, 56px); font-weight: 700;
    letter-spacing: -.035em; line-height: 1.04; text-wrap: balance;
  }
  .hero__lead {
    margin: 20px 0 30px; max-width: 46ch;
    font-size: clamp(16px, 2vw, 18px); color: var(--text-dim); text-wrap: pretty;
  }
  .hero__cta { display: flex; flex-wrap: wrap; align-items: center; gap: 14px 18px; }
  .hero__alt { font-size: 14px; color: var(--text-dim); text-decoration: none; }
  .hero__alt:hover { color: var(--text); }
  .hero__free { margin-top: 16px; font-size: 13.5px; color: var(--text-faint); }

  @media (min-width: 900px) {
    .hero__grid { grid-template-columns: 1.02fr .98fr; gap: 56px; }
  }

  /* ---- Khung minh hoạ thao tác chụp ---- */
  .shot {
    position: relative; aspect-ratio: 4 / 3;
    border: 1px solid var(--line-soft); border-radius: var(--r-md);
    background: var(--ink-1); overflow: hidden;
  }
  .shot__bar {
    display: flex; align-items: center; gap: 6px;
    padding: 11px 14px; border-bottom: 1px solid var(--line-soft); background: var(--ink-2);
  }
  .shot__dot { width: 9px; height: 9px; border-radius: 999px; background: var(--line); }
  .shot__body { position: relative; height: calc(100% - 41px); padding: 20px; }

  /* Những dòng chữ giả, để mắt hiểu ngay đây là một cửa sổ đang mở. */
  .shot__line { height: 8px; border-radius: 3px; background: var(--ink-2); margin-bottom: 11px; }
  .shot__line:nth-child(1) { width: 62%; }
  .shot__line:nth-child(2) { width: 84%; }
  .shot__line:nth-child(3) { width: 71%; }
  .shot__line:nth-child(4) { width: 46%; }
  .shot__line:nth-child(5) { width: 78%; }

  /* Vùng đang được quét chọn. */
  .shot__sel {
    position: absolute; left: 13%; top: 26%; width: 62%; height: 40%;
    border: 1.5px solid var(--accent); border-radius: 4px;
    background: color-mix(in srgb, var(--accent) 9%, transparent);
    animation: sweep 5s var(--ease) infinite;
  }
  .shot__sel::after {
    content: ''; position: absolute; right: -4px; bottom: -4px;
    width: 8px; height: 8px; border-radius: 2px; background: var(--accent);
  }

  /* Ô hỏi đáp trượt lên sau khi vùng chọn đứng yên. */
  .shot__ask {
    position: absolute; left: 13%; right: 13%; bottom: 16px;
    padding: 11px 14px; border: 1px solid var(--line); border-radius: var(--r-sm);
    background: var(--ink-0); font-size: 13px; color: var(--text-dim);
    animation: ask 5s var(--ease) infinite;
  }
  .shot__caret {
    display: inline-block; width: 1.5px; height: 13px; margin-left: 1px;
    background: var(--accent); vertical-align: -2px;
    animation: blink 1.1s steps(2) infinite;
  }

  @keyframes sweep {
    0%, 8%    { width: 0; height: 0; opacity: 0; }
    26%, 84%  { width: 62%; height: 40%; opacity: 1; }
    97%, 100% { width: 62%; height: 40%; opacity: 0; }
  }
  @keyframes ask {
    0%, 30%   { opacity: 0; transform: translateY(8px); }
    44%, 84%  { opacity: 1; transform: none; }
    97%, 100% { opacity: 0; transform: translateY(8px); }
  }
  @keyframes blink { 50% { opacity: 0; } }

  /* ---- Khối chung ---- */
  .band { padding: 74px 0; }
  .band__label {
    margin-bottom: 12px; font-size: 12.5px; font-weight: 600;
    letter-spacing: .12em; text-transform: uppercase; color: var(--text-faint);
  }
  h2 { font-size: clamp(24px, 3.4vw, 32px); font-weight: 600; letter-spacing: -.028em; text-wrap: balance; }

  /* Heading thật nhưng mang dáng nhãn nhỏ: cấu trúc đúng cho trình đọc màn
     hình mà mắt vẫn thấy một nhãn, không phải một tiêu đề to thứ hai. */
  .band__label--head {
    font-size: 12.5px; letter-spacing: .12em; text-transform: uppercase;
    color: var(--text-faint);
  }

  /* ---- Ba bước ---- */
  .steps { display: grid; gap: 22px; counter-reset: step; margin-top: 18px; }
  @media (min-width: 760px) { .steps { grid-template-columns: repeat(3, 1fr); gap: 26px; } }

  .step { position: relative; padding-top: 22px; border-top: 1px solid var(--line-soft); }
  .step::before {
    counter-increment: step; content: counter(step);
    position: absolute; top: -1px; left: 0;
    padding-right: 12px; border-top: 1px solid var(--accent);
    font: 600 12.5px/1 ui-monospace, monospace; color: var(--accent);
    transform: translateY(-50%); background: var(--ink-0);
  }
  .step h3 { margin-bottom: 7px; font-size: 16.5px; font-weight: 600; letter-spacing: -.012em; }
  .step p { font-size: 14.5px; color: var(--text-dim); text-wrap: pretty; }
  .step kbd {
    padding: 2px 7px; border: 1px solid var(--line); border-radius: 5px;
    font: 500 12.5px/1.5 ui-monospace, monospace; color: var(--text); background: var(--ink-2);
  }

  /* ---- Tính năng ----
     Ô đầu chiếm cả hàng trên màn rộng: một lưới ba ô bằng nhau là dấu hiệu
     quen thuộc của trang dựng vội. */
  .feats { display: grid; gap: 14px; margin-top: 30px; }
  @media (min-width: 820px) {
    .feats { grid-template-columns: repeat(6, 1fr); }
    .feat { grid-column: span 2; }
    .feat:nth-child(1) { grid-column: span 6; }
    .feat:nth-child(2), .feat:nth-child(3) { grid-column: span 3; }
  }

  .feat {
    padding: 26px 24px; border: 1px solid var(--line-soft);
    border-radius: var(--r-md); background: var(--ink-1);
    transition: border-color .22s var(--ease), transform .22s var(--ease);
  }
  .feat:hover { border-color: var(--line); transform: translateY(-2px); }
  .feat h3 { margin-bottom: 8px; font-size: 16.5px; font-weight: 600; letter-spacing: -.012em; }
  .feat p { font-size: 14.5px; color: var(--text-dim); max-width: 60ch; text-wrap: pretty; }
  .feat__mark { display: block; width: 22px; height: 22px; margin-bottom: 15px; color: var(--accent); }

  /* ---- Riêng tư ---- */
  .privacy { display: grid; gap: 34px; }
  @media (min-width: 860px) { .privacy { grid-template-columns: .82fr 1.18fr; gap: 54px; } }

  .privacy__lead { margin-top: 14px; color: var(--text-dim); max-width: 40ch; text-wrap: pretty; }
  .privacy__list { list-style: none; display: grid; gap: 2px; }
  .privacy__item { padding: 19px 0; border-top: 1px solid var(--line-soft); }
  .privacy__item:last-child { border-bottom: 1px solid var(--line-soft); }
  .privacy__item strong { display: block; margin-bottom: 4px; font-weight: 600; font-size: 15.5px; }
  .privacy__item span { font-size: 14.5px; color: var(--text-dim); }

  /* ---- Tải về ---- */
  .gets { display: grid; gap: 14px; margin-top: 30px; }
  @media (min-width: 700px) { .gets { grid-template-columns: 1fr 1fr; } }

  .get {
    display: flex; flex-direction: column; gap: 6px;
    padding: 26px 24px; border: 1px solid var(--line-soft);
    border-radius: var(--r-md); background: var(--ink-1);
  }
  .get h3 { font-size: 17px; font-weight: 600; letter-spacing: -.012em; }
  .get__meta { font-size: 13.5px; color: var(--text-faint); font-variant-numeric: tabular-nums; }
  .get .btn { margin-top: 16px; align-self: flex-start; }
  .get--soon { opacity: .62; }

  /* ---- FAQ ---- */
  .faq { display: grid; gap: 0; max-width: 78ch; margin-top: 26px; }
  .qa { border-top: 1px solid var(--line-soft); }
  .qa:last-child { border-bottom: 1px solid var(--line-soft); }
  .qa summary {
    display: flex; align-items: center; justify-content: space-between; gap: 20px;
    padding: 21px 0; cursor: pointer; list-style: none;
    font-size: 16px; font-weight: 500; letter-spacing: -.012em;
  }
  .qa summary::-webkit-details-marker { display: none; }
  .qa summary::after {
    content: ''; flex: none; width: 10px; height: 10px;
    border-right: 1.5px solid var(--text-faint); border-bottom: 1.5px solid var(--text-faint);
    transform: rotate(45deg) translateY(-2px); transition: transform .22s var(--ease);
  }
  .qa[open] summary::after { transform: rotate(-135deg) translateY(-2px); }
  .qa summary:hover { color: var(--accent); }
  .qa p { padding: 0 0 22px; max-width: 66ch; color: var(--text-dim); font-size: 14.5px; text-wrap: pretty; }

  /* ---- Hiện dần khi cuộn tới ---- */
  .lift { opacity: 0; transform: translateY(14px); transition: opacity .55s var(--ease), transform .55s var(--ease); }
  .lift.seen { opacity: 1; transform: none; }

  @media (prefers-reduced-motion: reduce) {
    .lift { opacity: 1; transform: none; }
    .shot__sel, .shot__ask, .shot__caret { animation: none; }
    .shot__ask { opacity: 1; }
  }
</style>
@endpush

@section('content')
  {{-- Hero --}}
  <section class="shell hero">
    <div class="hero__grid">
      <div>
        <h1>{{ __('Snap a region. Ask right there.') }}</h1>
        <p class="hero__lead">
          {{ __('One keystroke over whatever is on screen — an error log, a spreadsheet, a form in a language you do not read. Drag a box, type your question, get an answer.') }}
        </p>

        <div class="hero__cta">
          {{--
            Nút chính đoán hệ điều hành bằng JS; nếu JS tắt hoặc đoán sai thì vẫn
            còn link bên cạnh, nên không ai bị kẹt.
          --}}
          <a href="{{ route('download.file', 'windows') }}" class="btn" id="primary-download"
             data-win="{{ route('download.file', 'windows') }}"
             data-mac="{{ route('download.file', 'mac') }}"
             data-mac-label="{{ __('Download for :platform', ['platform' => __('macOS')]) }}">
            {{ __('Download for :platform', ['platform' => __('Windows')]) }}
          </a>

          <a href="{{ $downloadUrl }}" class="hero__alt" id="secondary-download"
             data-mac-label="{{ __('Also for :platform', ['platform' => __('Windows')]) }}">
            {{ __('Also for :platform', ['platform' => __('macOS')]) }}
          </a>
        </div>

        <p class="hero__free">{{ __('Free to start. No card.') }}</p>
      </div>

      {{-- Minh hoạ thao tác: quét một vùng rồi gõ câu hỏi, lặp lại. --}}
      <div class="shot" aria-hidden="true">
        <div class="shot__bar">
          <span class="shot__dot"></span><span class="shot__dot"></span><span class="shot__dot"></span>
        </div>
        <div class="shot__body">
          <div class="shot__line"></div>
          <div class="shot__line"></div>
          <div class="shot__line"></div>
          <div class="shot__line"></div>
          <div class="shot__line"></div>
          <div class="shot__sel"></div>
          <div class="shot__ask">{{ __('Ask the AI') }}<span class="shot__caret"></span></div>
        </div>
      </div>
    </div>
  </section>

  {{-- Ba bước --}}
  <section class="shell band lift">
    {{--
      Nhãn khối là heading thật chứ không phải một thẻ <p> trông giống heading:
      ba bước bên dưới là <h3>, mà nhảy thẳng từ <h1> xuống <h3> thì trình đọc
      màn hình hiểu sai cấu trúc trang.
    --}}
    <h2 class="band__label band__label--head">{{ __('Three steps') }}</h2>

    <div class="steps">
      <div class="step">
        <h3>{{ __('Press the hotkey') }}</h3>
        <p>{!! __(':hotkey anywhere, over any window.', ['hotkey' => '<kbd>Ctrl</kbd> <kbd>Alt</kbd> <kbd>W</kbd>']) !!}</p>
      </div>
      <div class="step">
        <h3>{{ __('Drag a box') }}</h3>
        <p>{{ __('A magnifier helps you land on the edge of small text.') }}</p>
      </div>
      <div class="step">
        <h3>{{ __('Ask the AI') }}</h3>
        <p>{{ __('Type a question and press Enter. The answer lands next to the shot.') }}</p>
      </div>
    </div>
  </section>

  {{-- Tính năng --}}
  <section class="shell band lift" id="features">
    <p class="band__label">{{ __('Features') }}</p>
    <h2>{{ __('What it does') }}</h2>

    <div class="feats">
      <article class="feat">
        <svg class="feat__mark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
          <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m7 15 4-5 3 3.4L16.5 11 20 15"/>
        </svg>
        <h3>{{ __('Mark up before you ask') }}</h3>
        <p>{{ __('Box, arrow, freehand and text — burnt into the image at full resolution, so nothing goes soft on a HiDPI screen.') }}</p>
      </article>

      <article class="feat">
        <svg class="feat__mark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
          <rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>
        </svg>
        <h3>{{ __('Blur what should not travel') }}</h3>
        <p>{{ __('A blur tool for account numbers, names and addresses. Cover them before the shot leaves your machine.') }}</p>
      </article>

      <article class="feat">
        <svg class="feat__mark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
          <circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>
        </svg>
        <h3>{{ __('A magnifier for small text') }}</h3>
        <p>{{ __('Zoom and coordinates follow the cursor, so you can cut exactly at the edge of a line.') }}</p>
      </article>

      <article class="feat">
        <svg class="feat__mark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
          <circle cx="6" cy="12" r="2.6"/><circle cx="18" cy="6.5" r="2.6"/><circle cx="18" cy="17.5" r="2.6"/>
          <path d="m8.4 10.8 7.2-3.2M8.4 13.2l7.2 3.2"/>
        </svg>
        <h3>{{ __('Connect your own services') }}</h3>
        <p>{{ __('Declare an MCP service and the AI looks up real data — stock, orders, appointments — instead of guessing from the picture.') }}</p>
      </article>

      <article class="feat">
        <svg class="feat__mark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
          <circle cx="8" cy="12" r="3.4"/><path d="M11.4 12H21m-3.4 0v3.2m-2.6-3.2v2.2"/>
        </svg>
        <h3>{{ __('Bring your own key') }}</h3>
        <p>{{ __('Plug in an OpenAI, Anthropic or Qwen key and the ask limit goes away. The token bill goes straight to the provider you picked.') }}</p>
      </article>
    </div>
  </section>

  {{-- Riêng tư --}}
  <section class="shell band lift">
    <div class="privacy">
      <div>
        <p class="band__label">{{ __('On privacy') }}</p>
        <h2>{{ __('On privacy') }}</h2>
        <p class="privacy__lead">{{ __('Screenshots carry sensitive things. Three rules this app keeps:') }}</p>
      </div>

      <ul class="privacy__list">
        <li class="privacy__item">
          <strong>{{ __('Screenshots are deleted after :days days.', ['days' => config('snapask.image.retention_days')]) }}</strong>
          <span>{{ __('The text history stays; the pictures do not.') }}</span>
        </li>
        <li class="privacy__item">
          <strong>{{ __('The AI key never reaches your machine.') }}</strong>
          <span>{{ __('It is called from the server, so an unpacked app reveals nothing.') }}</span>
        </li>
        <li class="privacy__item">
          <strong>{{ __('Service tokens are encrypted at rest.') }}</strong>
          <span>{{ __('They are never handed back out through the API.') }}</span>
        </li>
      </ul>
    </div>
  </section>

  {{-- Tải về --}}
  <section class="shell band lift" id="get">
    <p class="band__label">{{ __('Download') }}</p>
    <h2>{{ __('Get SnapAsk') }}</h2>

    <div class="gets">
      @foreach (['windows' => __('Windows'), 'mac' => __('macOS')] as $platform => $label)
        @php($build = $release['builds'][$platform])
        <article @class(['get', 'get--soon' => blank($build['file'])])>
          <h3>{{ $label }}</h3>
          <p class="get__meta">
            {{ __('Version :version', ['version' => $release['version']]) }}
            @if ($build['size'] > 0)
              · {{ round($build['size'] / 1048576) }} MB
            @endif
          </p>
          <p class="get__meta">{{ __('Requires :os or later', ['os' => $build['min_os']]) }}</p>

          @if (filled($build['file']))
            <a href="{{ route('download.file', $platform) }}" class="btn">{{ __('Download') }}</a>
          @else
            <p class="get__meta" style="margin-top: 12px">{{ __('This build is not published yet. Run it from source in the meantime.') }}</p>
          @endif
        </article>
      @endforeach
    </div>
  </section>

  {{-- FAQ --}}
  <section class="shell band lift" id="faq">
    <p class="band__label">{{ __('FAQ') }}</p>
    <h2>{{ __('Questions') }}</h2>

    <div class="faq">
      <details class="qa">
        <summary>{{ __('Does it cost anything?') }}</summary>
        <p>{{ __('A new account gets a monthly ask limit on the SnapAsk key, free. Plug in your own provider key and the limit goes away — you pay that provider directly.') }}</p>
      </details>

      <details class="qa">
        <summary>{{ __('Which model answers?') }}</summary>
        <p>{{ __('Any model that reads images. The default is set on the server; you can point your account at OpenAI, Anthropic, Qwen, OpenRouter or anything OpenAI-compatible.') }}</p>
      </details>

      <details class="qa">
        <summary>{{ __('Are my screenshots kept?') }}</summary>
        <p>{{ __('They are deleted after :days days by a scheduled job. The text of the conversation stays so you can look it up later.', ['days' => config('snapask.image.retention_days')]) }}</p>
      </details>

      <details class="qa">
        <summary>{{ __('Why does Windows or macOS warn me when I open it?') }}</summary>
        <p>{{ __('The installer is not code-signed yet. On Windows choose More info → Run anyway; on macOS right-click the app and choose Open.') }}</p>
      </details>
    </div>
  </section>
@endsection

@push('scripts')
<script>
  // Nút tải chính đổi theo hệ điều hành đang dùng. Đoán sai thì link nhỏ bên
  // cạnh vẫn đưa sang bản kia, nên không cần chắc chắn tuyệt đối.
  (() => {
    const primary = document.getElementById('primary-download');
    const secondary = document.getElementById('secondary-download');
    const platform = navigator.userAgentData?.platform ?? navigator.userAgent;

    if (!/mac/i.test(platform)) return;

    primary.href = primary.dataset.mac;
    primary.textContent = primary.dataset.macLabel;
    secondary.textContent = secondary.dataset.macLabel;
  })();

  // Các khối hiện dần khi cuộn tới. Chỉ một lần, không lặp — chuyển động ở đây
  // để dẫn mắt, không phải để gây chú ý.
  const seen = (entries, observer) => entries.forEach((entry) => {
    if (!entry.isIntersecting) return;
    entry.target.classList.add('seen');
    observer.unobserve(entry.target);
  });

  const observer = new IntersectionObserver(seen, { rootMargin: '0px 0px -12% 0px' });
  document.querySelectorAll('.lift').forEach((el) => observer.observe(el));
</script>
@endpush
