@props(['title', 'eyebrow' => null, 'lead' => null])

<header class="page-head">
  <div class="page-head__text">
    @if ($eyebrow)
      <p class="eyebrow">{{ $eyebrow }}</p>
    @endif
    <h1 id="page-title" tabindex="-1">{{ $title }}</h1>
    @if ($lead)
      <p class="lead">{{ $lead }}</p>
    @endif
  </div>

  @if (isset($actions) && $actions->isNotEmpty())
    <div class="page-head__actions">{{ $actions }}</div>
  @endif
</header>
