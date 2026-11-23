{{-- Sir orizontal de produse (prima pagina, produse similare). --}}
@if ($products->isNotEmpty())
<section class="rail-sec {{ $class ?? '' }}" aria-labelledby="{{ $id }}">
    <div class="wrap">
        <div class="section-head">
            <div>@isset($kicker)<p class="script">{{ $kicker }}</p>@endisset<h2 id="{{ $id }}" class="display h-md">{{ $title }}</h2></div>
            @isset($more)<a class="btn btn-ghost btn-sm" href="{{ $more }}">Vezi toate <x-hv-icon name="arrow" width="18" height="18" /></a>@endisset
        </div>
        <div class="rail" x-data="rail">
            <button type="button" class="rail-arrow rail-arrow--prev" @click="go(-1)" :disabled="atStart" aria-label="Înapoi"><x-hv-icon name="arrow" width="20" height="20" style="transform:scaleX(-1)" /></button>
            <div class="rail-track" x-ref="track" @scroll.debounce.60ms="update()">
                @foreach ($products as $p)<div class="rail-item">@include('shop.partials.card', ['p' => $p])</div>@endforeach
            </div>
            <button type="button" class="rail-arrow rail-arrow--next" @click="go(1)" :disabled="atEnd" aria-label="Înainte"><x-hv-icon name="arrow" width="20" height="20" /></button>
        </div>
    </div>
</section>
@endif
