@if ($paginator->hasPages())
@php
    // Adresele publice au slash final (/categorie-produs/hrana/?page=2), ca linkurile să nu treacă printr-un redirect.
    $slash = fn (?string $u) => $u === null ? null : preg_replace('#^([^?]*[^/?])(\?|$)#', '$1/$2', $u, 1);
@endphp
<nav class="pager" aria-label="Pagini">
    @if (! $paginator->onFirstPage())<a class="pager-btn" href="{{ $slash($paginator->previousPageUrl()) }}" rel="prev" data-filter-link aria-label="Pagina anterioară"><x-hv-icon name="arrow" width="18" height="18" style="transform:scaleX(-1)" /></a>@endif
    @if (method_exists($paginator, 'lastPage'))
        @php $cur = $paginator->currentPage(); $last = $paginator->lastPage(); $pages = collect([1, $cur - 1, $cur, $cur + 1, $last])->filter(fn ($n) => $n >= 1 && $n <= $last)->unique()->sort()->values(); @endphp
        @foreach ($pages as $i => $n)
            @if ($i > 0 && $n - $pages[$i - 1] > 1)<span class="pager-gap">…</span>@endif
            @if ($n === $cur)<span class="pager-btn is-current" aria-current="page">{{ $n }}</span>@else<a class="pager-btn" href="{{ $slash($paginator->url($n)) }}" data-filter-link>{{ $n }}</a>@endif
        @endforeach
    @endif
    @if ($paginator->hasMorePages())<a class="pager-btn" href="{{ $slash($paginator->nextPageUrl()) }}" rel="next" data-filter-link aria-label="Pagina următoare"><x-hv-icon name="arrow" width="18" height="18" /></a>@endif
</nav>
@endif
