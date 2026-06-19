@if ($paginator->hasPages())
<nav class="pagination-nav" role="navigation" aria-label="Pagination">
  {{-- Prev --}}
  @if ($paginator->onFirstPage())
    <span class="page-btn is-disabled" aria-disabled="true" aria-label="@lang('pagination.previous')">
      <svg viewBox="0 0 24 24" class="chev"><path d="M15 18l-6-6 6-6"/></svg>
    </span>
  @else
    <a class="page-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="@lang('pagination.previous')">
      <svg viewBox="0 0 24 24" class="chev"><path d="M15 18l-6-6 6-6"/></svg>
    </a>
  @endif

  <ul class="pages">
    <li><span class="page-num is-active" aria-current="page">{{ $paginator->currentPage() }}</span></li>
  </ul>

  {{-- Next --}}
  @if ($paginator->hasMorePages())
    <a class="page-btn" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="@lang('pagination.next')">
      <svg viewBox="0 0 24 24" class="chev"><path d="M9 6l6 6-6 6"/></svg>
    </a>
  @else
    <span class="page-btn is-disabled" aria-disabled="true" aria-label="@lang('pagination.next')">
      <svg viewBox="0 0 24 24" class="chev"><path d="M9 6l6 6-6 6"/></svg>
    </span>
  @endif
</nav>
@endif
