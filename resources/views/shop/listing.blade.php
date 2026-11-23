@extends('layouts.site')
@section('content')
<header class="list-head">
    <div class="wrap">
        <nav aria-label="Ești aici"><ol class="crumbs">
            @foreach ($seo->breadcrumbs as $crumb)<li>@if (! $loop->last)<a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a>@else<span aria-current="page">{{ $crumb['name'] }}</span>@endif</li>@endforeach
        </ol></nav>
        <div @class(['list-hero', 'list-hero--brand' => isset($brand)])>
            <div>
                <h1 class="display h-lg">{{ $title }}</h1>
                <p class="list-count">{{ $products->total() }} {{ $products->total() === 1 ? 'produs' : 'produse' }}</p>
                @if ($category?->description)<div class="list-desc prose">{!! \App\Support\Html::clean(\Illuminate\Support\Str::limit(strip_tags($category->description, '<p><strong><em><a><br>'), 420)) !!}</div>@endif
                @if (isset($brand) && $brand->description)<div class="list-desc prose">{!! \App\Support\Html::clean($brand->description) !!}</div>@endif
            </div>
            @if (isset($brand) && $brand->logo)<img class="brand-logo-lg" src="{{ asset($brand->logo) }}" alt="{{ $brand->name }}">@else
                <x-buddy class="list-buddy" :kind="crc32($title) % 2 ? 'cat' : 'dog'" :pose="crc32($title) % 2 ? 'sit' : 'peek'"
                         :say="crc32($title) % 2 ? ['Eu aș alege ce are pește. Miau.', 'Filtrează după brand, eu filtrez după miros.', 'Eu sunt Raza. Am testat tot raftul ăsta.'] : ['Ham! Aici e raftul meu preferat.', 'Sortează după „cele mai vândute”, nu greșești.', 'Eu sunt Hiko. Adulmec reducerile.']" />
            @endif
        </div>
        @if (! empty($subcategories) && $subcategories->isNotEmpty())
            <ul class="subcats">
                @foreach ($subcategories as $sub)<li><a href="{{ site_url($sub->path()) }}">@if ($sub->image)<img src="{{ asset($sub->image) }}" alt="" loading="lazy">@endif<span>{{ $sub->name }}</span></a></li>@endforeach
            </ul>
        @endif
        @if (! empty($categories))
            <ul class="subcats">
                @foreach ($categories as $sub)<li><a href="{{ site_url($sub->path()) }}">@if ($sub->image)<img src="{{ asset($sub->image) }}" alt="" loading="lazy">@endif<span>{{ $sub->name }}</span></a></li>@endforeach
            </ul>
        @endif
    </div>
</header>

<div class="wrap list-layout" x-data="filters" @popstate.window="reload(location.href, false)">
    <aside class="filters" :class="{ 'is-open': open }" aria-label="Filtre">
        <div class="filters-panel">
            <div class="filters-top"><strong>Filtre</strong><button type="button" class="icon-btn filters-close" @click="open = false" aria-label="Închide filtrele"><x-hv-icon name="close" width="20" height="20" /></button></div>
            <form x-ref="form" method="get" @change="apply()" @submit.prevent="apply()">
                @if (request('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif
                <input type="hidden" name="orderby" value="{{ request('orderby') }}">
                <fieldset class="facet">
                    <legend>Disponibilitate</legend>
                    <label class="check-row"><input type="checkbox" name="stock_status" value="instock" @checked($facets['selected']['stock'])> În stoc</label>
                    @if ($facets['sale_count'] > 0 || $facets['selected']['sale'])<label class="check-row"><input type="checkbox" name="on_sale" value="1" @checked($facets['selected']['sale'])> La reducere <small>{{ $facets['sale_count'] }}</small></label>@endif
                </fieldset>
                @if ($facets['price']['max'] > 0)
                <fieldset class="facet">
                    <legend>Preț (lei)</legend>
                    <div class="price-range">
                        <input type="number" name="min_price" inputmode="numeric" min="0" placeholder="{{ $facets['price']['min'] }}" value="{{ request('min_price') }}" aria-label="Preț minim">
                        <span>–</span>
                        <input type="number" name="max_price" inputmode="numeric" min="0" placeholder="{{ $facets['price']['max'] }}" value="{{ request('max_price') }}" aria-label="Preț maxim">
                    </div>
                </fieldset>
                @endif
                @if ($facets['brands']->count() > 1)
                <fieldset class="facet" x-data="{ more: false }">
                    <legend>Brand</legend>
                    <input type="hidden" name="filter_brand" x-ref="brands" value="{{ implode(',', $facets['selected']['brands']) }}">
                    @foreach ($facets['brands'] as $b)
                        <label class="check-row" @if ($loop->index >= 8) x-show="more" @endif><input type="checkbox" data-group="brands" value="{{ $b->id }}" @checked(in_array($b->id, $facets['selected']['brands'], true)) @change.stop="group($event, 'brands')"> {{ $b->name }} <small>{{ $b->n }}</small></label>
                    @endforeach
                    @if ($facets['brands']->count() > 8)<button type="button" class="link-btn" @click="more = !more" x-text="more ? 'Mai puține' : 'Toate brandurile ({{ $facets['brands']->count() }})'"></button>@endif
                </fieldset>
                @endif
                @foreach ($facets['attributes'] as $f)
                    @php $slug = $f['attribute']->slug; $chosen = $facets['selected']['attrs'][$slug] ?? []; @endphp
                    <fieldset class="facet" x-data="{ more: false }">
                        <legend>{{ $f['attribute']->name }}</legend>
                        <input type="hidden" name="filter_{{ $slug }}" x-ref="attr_{{ $slug }}" value="{{ implode(',', $chosen) }}">
                        @foreach ($f['values'] as $v)
                            <label class="check-row" @if ($loop->index >= 8) x-show="more" @endif>
                                <input type="checkbox" value="{{ $v->slug }}" @checked(in_array($v->slug, $chosen, true)) @change.stop="group($event, 'attr_{{ $slug }}')">
                                @if ($v->color)<i class="swatch" style="background: {{ $v->color }}"></i>@endif {{ $v->name }} <small>{{ $v->n }}</small>
                            </label>
                        @endforeach
                        @if ($f['values']->count() > 8)<button type="button" class="link-btn" @click="more = !more" x-text="more ? 'Mai puține' : 'Arată toate'"></button>@endif
                    </fieldset>
                @endforeach
                <noscript><button class="btn btn-dark btn-sm" type="submit">Aplică filtrele</button></noscript>
            </form>
        </div>
    </aside>

    <section class="list-main" aria-label="Produse">
        <div class="toolbar">
            <button type="button" class="btn btn-ghost btn-sm filters-open" @click="open = true"><x-hv-icon name="filter" width="18" height="18" /> Filtre</button>
            <div class="chips" x-ref="chips">@include('shop.partials.chips')</div>
            <label class="sort"><span>Sortează</span>
                <select @change="sort($event.target.value)" aria-label="Sortează produsele">
                    @if (request('q'))<option value="relevance" @selected($query->sort() === 'relevance')>Relevanță</option>@endif
                    @foreach (\App\Services\Shop\CatalogQuery::SORTS as $key => $label)<option value="{{ $key }}" @selected($query->sort() === $key)>{{ $label }}</option>@endforeach
                </select>
            </label>
        </div>
        <div x-ref="results" :class="{ 'is-loading': loading }" aria-live="polite">
            @include('shop.partials.grid')
        </div>
    </section>
</div>
@endsection
