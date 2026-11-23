@foreach ($chips as $chip)<a class="chip" href="{{ $chip['url'] }}" data-filter-link>{{ $chip['label'] }} <x-hv-icon name="close" width="14" height="14" /></a>@endforeach
@if ($chips->count() > 1)<a class="chip chip--clear" href="{{ url()->current() }}{{ request('q') ? '?q='.urlencode(request('q')) : '' }}" data-filter-link>Șterge tot</a>@endif
