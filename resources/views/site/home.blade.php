@extends('layouts.site')

@section('content')
@php
    $slide = $slides->first();
    $posters = [
        ['media/site/hero/slide-1-caini.webp', 'Hrană super premium pentru câini', '/categorie-produs/hrana/caini-hrana/'],
        ['media/site/hero/slide-2-pisici.webp', 'Totul pentru pisici', '/categorie-produs/hrana/pisici-hrana/'],
    ];
    $tileColors = ['sun', 'magenta', 'cobalt', 'tangerine', 'teal', 'sky'];
    $tileIcons = ['bowl', 'heart', 'pulse', 'paw', 'stethoscope', 'drop', 'shield', 'bone'];
@endphp
<section class="wall wall--shop" data-wall>
    <div class="wrap wall-grid">
        <div class="wall-stage">
            <span class="paint dots deco-dots" aria-hidden="true"></span>
            <div class="perch-cat">@include('partials.mascot-cat')</div>
            <div class="bubble" x-data="heroSlides({{ $slides->count() }})">
                @include('partials.bubble-shape')
                <p class="bubble-kicker">Pet Market <span>by HiKeVet</span></p>
                @forelse ($slides as $i => $s)
                    <div class="bubble-slide" x-show="i === {{ $i }}" @if ($i) x-cloak @endif x-transition.opacity.duration.300ms>
                        @if ($i === 0)<h1 class="display">{{ $s->title }}</h1>@else<h2 class="display">{{ $s->title }}</h2>@endif
                        @if ($s->subtitle)<p>{{ $s->subtitle }}</p>@endif
                        <div class="bubble-actions">
                            @if ($s->url)<a class="btn btn-primary" href="{{ site_url($s->url) }}">{{ $s->cta_label ?: 'Vezi produsele' }}</a>@endif
                            <a class="btn btn-ghost" href="{{ site_url('/magazin/?on_sale=1') }}">Reduceri</a>
                        </div>
                    </div>
                @empty
                    <h1 class="display">Pentru că nu e doar un animal</h1>
                    <p>{{ $shop['tagline'] }}</p>
                    <div class="bubble-actions"><a class="btn btn-primary" href="{{ site_url('/magazin/') }}">Intră în magazin</a></div>
                @endforelse
                @if ($slides->count() > 1)
                    <div class="bubble-dots" role="tablist" aria-label="Mesaje">
                        @foreach ($slides as $i => $s)<button type="button" role="tab" @click="go({{ $i }})" :aria-selected="(i === {{ $i }}).toString()" aria-label="Mesajul {{ $i + 1 }}"></button>@endforeach
                    </div>
                @endif
                <button class="treat-btn" type="button" data-treat aria-label="Dă-i o recompensă lui Hiko"><span aria-hidden="true">🦴</span> Recompensă pentru Hiko <small data-treat-count></small></button>
                <button class="peek-dog" type="button" aria-label="Hiko, câinele Pet Market: apasă și latră">
                    @include('partials.mascot-dog')
                    <span class="woof" aria-live="polite"></span>
                </button>
            </div>
        </div>
        <div class="posters">
            <span class="paint paint--tri deco-tri-m" aria-hidden="true"></span>
            <span class="paint deco-arrow" aria-hidden="true"></span>
            <a href="{{ site_url($posters[0][2]) }}" class="poster poster--main"><img src="{{ asset($posters[0][0]) }}" alt="" width="1920" height="1280" fetchpriority="high"><figcaption>{{ $posters[0][1] }}</figcaption></a>
            <a href="{{ site_url($posters[1][2]) }}" class="poster poster--side"><img src="{{ asset($posters[1][0]) }}" alt="" width="1920" height="1280" loading="lazy"><figcaption>{{ $posters[1][1] }}</figcaption></a>
            <span class="sticker-sun" aria-hidden="true">Ales de medici!</span>
            <span class="paint paint--tri deco-tri-o" aria-hidden="true"></span>
        </div>
    </div>
    <div class="wrap">
        <ul class="wall-facts">
            <li><span>Livrare</span><a href="{{ site_url('/livrare/') }}">@if ($shop['free_shipping'])gratuită de la {{ money($shop['free_shipping']) }}@else în toată țara @endif</a></li>
            <li><span>Comenzi și sfaturi</span><a href="tel:{{ preg_replace('/[^0-9+]/', '', $shop['phone']) }}" data-track="phone_click">{{ $shop['phone'] }}</a></li>
            <li><span>Clinica din spatele magazinului</span><a href="https://hikevet.ro/" rel="noopener">HiKeVet, Târgu Mureș</a></li>
        </ul>
    </div>
</section>

<div class="stripe" aria-hidden="true"></div>

@if ($categories->isNotEmpty())
<section class="section section--tight" aria-labelledby="cat-title">
    <div class="wrap">
        <div class="section-head"><div><p class="script">Rafturile lui Hiko</p><h2 id="cat-title" class="display h-md">Ce cauți azi?</h2></div>
            <a class="btn btn-ghost btn-sm" href="{{ site_url('/magazin/') }}">Tot magazinul <x-hv-icon name="arrow" width="18" height="18" /></a></div>
        <ul class="tiles">
            @foreach ($categories as $i => $c)
                <li><a class="tile tile--{{ $tileColors[$i % count($tileColors)] }}" href="{{ site_url($c->path()) }}">
                    @if ($c->image)<img src="{{ asset($c->image) }}" alt="" loading="lazy">@else<x-hv-icon :name="$tileIcons[$i % count($tileIcons)]" />@endif
                    <span>{{ $c->name }}</span><small>{{ $c->product_count }} produse</small>
                </a></li>
            @endforeach
        </ul>
    </div>
</section>
@endif

@include('shop.partials.rail', ['products' => $deals->count() >= 4 ? $deals : collect(), 'title' => 'Reduceri care dau din coadă', 'id' => 'reduceri', 'kicker' => 'Prețul cel mic din ultimele 30 de zile, afișat cinstit', 'more' => site_url('/magazin/?on_sale=1'), 'class' => 'rail-sec--sun'])
@include('shop.partials.rail', ['products' => $featured->isNotEmpty() ? $featured : $bestsellers, 'title' => 'Cele mai căutate', 'id' => 'populare', 'kicker' => 'Aprobate de Raza', 'more' => site_url('/magazin/?orderby=popularity')])

@if ($promos->isNotEmpty())
<section class="section section--tight" aria-label="Promoții">
    <div class="wrap promos">
        @foreach ($promos as $i => $promo)
            <a class="promo promo--{{ $promo->theme }}" href="{{ site_url($promo->url ?: '/magazin/') }}">
                <div class="promo-text"><h3 class="display">{{ $promo->title }}</h3>@if ($promo->subtitle)<p>{{ $promo->subtitle }}</p>@endif<span class="btn btn-light btn-sm">{{ $promo->cta_label ?: 'Vezi produsele' }}</span></div>
                @if ($promo->image)<img src="{{ asset($promo->image) }}" alt="" loading="lazy">@endif
            </a>
        @endforeach
    </div>
</section>
@endif

@include('shop.partials.rail', ['products' => $newest, 'title' => 'Proaspăt sosite', 'id' => 'noutati', 'kicker' => 'Le-a mirosit Hiko primul', 'more' => site_url('/magazin/?orderby=date')])

<section class="section section--cobalt story" aria-labelledby="story-title">
    <div class="wrap split">
        <div>
            <p class="script">Magazinul clinicii HiKeVet</p>
            <h2 id="story-title" class="display h-lg">Alese de medici, livrate acasă</h2>
            <p class="lead">În spatele Pet Market e echipa clinicii veterinare HiKeVet din Târgu Mureș. Dietele, suplimentele și tratamentele de pe raft sunt cele pe care le recomandăm zilnic pacienților noștri.</p>
            <ul class="ticks">
                <li><x-hv-icon name="check" /> Diete veterinare și suplimente, cu sfatul unui medic la telefon</li>
                <li><x-hv-icon name="check" /> Produsele cu rețetă se eliberează doar după consult, nu online</li>
                <li><x-hv-icon name="check" /> Livrare în toată țara{{ $shop['free_shipping'] ? ', gratuită de la '.money($shop['free_shipping']) : '' }}</li>
            </ul>
            <p class="story-actions"><a class="btn btn-primary" href="https://hikevet.ro/" rel="noopener">Cunoaște clinica</a> <a class="btn btn-ghost" href="{{ site_url('/despre-noi/') }}">Despre noi</a></p>
        </div>
        <div class="story-art">
            <img class="story-photo" src="{{ asset('media/site/sections/suport-dedicat-pisica.webp') }}" alt="" width="750" height="825" loading="lazy">
            <x-buddy class="story-buddy" kind="dog" pose="peek" :say="['Eu sunt Hiko, ciobănescul clinicii. Și al magazinului.', 'Raza alege hrana, eu verific recompensele.', 'Sună-ne dacă nu știi ce să alegi!']" />
        </div>
    </div>
</section>

@if ($brands->isNotEmpty())
<section class="section section--tight" aria-labelledby="brand-title">
    <div class="wrap">
        <div class="section-head"><h2 id="brand-title" class="display h-md">Branduri de încredere</h2><a class="btn btn-ghost btn-sm" href="{{ site_url('/branduri/') }}">Toate brandurile</a></div>
        <ul class="logos">@foreach ($brands as $b)<li><a href="{{ site_url($b->path()) }}"><img src="{{ asset($b->logo) }}" alt="{{ $b->name }}" loading="lazy"></a></li>@endforeach</ul>
    </div>
</section>
@endif

@if ($posts->isNotEmpty())
<section class="section section--tight" aria-labelledby="blog-title">
    <div class="wrap">
        <div class="section-head"><h2 id="blog-title" class="display h-md">Sfaturi de la medici</h2><a class="btn btn-ghost btn-sm" href="{{ site_url('/blog/') }}">Toate articolele</a></div>
        <div class="posts">@foreach ($posts as $post)@include('partials.post-card', ['post' => $post])@endforeach</div>
    </div>
</section>
@endif
@endsection
