{{-- Cardul de produs din liste: imagine, etichete, brand, nume, rating, pret, adaugare rapida in cos. --}}
@php
    /** @var \App\Models\Product $p */
    $img = $p->mainImage?->thumbUrl();
    $variable = $p->isVariable();
    $percent = $variable ? null : $p->discountPercent();
    $out = $p->stock_status->value === 'outofstock';
@endphp
<article class="card-p {{ $out ? 'is-out' : '' }}">
    <a class="card-p-media" href="{{ site_url($p->path()) }}" tabindex="-1" aria-hidden="true">
        @if ($img)<img src="{{ $img }}" alt="" loading="lazy" decoding="async" width="400" height="400">@else<span class="card-p-noimg">@include('partials.mascot-dog')</span>@endif
    </a>
    <div class="card-p-badges">
        @if ($percent)<span class="sticker sticker--sale">-{{ $percent }}%</span>@elseif ($p->on_sale)<span class="sticker sticker--sale">Reducere</span>@endif
        @if ($p->isNew())<span class="sticker sticker--new">Nou</span>@endif
        @if ($p->badge)<span class="sticker sticker--custom">{{ $p->badge }}</span>@endif
        @if ($out)<span class="sticker sticker--out">Stoc epuizat</span>@endif
        @if (! $p->sold_online)<span class="sticker sticker--rx">Cu rețetă</span>@endif
    </div>
    <button type="button" class="card-p-fav" @click="toggleFav({{ $p->id }}, $el)" aria-label="Adaugă la favorite: {{ $p->name }}" aria-pressed="false"><x-hv-icon name="heart-o" width="20" height="20" /></button>
    <div class="card-p-body">
        @if ($p->brand)<p class="card-p-brand">{{ $p->brand->name }}</p>@endif
        <h3 class="card-p-title"><a href="{{ site_url($p->path()) }}">{{ $p->name }}</a></h3>
        @if ($p->rating_count > 0)
            <p class="stars" aria-label="Notă {{ number_format($p->rating_avg, 1, ',', '') }} din 5, {{ $p->rating_count }} recenzii"><span style="--r: {{ $p->rating_avg }}"></span><small>({{ $p->rating_count }})</small></p>
        @endif
        <div class="card-p-foot">
            <p class="price">
                @if ($p->price === null)
                    <span class="price-now price-none">Preț la cerere</span>
                @else
                    @if ($variable && $p->price_max > $p->price)<small>de la</small>@endif
                    <span class="price-now {{ $p->on_sale ? 'is-sale' : '' }}">{{ money($p->price) }}</span>
                    @if (! $variable && $p->on_sale)<s class="price-old">{{ money($p->regular_price) }}</s>@endif
                @endif
            </p>
            @if (! $p->sold_online)
                <a class="add-btn add-btn--info" href="{{ site_url($p->path()) }}" aria-label="Detalii: {{ $p->name }}"><x-hv-icon name="phone" width="18" height="18" /><span>Rețetă</span></a>
            @elseif (! $out && $p->price !== null)
                @if ($variable)
                    <a class="add-btn" href="{{ site_url($p->path()) }}" aria-label="Alege varianta: {{ $p->name }}"><x-hv-icon name="chevron" width="20" height="20" /><span>Alege</span></a>
                @else
                    <button class="add-btn" type="button" @click="addToCart({{ $p->id }}, null, 1, $el)" aria-label="Adaugă în coș: {{ $p->name }}"><x-hv-icon name="cart" width="20" height="20" /><span>Adaugă</span></button>
                @endif
            @endif
        </div>
    </div>
</article>
