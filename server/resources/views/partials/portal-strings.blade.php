{{-- Chữ cho portal.js, dịch sẵn theo ngôn ngữ đang xem. JSON trong thẻ script nên không chạy được như mã. --}}
@php
  $portalStrings = collect(['Are you sure?', 'Delete', 'Copy', 'Copied', 'Could not copy', 'Show', 'Hide', 'Show password', 'Hide password'])
      ->mapWithKeys(fn (string $key): array => [$key => __($key)])
      ->all();
@endphp
<script type="application/json" id="portal-strings">{!! json_encode($portalStrings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
