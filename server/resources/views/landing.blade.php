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
      <h2 id="try-title">{{ __('Try the workflow yourself.') }}</h2>
      <p>{{ __('This private browser demo uses a sample screen. Nothing is uploaded and no account is required.') }}</p>
    </header>

    <div class="try-workspace" data-try-demo data-step="select">
      <div class="try-workspace__bar">
        <div>
          <strong>{{ __('Interactive demo') }}</strong>
          <span data-demo-status aria-live="polite">{{ __('Drag around the error message below.') }}</span>
        </div>
        <button type="button" class="try-reset" data-demo-reset>{{ __('Start over') }}</button>
      </div>

      <div class="try-workspace__body">
        <div class="try-screen" data-demo-screen tabindex="0" role="application" aria-label="{{ __('Sample screen. Drag to select the error message, or press Enter to select it with the keyboard.') }}">
          <div class="try-browser">
            <div class="try-browser__tabs" aria-hidden="true"><span></span><span></span><span></span><strong>orders.ts</strong></div>
            <div class="try-code" aria-hidden="true">
              <span><i>12</i><code>const customer = await findCustomer(input.email);</code></span>
              <span class="try-code__target"><i>13</i><code>const total = input.items.reduce(sumPrice);</code></span>
              <span><i>14</i><code>return db.orders.create(&#123; customer, total &#125;);</code></span>
              <p><b>TypeError</b> Cannot read properties of undefined (reading 'reduce')</p>
            </div>
          </div>
          <div class="try-selection" data-demo-selection aria-hidden="true"></div>
          <div class="try-drag-hint" aria-hidden="true">{{ __('Drag here') }}</div>
        </div>

        <aside class="try-assistant" aria-label="{{ __('SnapAsk demo assistant') }}">
          <div class="try-assistant__empty" data-demo-empty>
            <span aria-hidden="true">⌁</span>
            <h3>{{ __('Select something to ask about') }}</h3>
            <p>{{ __('Drag on the sample screen just like you would after pressing the SnapAsk shortcut.') }}</p>
          </div>

          <form class="try-question" data-demo-form hidden>
            <div class="try-preview" aria-hidden="true"><span>{{ __('Captured selection') }}</span><code>input.items.reduce(sumPrice)</code></div>
            <label for="demo-question">{{ __('What do you want to know?') }}</label>
            <textarea id="demo-question" data-demo-question rows="3" maxlength="180" placeholder="{{ __('Why can items be undefined here?') }}">{{ __('Why can items be undefined here?') }}</textarea>
            <button class="btn" type="submit" data-demo-submit>{{ __('Ask SnapAsk') }}</button>
          </form>

          <div class="try-thinking" data-demo-thinking hidden aria-live="polite">
            <span></span><span></span><span></span>
            <p>{{ __('Reading the selected context…') }}</p>
          </div>

          <div
            class="try-result"
            data-demo-result
            data-meaning-title="{{ __('This error means the code cannot call reduce on an undefined value.') }}"
            data-meaning-body="{{ __('In Vietnamese: input.items has no value, so JavaScript cannot run the reduce function on it.') }}"
            data-cause-title="{{ __('The items field is missing from the input.') }}"
            data-cause-body="{{ __('The caller may not have supplied items, or the data has not finished loading when this line runs.') }}"
            data-fix-title="{{ __('Guard the input before reducing it.') }}"
            data-fix-body="{{ __('Give items an empty-array fallback, then provide zero as the initial total for reduce.') }}"
            data-general-title="{{ __('The selected line fails because items is undefined.') }}"
            data-general-body="{{ __('Check where input is created, make sure items is an array, and add a safe fallback before reduce.') }}"
            hidden
            tabindex="-1"
          >
            <span class="try-result__label">{{ __('Sample answer') }}</span>
            <h3 data-demo-answer-title>{{ __('Guard the input before reducing it.') }}</h3>
            <p data-demo-answer-body>{{ __('Give items an empty-array fallback, then provide zero as the initial total for reduce.') }}</p>
            <code data-demo-answer-code>const total = (input.items ?? []).reduce(sumPrice, 0);</code>
            <button type="button" class="try-again" data-demo-reset>{{ __('Try another question') }}</button>
          </div>
        </aside>
      </div>
    </div>
    <p class="try-demo__note">{{ __('The installed app works over every application and adds capture markup, privacy controls, and your chosen AI model.') }}</p>
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
        <div class="connector-visual" aria-hidden="true"><span>MCP</span><i></i><span>{{ __('Warehouse') }}</span></div>
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
        <article><span aria-hidden="true">02</span><div><h3>{{ __('The AI key never reaches your machine.') }}</h3><p>{{ __('Provider requests run on the server, away from the unpackable desktop app.') }}</p></div></article>
        <article><span aria-hidden="true">03</span><div><h3>{{ __('Service tokens are encrypted at rest.') }}</h3><p>{{ __('Stored tokens are encrypted and never returned through the API.') }}</p></div></article>
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
        <summary>{{ __('Why does Windows or macOS warn me when I open it?') }}</summary>
        <div class="qa__body"><p>{{ __('The installer is not code-signed yet. On Windows choose More info, then Run anyway. On macOS, right-click the app and choose Open.') }}</p></div>
      </details>
    </div>
  </section>
@endsection

@push('scripts')
<script src="{{ asset('js/landing.js') }}" defer></script>
@endpush
