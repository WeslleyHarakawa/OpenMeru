{{-- Translated pager for the accounts list (links are loaded in place by the page script). --}}
@php($last = $page->lastPage())
@php($current = $page->currentPage())
<nav class="d-flex align-items-center justify-content-between flex-wrap gap-2" aria-label="{{ __('admin.accounts') }}">
  <span class="hint">{{ __('admin.showing', ['from' => $page->firstItem(), 'to' => $page->lastItem(), 'total' => $page->total()]) }}</span>
  <ul class="pagination pagination-sm mb-0 flex-wrap">
    <li class="page-item @if ($page->onFirstPage()) disabled @endif">
      <a class="page-link" href="{{ $page->previousPageUrl() ?? '#' }}">‹ {{ __('admin.previous') }}</a>
    </li>
    @foreach (range(1, $last) as $n)
      @if ($n === 1 || $n === $last || abs($n - $current) <= 1)
        <li class="page-item @if ($n === $current) active @endif"><a class="page-link" href="{{ $page->url($n) }}">{{ $n }}</a></li>
      @elseif ($n === 2 || $n === $last - 1)
        <li class="page-item disabled"><span class="page-link">…</span></li>
      @endif
    @endforeach
    <li class="page-item @if (! $page->hasMorePages()) disabled @endif">
      <a class="page-link" href="{{ $page->nextPageUrl() ?? '#' }}">{{ __('admin.next') }} ›</a>
    </li>
  </ul>
</nav>
