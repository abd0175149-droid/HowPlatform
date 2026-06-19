{{-- resources/views/vendor/pagination/how.blade.php --}}
@if ($paginator->hasPages())
<nav class="pagination-nav how" role="navigation" aria-label="@lang('pagination.label')" dir="rtl">
  {{-- Previous --}}
  @if ($paginator->onFirstPage())
    <span class="page-btn is-disabled" aria-disabled="true" aria-label="@lang('pagination.previous')">
       <svg viewBox="0 0 24 24" class="chev"><path d="M9 6l6 6-6 6"/></svg>
    </span>
  @else
    <a class="page-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="@lang('pagination.previous')">
        <svg viewBox="0 0 24 24" class="chev"><path d="M9 6l6 6-6 6"/></svg>
    </a>
  @endif

  {{-- Numbers --}}
  <ul class="pages">
    @foreach ($elements as $element)
      {{-- Dots --}}
      @if (is_string($element))
        <li class="dots" aria-hidden="true">…</li>
      @endif

      {{-- Array of links --}}
      @if (is_array($element))
        @foreach ($element as $page => $url)
          @if ($page == $paginator->currentPage())
            <li><span class="page-num is-active" aria-current="page">{{ $page }}</span></li>
          @else
            <li><a class="page-num" href="{{ $url }}" aria-label="@lang('pagination.go_to_page', ['page' => $page])">{{ $page }}</a></li>
          @endif
        @endforeach
      @endif
    @endforeach
  </ul>

  {{-- Next --}}
  @if ($paginator->hasMorePages())
    <a class="page-btn" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="@lang('pagination.next')">
        <svg viewBox="0 0 24 24" class="chev"><path d="M15 18l-6-6 6-6"/></svg>
    </a>
  @else
    <span class="page-btn is-disabled" aria-disabled="true" aria-label="@lang('pagination.next')">
        <svg viewBox="0 0 24 24" class="chev"><path d="M15 18l-6-6 6-6"/></svg>
    </span>
  @endif
</nav>
@endif
