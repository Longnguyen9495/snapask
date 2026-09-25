{{--
  Phân trang của trang quản lý: trang trước/sau có nhãn đầy đủ, trang hiện tại
  mang aria-current, và đường dẫn giữ nguyên bộ lọc (withQueryString ở controller).
--}}
@if ($paginator->hasPages())
  <nav class="pagination" aria-label="{{ __('Pagination') }}">
    <p class="pagination__summary tabular">
      {{ __('Showing :from–:to of :total', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
    </p>

    <ul class="pagination__pages">
      <li>
        @if ($paginator->onFirstPage())
          <span class="pagination__link" aria-disabled="true"><x-icon name="chevron-left" :size="16" /><span class="sr-only">{{ __('Previous page') }}</span></span>
        @else
          <a class="pagination__link" href="{{ $paginator->previousPageUrl() }}" rel="prev"><x-icon name="chevron-left" :size="16" /><span class="sr-only">{{ __('Previous page') }}</span></a>
        @endif
      </li>

      @foreach ($elements as $element)
        @if (is_string($element))
          <li><span class="pagination__link" aria-disabled="true">{{ $element }}</span></li>
        @endif

        @if (is_array($element))
          @foreach ($element as $page => $url)
            <li>
              @if ($page == $paginator->currentPage())
                <span class="pagination__link tabular" aria-current="page"><span class="sr-only">{{ __('Page') }}</span> {{ $page }}</span>
              @else
                <a class="pagination__link tabular" href="{{ $url }}"><span class="sr-only">{{ __('Page') }}</span> {{ $page }}</a>
              @endif
            </li>
          @endforeach
        @endif
      @endforeach

      <li>
        @if ($paginator->hasMorePages())
          <a class="pagination__link" href="{{ $paginator->nextPageUrl() }}" rel="next"><x-icon name="chevron-right" :size="16" /><span class="sr-only">{{ __('Next page') }}</span></a>
        @else
          <span class="pagination__link" aria-disabled="true"><x-icon name="chevron-right" :size="16" /><span class="sr-only">{{ __('Next page') }}</span></span>
        @endif
      </li>
    </ul>
  </nav>
@endif
