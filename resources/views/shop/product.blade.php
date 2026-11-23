@extends('layouts.site')
@section('content')
@php
    $images = $product->images->map(fn ($i) => ['src' => $i->url(), 'alt' => $i->alt ?: $product->name])->filter(fn ($i) => $i['src'])->values();
    $percent = $product->isVariable() ? null : $product->discountPercent();
    $freeFrom = (int) \App\Support\Settings::get('commerce.free_shipping_threshold');
@endphp
<div class="wrap pdp" x-data="productPage({{ json_encode([
    'id' => $product->id, 'variable' => $product->isVariable(), 'variants' => $variants, 'attrs' => $variationAttributes,
    'images' => $images, 'price' => $product->price, 'regular' => $product->regular_price, 'onSale' => (bool) $product->on_sale,
    'stock' => $product->stock_status->value, 'qty' => $product->manage_stock ? $product->stock_qty : null, 'name' => $product->name,
    'sku' => $product->sku, 'brand' => $product->brand?->name, 'category' => $category?->name, 'lowest30' => $lowest30,
    'min' => $product->min_qty, 'max' => $product->max_qty, 'low' => (int) \App\Support\Settings::get('commerce.low_stock_threshold'),
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) }})">
    <nav aria-label="Ești aici"><ol class="crumbs">
        @foreach ($seo->breadcrumbs as $crumb)<li>@if (! $loop->last)<a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a>@else<span aria-current="page">{{ $crumb['name'] }}</span>@endif</li>@endforeach
    </ol></nav>
    @if ($preview)<p class="notice notice--warn">Previzualizare: produsul nu este publicat.</p>@endif

    <div class="pdp-grid">
        <div class="gallery">
            <div class="gallery-main">
                <div class="card-p-badges">
                    @if ($percent)<span class="sticker sticker--sale">-{{ $percent }}%</span>@elseif ($product->on_sale)<span class="sticker sticker--sale">Reducere</span>@endif
                    @if ($product->isNew())<span class="sticker sticker--new">Nou</span>@endif
                    @if ($product->badge)<span class="sticker sticker--custom">{{ $product->badge }}</span>@endif
                </div>
                @if ($images->isNotEmpty())
                    <button type="button" class="gallery-zoom" @click="zoom = true" aria-label="Mărește imaginea">
                        <img :src="current.src" src="{{ $images[0]['src'] }}" :alt="current.alt" alt="{{ $images[0]['alt'] }}" width="800" height="800" fetchpriority="high">
                    </button>
                @else
                    <div class="gallery-empty">@include('partials.mascot-dog')<p>Fotografia vine curând.</p></div>
                @endif
            </div>
            @if ($images->count() > 1)
                <ul class="thumbs">
                    @foreach ($images as $i => $img)
                        <li><button type="button" @click="show({{ $i }})" :aria-current="(index === {{ $i }}).toString()" aria-label="Imaginea {{ $i + 1 }}"><img src="{{ $img['src'] }}" alt="" loading="lazy" width="96" height="96"></button></li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="buybox">
            @if ($product->brand)<a class="pdp-brand" href="{{ site_url($product->brand->path()) }}">{{ $product->brand->name }}</a>@endif
            <h1 class="pdp-title">{{ $product->name }}</h1>
            <p class="pdp-meta">
                @if ($product->rating_count)<a href="#recenzii" class="stars"><span style="--r: {{ $product->rating_avg }}"></span><small>{{ number_format($product->rating_avg, 1, ',', '') }} · {{ $product->rating_count }} recenzii</small></a>@endif
                <span x-show="sku" x-cloak>Cod: <b x-text="sku"></b></span>
            </p>

            <div class="pdp-price">
                <template x-if="price !== null">
                    <p class="price price--lg">
                        <small x-show="variable && !variant && priceMax > price" x-cloak>de la</small>
                        <span class="price-now" :class="{ 'is-sale': onSale }" x-text="fmt(price)">{{ money($product->price) }}</span>
                        <s class="price-old" x-show="onSale && regular" x-text="fmt(regular)" x-cloak></s>
                        <span class="sticker sticker--sale" x-show="onSale && regular" x-text="'-' + Math.round(100 - price * 100 / regular) + '%'" x-cloak></span>
                    </p>
                </template>
                <p class="omnibus" x-show="onSale && lowest30" x-cloak>Cel mai mic preț din ultimele 30 de zile: <b x-text="fmt(lowest30)"></b></p>
            </div>

            @if ($product->short_description)<div class="pdp-short prose">{!! \App\Support\Html::clean($product->short_description) !!}</div>@endif

            @if (! $product->sold_online)
                <div class="rx-box">
                    <h2 class="h-sm"><x-hv-icon name="stethoscope" width="22" height="22" /> Produs care nu se vinde online</h2>
                    <p>{{ \App\Support\Settings::get('commerce.not_online_notice') }}</p>
                    <p class="rx-actions"><a class="btn btn-primary btn-sm" href="tel:{{ preg_replace('/[^0-9+]/', '', $shop['phone']) }}" data-track="phone_click"><x-hv-icon name="phone" width="18" height="18" /> {{ $shop['phone'] }}</a>
                        <a class="btn btn-ghost btn-sm" href="mailto:{{ $shop['email'] }}?subject={{ rawurlencode('Despre '.$product->name) }}">{{ $shop['email'] }}</a></p>
                </div>
            @else
            <form class="pdp-form" @submit.prevent="add()">
                @foreach ($variationAttributes as $attr)
                    <fieldset class="opt">
                        <legend>{{ $attr['name'] }}: <b x-text="labelFor('{{ $attr['slug'] }}')"></b></legend>
                        <div class="opt-list">
                            @foreach ($attr['values'] as $val)
                                <button type="button" class="opt-btn {{ $attr['type'] === 'color' ? 'opt-btn--color' : '' }}" @click="pick('{{ $attr['slug'] }}', '{{ $val['slug'] }}')"
                                        :aria-pressed="(sel['{{ $attr['slug'] }}'] === '{{ $val['slug'] }}').toString()" :disabled="!possible('{{ $attr['slug'] }}', '{{ $val['slug'] }}')"
                                        @if ($val['color']) style="--sw: {{ $val['color'] }}" @endif>{{ $val['name'] }}</button>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                <p class="stock" :class="'stock--' + stock" aria-live="polite">
                    <i></i><span x-text="stockText()">{{ $product->stock_status->label() }}</span>
                </p>

                <div class="pdp-buy">
                    <div class="qty" role="group" aria-label="Cantitate">
                        <button type="button" @click="qty = Math.max(min, qty - 1)" aria-label="Scade cantitatea"><x-hv-icon name="minus" width="18" height="18" /></button>
                        <input type="number" x-model.number="qty" :min="min" :max="maxQty()" inputmode="numeric" aria-label="Cantitate">
                        <button type="button" @click="qty = Math.min(maxQty(), qty + 1)" aria-label="Crește cantitatea"><x-hv-icon name="plus" width="18" height="18" /></button>
                    </div>
                    <button class="btn btn-primary btn-buy" type="submit" :disabled="!canBuy() || busy">
                        <x-hv-icon name="cart" width="22" height="22" />
                        <span x-text="variable && !variant ? 'Alege o variantă' : (stock === 'outofstock' ? 'Stoc epuizat' : 'Adaugă în coș')">Adaugă în coș</span>
                    </button>
                    <button type="button" class="icon-btn icon-btn--lg" @click="toggleFav({{ $product->id }}, $el)" aria-label="Adaugă la favorite" aria-pressed="false"><x-hv-icon name="heart-o" width="22" height="22" /></button>
                </div>
            </form>
            @endif

            <ul class="pdp-perks">
                <li><x-hv-icon name="truck" /><span>@if ($freeFrom)<b>Livrare gratuită</b> de la {{ money($freeFrom) }}@else<b>Livrare</b> în toată țara @endif · 1–3 zile lucrătoare</span></li>
                <li><x-hv-icon name="return" /><span><b>Retur 14 zile</b> pentru produsele nedesigilate</span></li>
                <li><x-hv-icon name="stethoscope" /><span><b>Întreabă un medic HiKeVet</b> ce i se potrivește: <a href="tel:{{ preg_replace('/[^0-9+]/', '', $shop['phone']) }}" data-track="phone_click">{{ $shop['phone'] }}</a></span></li>
            </ul>
            <x-buddy class="pdp-buddy" :kind="str_contains(mb_strtolower($category?->path ?? ''), 'pisic') ? 'cat' : 'dog'" pose="peek"
                     :say="str_contains(mb_strtolower($category?->path ?? ''), 'pisic') ? ['Miau! Ăsta e pentru mine?', 'Eu sunt Raza. Aprob din priviri.', 'Pune două. Una e pentru mine.'] : ['Ham! Ăsta miroase a recompensă.', 'Eu sunt Hiko. Îl vreau. Acum.', 'Adaugă-l în coș, eu păzesc ușa.']" />
        </div>
    </div>

    <div class="pdp-tabs" x-data="{ tab: '{{ $product->description ? 'desc' : ($specs->isNotEmpty() ? 'specs' : 'reviews') }}' }">
        <div class="tabs" role="tablist">
            @if ($product->description)<button type="button" role="tab" :aria-selected="(tab === 'desc').toString()" @click="tab = 'desc'">Descriere</button>@endif
            @if ($specs->isNotEmpty())<button type="button" role="tab" :aria-selected="(tab === 'specs').toString()" @click="tab = 'specs'">Specificații</button>@endif
            <button type="button" role="tab" :aria-selected="(tab === 'reviews').toString()" @click="tab = 'reviews'" id="recenzii">Recenzii ({{ $product->rating_count }})</button>
            <button type="button" role="tab" :aria-selected="(tab === 'ship').toString()" @click="tab = 'ship'">Livrare și retur</button>
        </div>
        @if ($product->description)<div class="tab-panel prose" role="tabpanel" x-show="tab === 'desc'">{!! \App\Support\Html::clean($product->description) !!}</div>@endif
        @if ($specs->isNotEmpty())
            <div class="tab-panel" role="tabpanel" x-show="tab === 'specs'" x-cloak>
                <table class="specs">@foreach ($specs as $s)<tr><th>{{ $s['name'] }}</th><td>{{ $s['value'] }}</td></tr>@endforeach
                    @if ($product->brand)<tr><th>Brand</th><td><a href="{{ site_url($product->brand->path()) }}">{{ $product->brand->name }}</a></td></tr>@endif
                    @if ($product->weight_grams)<tr><th>Greutate colet</th><td>{{ rtrim(rtrim(number_format($product->weight_grams / 1000, 2, ',', ''), '0'), ',') }} kg</td></tr>@endif
                </table>
            </div>
        @endif
        <div class="tab-panel" role="tabpanel" x-show="tab === 'reviews'" x-cloak>
            @if (session('review_sent'))<p class="notice notice--ok">Mulțumim! Recenzia apare după ce o verifică echipa.</p>@endif
            @forelse ($reviews as $r)
                <article class="review">
                    <p class="stars"><span style="--r: {{ $r->rating }}"></span></p>
                    <p class="review-who"><b>{{ $r->author_name }}</b> · {{ $r->created_at->format('d.m.Y') }} @if ($r->verified_owner)<span class="badge badge--ok">a cumpărat produsul</span>@endif</p>
                    <p>{{ $r->body }}</p>
                    @if ($r->reply)<p class="review-reply"><b>Răspuns Pet Market:</b> {{ $r->reply }}</p>@endif
                </article>
            @empty
                <p>Încă nu există recenzii. Fii primul care spune ce crede animalul tău!</p>
            @endforelse
            @if ($canReview)
                <form class="review-form card" method="post" action="{{ site_url($product->path()) }}recenzie">
                    @csrf
                    <h3 class="h-sm">Scrie o recenzie</h3>
                    <fieldset class="rate" x-data="{ r: 5 }"><legend>Nota ta</legend>
                        @for ($i = 1; $i <= 5; $i++)<label><input type="radio" name="rating" value="{{ $i }}" x-model.number="r" @checked($i === 5)><span :class="{ on: r >= {{ $i }} }"><x-hv-icon name="star" width="26" height="26" /></span><b class="sr-only">{{ $i }} stele</b></label>@endfor
                    </fieldset>
                    <div class="field"><label for="rbody">Părerea ta</label><textarea id="rbody" name="body" required minlength="10" maxlength="3000"></textarea>@error('body')<p class="error">{{ $message }}</p>@enderror</div>
                    <button class="btn btn-dark btn-sm" type="submit">Trimite recenzia</button>
                </form>
            @elseif (! auth()->check())
                <p><a href="{{ site_url('/contul-meu/autentificare/') }}">Intră în cont</a> ca să lași o recenzie.</p>
            @endif
        </div>
        <div class="tab-panel prose" role="tabpanel" x-show="tab === 'ship'" x-cloak>
            <p>Comenzile plasate în zilele lucrătoare până la ora 14:00 pleacă de regulă în aceeași zi. Livrarea prin curier durează 1–3 zile lucrătoare.@if ($freeFrom) Livrarea este gratuită pentru comenzile de peste {{ money($freeFrom) }}.@endif</p>
            <p>Poți returna produsele nedesigilate în 14 zile de la primire. Detalii în <a href="{{ site_url('/politica-de-retur/') }}">politica de retur</a> și <a href="{{ site_url('/livrare/') }}">pagina de livrare</a>.</p>
        </div>
    </div>

    <div class="zoom" x-show="zoom" x-cloak x-transition.opacity @click="zoom = false" role="dialog" aria-modal="true" aria-label="Imagine mărită">
        <img :src="current.src" :alt="current.alt">
        <button type="button" class="icon-btn" aria-label="Închide"><x-hv-icon name="close" width="22" height="22" /></button>
    </div>
</div>

@include('shop.partials.rail', ['products' => $upsells, 'title' => 'Ți-ar mai plăcea', 'id' => 'upsell', 'kicker' => 'Recomandarea lui Hiko'])
@include('shop.partials.rail', ['products' => $related, 'title' => 'Produse similare', 'id' => 'similare', 'more' => $category ? site_url($category->path()) : null])
@endsection
