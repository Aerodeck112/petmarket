@if ($products->isEmpty())
    <div class="empty">
        <x-buddy kind="cat" pose="sit" :say="['Nimic aici. Am căutat și sub canapea.', 'Încearcă mai puține filtre. Miau.']" />
        <h2 class="h-sm">Niciun produs nu se potrivește</h2>
        <p>Scoate câteva filtre sau caută altceva.</p>
        <a class="btn btn-dark btn-sm" href="{{ site_url('/'.trim(request()->path(), '/').'/') }}">Șterge filtrele</a>
    </div>
@else
    <h2 class="sr-only">Produse</h2>
    <div class="grid-p">
        @foreach ($products as $p)@include('shop.partials.card', ['p' => $p])@endforeach
    </div>
    <div class="list-pages">{{ $products->onEachSide(1)->links('partials.pagination') }}</div>
@endif
