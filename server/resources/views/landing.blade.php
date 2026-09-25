@extends('layouts.public')

@section('title', __('SnapAsk - capture anywhere, ask right there'))
@section('description', __('Capture any part of your screen, ask a question, and get an AI answer right where you work.'))

@push('styles')
<link rel="stylesheet" href="{{ asset('css/landing.css') }}">
@endpush

@section('content')
  @php
    $platforms = ['windows' => __('Windows'), 'mac' => __('macOS')];
    $fileUrl = fn (string $platform): string => filled($release['builds'][$platform]['file'])
      ? route('download.file', $platform)
      : $downloadUrl;
  @endphp

  <section class="hero shell" aria-labelledby="hero-title">
    <div class="hero__copy">
      <h1 id="hero-title">{{ __('Capture anywhere. Ask right there.') }}</h1>
      <p class="hero__lead">{{ __('Select anything on screen. Ask a question and get an answer without breaking your flow.') }}</p>
      <div class="hero__actions">
        <a href="{{ $fileUrl('windows') }}" class="btn js-platform-download" data-win="{{ $fileUrl('windows') }}" data-mac="{{ $fileUrl('mac') }}" data-win-label="{{ __('Download for :platform', ['platform' => __('Windows')]) }}" data-mac-label="{{ __('Download for :platform', ['platform' => __('macOS')]) }}">
          {{ __('Download for :platform', ['platform' => __('Windows')]) }}
        </a>
        <a href="#try" class="btn btn--secondary">{{ __('Try it on the web') }}</a>
      </div>
    </div>

    <div class="capture-demo" data-capture-demo aria-hidden="true">
      <div class="capture-demo__chrome">
        <span>orders.ts</span>
        <button class="capture-demo__replay" type="button" data-replay tabindex="-1">{{ __('Replay') }}</button>
      </div>
      <div class="capture-demo__editor">
        <ol class="code-sample">
          <li><code><span class="code-keyword">async function</span> createOrder(input) &#123;</code></li>
          <li><code>&nbsp;&nbsp;<span class="code-keyword">const</span> customer = <span class="code-keyword">await</span> findCustomer(input.email);</code></li>
          <li class="code-sample__error"><code>&nbsp;&nbsp;<span class="code-keyword">const</span> total = input.items.reduce(sumPrice);</code></li>
          <li><code>&nbsp;&nbsp;<span class="code-keyword">return</span> db.orders.create(&#123; customer, total &#125;);</code></li>
          <li><code>&#125;</code></li>
        </ol>
        <div class="error-message"><strong>TypeError</strong><span>Cannot read properties of undefined (reading 'reduce')</span></div>
        <div class="capture-demo__shade"></div>
        <div class="capture-demo__selection"></div>
        <div class="capture-demo__cursor"></div>
        <div class="capture-demo__magnifier"><code>items.reduce</code></div>
        <div class="capture-demo__question">{{ __('Why can items be undefined here?') }}</div>
        <div class="capture-demo__answer">
          <strong>{{ __('Guard the input before reducing it.') }}</strong>
          <code>const total = (input.items ?? []).reduce(sumPrice, 0);</code>
        </div>
      </div>
    </div>
    <button class="demo-replay" type="button" data-replay aria-label="{{ __('Replay product demonstration') }}">{{ __('Replay') }}</button>
  </section>

  <section class="workflow section shell reveal" id="workflow" aria-labelledby="workflow-title">
    <header class="section-heading">
      <h2 id="workflow-title">{{ __('From screen to answer, in one motion.') }}</h2>
      <p>{{ __('Keep your context visible while SnapAsk handles the capture, question, and answer.') }}</p>
    </header>

    {{-- data-scene: ba khối minh hoạ tự diễn hoạt khi cuộn tới. --}}
    <div class="workflow-canvas" data-scene>
      <div class="workflow-canvas__rail" aria-hidden="true"></div>
      <article class="workflow-action workflow-action--keys">
        <div class="key-combo" aria-hidden="true"><kbd>Ctrl</kbd><kbd>Alt</kbd><kbd>W</kbd></div>
        <div><h3>{{ __('Press the hotkey') }}</h3><p>{{ __('Start over any app without switching windows.') }}</p></div>
      </article>
      <article class="workflow-action workflow-action--select">
        <div class="mini-selection" aria-hidden="true"><span></span></div>
        <div><h3>{{ __('Select what matters') }}</h3><p>{{ __('Drag precisely around the text, chart, or error you need.') }}</p></div>
      </article>
      <article class="workflow-action workflow-action--answer">
        <div class="mini-answer" aria-hidden="true"><span>AI</span><code>items ?? []</code></div>
        <div><h3>{{ __('Get the answer in place') }}</h3><p>{{ __('Read the response beside your capture and keep working.') }}</p></div>
      </article>
    </div>
  </section>

  <section class="try-demo section shell reveal" id="try" aria-labelledby="try-title">
    <header class="section-heading try-demo__heading">
      <h2 id="try-title">{{ __('Try it right here. No account needed.') }}</h2>
      <p>
        @if ($demoLive)
          {{ __('Pick a screen, drag around what you care about, and ask. A real AI answers — the same one the app uses.') }}
        @else
          {{ __('Pick a screen and drag around what you care about, exactly as you would in the app.') }}
        @endif
      </p>
    </header>

    {{--
      Ba màn hình cho ba kiểu người.

      Bản trước chỉ có một đoạn mã lỗi, nên ai không lập trình nhìn vào không
      thấy mình trong đó — mà phần lớn khách của SnapAsk không lập trình.
    --}}
    <div class="scene-picker" role="tablist" aria-label="{{ __('Sample screens') }}">
      @foreach ($scenes as $key => $scene)
        <button type="button" class="scene-tab" role="tab" data-scene-tab="{{ $key }}"
                id="scene-tab-{{ $key }}" aria-controls="scene-panel-{{ $key }}"
                aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}">
          {{ $scene['label'] }}
        </button>
      @endforeach
    </div>

    {{-- `data-chart-src`: thư viện vẽ biểu đồ chỉ được tải khi câu trả lời thật sự cần. --}}
    <div class="try-workspace" data-try-demo data-step="select"
         data-endpoint="{{ route('demo.ask') }}" data-live="{{ $demoLive ? '1' : '0' }}"
         data-chart-src="{{ asset('js/chart.umd.min.js') }}">
      <div class="try-workspace__bar">
        <div>
          <strong>{{ __('Interactive demo') }}</strong>
          <span data-demo-status aria-live="polite">{{ __('Drag around what you want to ask about.') }}</span>
        </div>
        <button type="button" class="try-reset" data-demo-reset>{{ __('Start over') }}</button>
      </div>

      <div class="try-workspace__body">
        <div class="try-screen" data-demo-screen tabindex="0" role="application"
             aria-label="{{ __('Sample screen. Drag to select a region, or press Enter to select the highlighted part.') }}">
          @foreach ($scenes as $key => $scene)
            <div class="try-browser" id="scene-panel-{{ $key }}" role="tabpanel"
                 aria-labelledby="scene-tab-{{ $key }}" data-scene-panel="{{ $key }}" @unless ($loop->first) hidden @endunless>
              <div class="try-browser__tabs" aria-hidden="true"><span></span><span></span><span></span><strong>{{ $scene['title'] }}</strong></div>
              <div class="try-code try-code--{{ $scene['kind'] }}" aria-hidden="true">
                @foreach ($scene['lines'] as $line)
                  <span @class(['try-code__target' => $line['target'] ?? false])>
                    @if ($line['n'] !== '')<i>{{ $line['n'] }}</i>@endif
                    <code>{{ $line['text'] }}</code>
                  </span>
                @endforeach
              </div>
            </div>
          @endforeach

          <div class="try-selection" data-demo-selection aria-hidden="true"></div>
          <div class="try-drag-hint" aria-hidden="true" data-demo-hint>{{ __('Drag here') }}</div>
        </div>

        <aside class="try-assistant" aria-label="{{ __('SnapAsk demo assistant') }}">
          <div class="try-assistant__empty" data-demo-empty>
            <span aria-hidden="true">⌁</span>
            <h3>{{ __('Select something to ask about') }}</h3>
            <p>{{ __('Drag on the sample screen just like you would after pressing the SnapAsk shortcut.') }}</p>
          </div>

          <form class="try-question" data-demo-form hidden>
            <div class="try-preview" aria-hidden="true"><span>{{ __('Captured selection') }}</span><code data-demo-preview></code></div>

            <label for="demo-question">{{ __('What do you want to know?') }}</label>
            <textarea id="demo-question" data-demo-question rows="2"
                      maxlength="{{ config('snapask.demo.max_question_length') }}"
                      placeholder="{{ __('Ask anything about what you selected…') }}"></textarea>

            {{-- Gợi ý bấm một phát: rào cản lớn nhất của bản dùng thử là không biết hỏi gì. --}}
            <div class="try-chips" data-demo-chips aria-label="{{ __('Example questions') }}"></div>

            <button class="btn" type="submit" data-demo-submit>{{ __('Ask SnapAsk') }}</button>
          </form>

          <div class="try-thinking" data-demo-thinking hidden aria-live="polite">
            <span></span><span></span><span></span>
            <p>{{ __('Reading the selected context…') }}</p>
          </div>

          <div class="try-result" data-demo-result hidden tabindex="-1">
            <span class="try-result__label" data-demo-result-label>{{ __('Answer') }}</span>
            <div class="try-answer prose" data-demo-answer></div>
            <div class="try-result__foot">
              <button type="button" class="try-again" data-demo-reset>{{ __('Ask something else') }}</button>
              <a href="{{ route('register') }}" class="btn btn--secondary try-cta" data-demo-cta hidden>{{ __('Keep going — create a free account') }}</a>
            </div>
          </div>

          <p class="try-error" data-demo-error role="alert" hidden></p>
        </aside>
      </div>
    </div>

    <p class="try-demo__note">
      @if ($demoLive)
        {{ __('This demo uses sample screens. The installed app works over any window on your machine, with markup tools, privacy controls, and your own choice of AI model.') }}
      @else
        {{ __('The installed app works over every application and adds capture markup, privacy controls, and your chosen AI model.') }}
      @endif
    </p>
  </section>

  <section class="features section shell reveal" id="features" aria-labelledby="features-title">
    <header class="section-heading">
      <p class="eyebrow">{{ __('Features') }}</p>
      <h2 id="features-title">{{ __('Precise tools before and after capture.') }}</h2>
    </header>

    {{-- data-stagger: năm thẻ hiện lần lượt; data-scene: các hình minh hoạ tự chạy. --}}
    <div class="bento" data-stagger data-scene>
      <article class="feature-card feature-card--markup">
        <div class="markup-visual" aria-hidden="true">
          <div class="markup-visual__document">{{ __('Quarterly revenue') }}<strong>$84,240</strong><span>{{ __('Review this total') }}</span></div>
          <div class="markup-visual__arrow"></div>
        </div>
        <div><h3>{{ __('Mark up before you ask') }}</h3><p>{{ __('Add boxes, arrows, freehand notes, or text at full resolution before sending.') }}</p></div>
      </article>
      <article class="feature-card feature-card--blur">
        <div class="blur-visual" aria-hidden="true"><span>ACME-4820</span><span class="blur-visual__mask"></span></div>
        <h3>{{ __('Blur sensitive data') }}</h3><p>{{ __('Hide names, addresses, and account details before the image leaves your machine.') }}</p>
      </article>
      <article class="feature-card feature-card--magnify">
        <div class="lens-visual" aria-hidden="true"><span>12px</span></div>
        <h3>{{ __('A magnifier for small text') }}</h3><p>{{ __('Place selection edges accurately on dense interfaces and HiDPI screens.') }}</p>
      </article>
      <article class="feature-card feature-card--mcp">
        {{-- "MCP" là tên giao thức, không nói gì với người dùng; thay bằng chính chữ SnapAsk. --}}
        <div class="connector-visual" aria-hidden="true"><span>SnapAsk</span><i></i><span>{{ __('Warehouse') }}</span></div>
        <h3>{{ __('Connect your own services') }}</h3><p>{{ __('Let AI query real stock, orders, or appointments instead of guessing from pixels.') }}</p>
      </article>
      <article class="feature-card feature-card--key">
        <div class="key-visual" aria-hidden="true"><span>sk</span><i></i><i></i></div>
        <h3>{{ __('Bring your own key') }}</h3><p>{{ __('Choose your provider and model, then pay that provider directly for usage.') }}</p>
      </article>
    </div>
  </section>

  <section class="privacy section reveal" id="privacy" aria-labelledby="privacy-title">
    <div class="shell privacy__layout">
      <div class="privacy__statement">
        <h2 id="privacy-title">{{ __('Your screenshots do not stay.') }}</h2>
        <p>{{ __('Sensitive screen content needs clear limits, not vague promises.') }}</p>
        <div class="privacy__retention"><strong>{{ config('snapask.image.retention_days') }}</strong><span>{{ __('days until screenshots are automatically deleted') }}</span></div>
      </div>
      <div class="privacy__commitments" data-stagger>
        <article><span aria-hidden="true">01</span><div><h3>{{ __('Screenshots are deleted after :days days.', ['days' => config('snapask.image.retention_days')]) }}</h3><p>{{ __('Conversation text remains available; the image does not.') }}</p></div></article>
        <article><span aria-hidden="true">02</span><div><h3>{{ __('Only you can open your screenshots.') }}</h3><p>{{ __('Every time an image is viewed, SnapAsk checks it belongs to the account asking for it.') }}</p></div></article>
        <article><span aria-hidden="true">03</span><div><h3>{{ __('Keys you save are never shown again.') }}</h3><p>{{ __('Provider keys and service tokens are stored locked, and no page or app can read them back.') }}</p></div></article>
      </div>
    </div>
  </section>

  <section class="download section shell reveal" id="get" aria-labelledby="download-title">
    <div class="download-panel">
      <div class="download-panel__intro">
        <p class="eyebrow">{{ __('Download') }}</p>
        <h2 id="download-title">{{ __('Put SnapAsk one shortcut away.') }}</h2>
        <p>{{ __('Choose your platform. SnapAsk will prioritize the one you are using now.') }}</p>
      </div>
      <div class="platforms" data-platforms data-stagger>
        @foreach ($platforms as $platform => $label)
          @php $build = $release['builds'][$platform]; @endphp
          <article class="platform" data-platform="{{ $platform }}">
            <div class="platform__heading">
              <h3>{{ $label }}</h3>
              <span class="platform__recommended" hidden>{{ __('Recommended') }}</span>
            </div>
            <p>{{ __('Requires :os or later', ['os' => $build['min_os']]) }}</p>
            @if ($build['size'] > 0)
              <p class="platform__meta">{{ round($build['size'] / 1048576) }} MB</p>
            @endif
            @if (filled($build['file']))
              <a href="{{ route('download.file', $platform) }}" class="btn download-action" data-download-action>{{ __('Download for :platform', ['platform' => $label]) }}</a>
              <p class="download-error" role="status" hidden>{{ __('The download could not start. Please try again.') }}</p>
            @else
              <button class="btn" type="button" disabled>{{ __('Not available yet') }}</button>
              <p class="platform__status">{{ __('This build is not published yet.') }}</p>
            @endif
          </article>
        @endforeach
      </div>
    </div>
  </section>

  <section class="faq-section section shell reveal" id="faq" aria-labelledby="faq-title">
    <header class="section-heading"><h2 id="faq-title">{{ __('Questions, answered clearly.') }}</h2></header>
    <div class="faq" data-accordion>
      <details class="qa">
        <summary>{{ __('Does it cost anything?') }}</summary>
        <div class="qa__body"><p>{{ __('A new account includes a free monthly ask allowance. Use your own provider key to remove that limit and pay the provider directly.') }}</p></div>
      </details>
      <details class="qa">
        <summary>{{ __('Which model answers?') }}</summary>
        <div class="qa__body"><p>{{ __('Any vision-capable model. You can use the server default or connect OpenAI, Anthropic, Qwen, OpenRouter, and compatible providers.') }}</p></div>
      </details>
      <details class="qa">
        <summary>{{ __('Are my screenshots kept?') }}</summary>
        <div class="qa__body"><p>{{ __('Screenshots are deleted automatically after :days days. Conversation text remains available in your history.', ['days' => config('snapask.image.retention_days')]) }}</p></div>
      </details>
      <details class="qa">
        <summary>{{ __('Windows or macOS shows a warning. What do I do?') }}</summary>
        <div class="qa__body"><p>{{ __('That warning appears for every app the system has not seen before. On Windows choose More info, then Run anyway. On macOS right-click SnapAsk and choose Open.') }}</p></div>
      </details>
    </div>
  </section>
@endsection

@push('scripts')
{{--
  Dữ liệu cho bản dùng thử: gợi ý câu hỏi, nhãn vùng chọn và chữ trạng thái,
  đã dịch sẵn theo ngôn ngữ đang xem.

  Đặt trong <script type="application/json"> nên trình duyệt đọc như dữ liệu chứ
  không chạy như mã, và các cờ JSON_HEX_* chặn mọi ký tự có thể đóng thẻ sớm.
--}}
@php
  $demoData = [
      'scenes' => collect($scenes)->map(fn (array $scene): array => [
          'selection' => $scene['selection'],
          'hint' => $scene['hint'],
          'questions' => $scene['questions'],
          'note' => $scene['note'],
      ])->all(),
      'strings' => collect([
          'Drag around what you want to ask about.',
          'Type a question, or pick one below.',
          'Reading the selected context…',
          'Answer',
          'Sample answer',
          'Type a question first.',
          'Something went wrong. Please try again.',
          'Ask SnapAsk',
          'Asking…',
      ])->mapWithKeys(fn (string $key): array => [$key => __($key)])->all(),
  ];
@endphp
<script type="application/json" id="demo-data">{!! json_encode($demoData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script src="{{ asset('js/landing.js') }}" defer></script>
<script src="{{ asset('js/demo.js') }}" defer></script>
@endpush
