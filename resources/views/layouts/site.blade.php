<!doctype html>
<html lang="ro" data-intl="ro-RO" data-base="{{ rtrim(request()->root(), '/') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $seo->title }}</title>
    <meta name="description" content="{{ $seo->description }}">
    <link rel="canonical" href="{{ $seo->canonical }}">
    @if ($seo->noindex)<meta name="robots" content="noindex, follow">@endif
    <meta property="og:type" content="{{ $seo->type }}">
    <meta property="og:locale" content="ro_RO">
    <meta property="og:site_name" content="{{ $shop['name'] }}">
    <meta property="og:title" content="{{ $seo->title }}">
    <meta property="og:description" content="{{ $seo->description }}">
    <meta property="og:url" content="{{ $seo->canonical }}">
    <meta property="og:image" content="{{ $seo->image ?? asset('media/brand/og-pet-market.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="#1e3ba8">
    @if ($shop['verification'])<meta name="google-site-verification" content="{{ $shop['verification'] }}">@endif
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @foreach ($seo->jsonLd as $schema)
    <script type="application/ld+json">{!! json_encode(array_filter($schema, fn ($v) => $v !== null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endforeach
    @vite(['resources/css/site.css', 'resources/js/site.js'])
    @include('partials.tracking-head')
    @stack('head')
</head>
@php
    $cartService = app(\App\Services\Shop\CartService::class);
    $cartCount = $cartService->count();
    $menu = \App\Models\Category::tree();
@endphp
<body x-data="shopShell({{ $cartCount }})" @keydown.escape.window="closeAll()" :class="{ 'is-locked': drawer || menuOpen }">
@include('partials.tracking-body')
<a class="skip" href="#continut">Sari la conținut</a>

@if ($shop['announcement'])
<div class="topbar">
    <div class="wrap topbar-inner">
        <p><x-hv-icon name="truck" width="18" height="18" /> {{ $shop['announcement'] }}</p>
        <p class="topbar-side"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $shop['phone']) }}" data-track="phone_click"><x-hv-icon name="phone" width="16" height="16" /> {{ $shop['phone'] }}</a> <span>{{ $shop['hours'] }}</span></p>
    </div>
</div>
@endif

<header class="shop-header">
    <div class="wrap">
        <div class="bar">
            <button class="bar-burger" type="button" @click="menuOpen = true" aria-controls="meniu-mobil" :aria-expanded="menuOpen.toString()">
                <span class="sr-only">Meniu</span><x-hv-icon name="menu" width="22" height="22" />
            </button>
            <a class="brand-badge" href="{{ site_url('/') }}" aria-label="{{ $shop['name'] }} – pagina principală">
                <img src="{{ asset('media/brand/pet-market-logo.png') }}" alt="{{ $shop['name'] }}" width="360" height="120">
                <span class="brand-by">{{ $shop['byline'] }}</span>
            </a>

            <form class="search" role="search" action="{{ site_url('/cautare/') }}" method="get" x-data="searchBox" @click.outside="open = false" @submit="track()">
                <label class="sr-only" for="cautare">Caută produse</label>
                <input id="cautare" type="search" name="q" value="{{ request('q') }}" placeholder="Caută hrană, brand, cod produs…" autocomplete="off"
                       x-model="term" @input.debounce.220ms="fetch()" @focus="open = results.products.length > 0" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter="go($event)"
                       role="combobox" aria-autocomplete="list" aria-controls="sugestii" :aria-expanded="open.toString()">
                <button type="submit" class="search-go" aria-label="Caută"><x-hv-icon name="search" width="20" height="20" /></button>
                <div class="suggest" id="sugestii" x-show="open" x-cloak role="listbox">
                    <template x-if="results.categories.length">
                        <ul class="suggest-cats"><template x-for="c in results.categories" :key="c.url"><li><a :href="c.url" x-text="c.name"></a></li></template></ul>
                    </template>
                    <ul class="suggest-list">
                        <template x-for="(p, i) in results.products" :key="p.url">
                            <li><a :href="p.url" :class="{ 'is-active': i === active }" role="option">
                                <span class="suggest-img"><img :src="p.image || '{{ asset('media/brand/placeholder.svg') }}'" alt="" loading="lazy"></span>
                                <span class="suggest-name" x-text="p.name"></span>
                                <span class="suggest-price"><b x-text="p.price"></b><s x-show="p.regular" x-text="p.regular"></s></span>
                            </a></li>
                        </template>
                    </ul>
                    <a class="suggest-all" :href="results.all">Vezi toate rezultatele <x-hv-icon name="arrow" width="16" height="16" /></a>
                    <p class="suggest-empty" x-show="!loading && term.length > 1 && !results.products.length">Raza n-a găsit nimic. Încearcă alt cuvânt.</p>
                </div>
            </form>

            <nav class="bar-actions" aria-label="Contul meu și coș">
                <a class="bar-icon" href="{{ site_url(auth()->check() ? '/contul-meu/' : '/contul-meu/autentificare/') }}">
                    <x-hv-icon name="user" width="22" height="22" /><span>{{ auth()->check() ? 'Contul meu' : 'Intră în cont' }}</span>
                </a>
                <a class="bar-icon" href="{{ site_url(auth()->check() ? '/contul-meu/favorite/' : '/contul-meu/autentificare/') }}">
                    <x-hv-icon name="heart-o" width="22" height="22" /><span>Favorite</span>
                </a>
                <a class="cart-btn" href="{{ site_url('/cos/') }}" @click.prevent="openCart()" data-track="cart_open">
                    <x-hv-icon name="cart" width="22" height="22" />
                    <span class="cart-label">Coș</span>
                    <b class="cart-count" x-text="count" x-show="count > 0" @if (! $cartCount) x-cloak @endif>{{ $cartCount }}</b>
                </a>
            </nav>
        </div>

        <nav class="cats" aria-label="Categorii">
            <ul class="cats-list">
                @foreach ($menu as $cat)
                    <li class="cats-item" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" @focusin="open = true" @focusout="open = $el.contains($event.relatedTarget)">
                        <a href="{{ site_url($cat->path()) }}" @if (request()->is('categorie-produs/'.$cat->path.'*')) aria-current="page" @endif>{{ $cat->name }}</a>
                        @if ($cat->children->isNotEmpty())
                            <div class="mega" x-show="open" x-cloak x-transition.opacity.duration.150ms>
                                <div class="mega-cols">
                                    @foreach ($cat->children as $child)
                                        <div class="mega-col">
                                            <a class="mega-head" href="{{ site_url($child->path()) }}">{{ $child->name }}</a>
                                            @if ($child->children->isNotEmpty())
                                                <ul>@foreach ($child->children->take(8) as $leaf)<li><a href="{{ site_url($leaf->path()) }}">{{ $leaf->name }}</a></li>@endforeach</ul>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                                <a class="mega-all" href="{{ site_url($cat->path()) }}">Tot din {{ $cat->name }} <x-hv-icon name="arrow" width="16" height="16" /></a>
                            </div>
                        @endif
                    </li>
                @endforeach
                <li class="cats-item cats-item--hot"><a href="{{ site_url('/magazin/?on_sale=1') }}"><x-hv-icon name="fire" width="16" height="16" /> Reduceri</a></li>
                <li class="cats-item"><a href="{{ site_url('/branduri/') }}">Branduri</a></li>
            </ul>
        </nav>
    </div>
</header>

{{-- Meniul de pe telefon: sertar lateral cu categoriile pe niveluri --}}
<div class="drawer drawer--left" id="meniu-mobil" x-show="menuOpen" x-cloak x-transition.opacity role="dialog" aria-modal="true" aria-label="Meniu">
    <div class="drawer-backdrop" @click="menuOpen = false"></div>
    <div class="drawer-panel" x-trap.noscroll="menuOpen">
        <div class="drawer-head"><strong>Categorii</strong><button type="button" class="icon-btn" @click="menuOpen = false" aria-label="Închide meniul"><x-hv-icon name="close" width="20" height="20" /></button></div>
        <ul class="m-cats">
            @foreach ($menu as $cat)
                <li x-data="{ open: false }">
                    <div class="m-row"><a href="{{ site_url($cat->path()) }}">{{ $cat->name }}</a>
                        @if ($cat->children->isNotEmpty())<button type="button" class="icon-btn" @click="open = !open" :aria-expanded="open.toString()" aria-label="Subcategorii {{ $cat->name }}"><x-hv-icon name="plus" width="18" height="18" /></button>@endif
                    </div>
                    @if ($cat->children->isNotEmpty())
                        <ul x-show="open" x-transition>@foreach ($cat->children as $child)<li><a href="{{ site_url($child->path()) }}">{{ $child->name }}</a></li>@endforeach</ul>
                    @endif
                </li>
            @endforeach
            <li><div class="m-row"><a href="{{ site_url('/magazin/?on_sale=1') }}">Reduceri</a></div></li>
            <li><div class="m-row"><a href="{{ site_url('/branduri/') }}">Branduri</a></div></li>
            <li><div class="m-row"><a href="{{ site_url('/contact/') }}">Contact</a></div></li>
        </ul>
    </div>
</div>

{{-- Cosul lateral --}}
<div class="drawer drawer--right" x-show="drawer" x-cloak x-transition.opacity role="dialog" aria-modal="true" aria-label="Coșul tău">
    <div class="drawer-backdrop" @click="drawer = false"></div>
    <div class="drawer-panel" x-trap.noscroll="drawer">
        <div class="drawer-head"><strong>Coșul tău</strong><button type="button" class="icon-btn" @click="drawer = false" aria-label="Închide coșul"><x-hv-icon name="close" width="20" height="20" /></button></div>
        <div class="mini-cart" x-html="mini" aria-live="polite"><p class="muted">Se încarcă…</p></div>
    </div>
</div>

<div class="toast" x-show="toast" x-cloak x-transition role="status" aria-live="polite"><span x-text="toast"></span></div>

<main id="continut">
    @if (session('cart_ok'))<div class="wrap"><p class="notice notice--ok flash">{{ session('cart_ok') }}</p></div>@endif
    @if (session('cart_error'))<div class="wrap"><p class="notice notice--error flash">{{ session('cart_error') }}</p></div>@endif
    @yield('content')
</main>

<section class="newsletter no-print" aria-labelledby="nl-title">
    <div class="wrap newsletter-inner">
        <x-buddy class="nl-buddy" kind="dog" pose="peek" :say="['Ofertele ți le aduc eu. În gură, dar uscate.', 'Un email pe săptămână. Promit pe coadă.']" />
        <div>
            <h2 id="nl-title" class="display h-md">Recompense direct în inbox</h2>
            <p>Oferte, produse noi și sfaturi de la medicii HiKeVet. Fără spam, cuvânt de ciobănesc.</p>
        </div>
        <form class="nl-form" action="{{ site_url('/newsletter') }}" method="post" x-data="newsletter" @submit.prevent="send($el)">
            @csrf
            <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
            <label class="sr-only" for="nl-email">Adresa de email</label>
            <div class="nl-row"><input id="nl-email" type="email" name="email" required placeholder="adresa@email.ro" autocomplete="email"><button class="btn btn-primary" type="submit" :disabled="busy">Abonează-mă</button></div>
            <label class="check nl-check"><input type="checkbox" name="consent" value="1" required> <span>Sunt de acord să primesc emailuri de marketing. Mă pot dezabona oricând.</span></label>
            <p class="nl-msg" x-show="msg" x-text="msg" x-cloak></p>
            @if (session('newsletter_ok'))<p class="nl-msg">{{ session('newsletter_ok') }}</p>@endif
        </form>
    </div>
</section>

<footer class="site-footer">
    <x-buddy class="footer-cat" kind="cat" pose="sleep" :greet="false" :say="['Zzz… a, bună! Coletul vine, eu dorm.', 'Mai stai puțin? E cald aici.', 'Miau. Am păzit coșul toată ziua.']" />
    <div class="wrap">
        <div class="footer-perks">
            <div><x-hv-icon name="truck" /><p><strong>Livrare rapidă</strong><span>@if ($shop['free_shipping'])gratuită de la {{ money($shop['free_shipping']) }}@else în toată țara @endif</span></p></div>
            <div><x-hv-icon name="return" /><p><strong>Retur în 14 zile</strong><span>conform OUG 34/2014</span></p></div>
            <div><x-hv-icon name="stethoscope" /><p><strong>Ales de medici</strong><span>echipa clinicii HiKeVet</span></p></div>
            <div><x-hv-icon name="lock" /><p><strong>Plată sigură</strong><span>ramburs sau online</span></p></div>
        </div>
        <div class="footer-top">
            <div>
                <a class="footer-logo" href="{{ site_url('/') }}" aria-label="{{ $shop['name'] }} – pagina principală"><img src="{{ asset('media/brand/pet-market-logo-alb.png') }}" alt="" width="360" height="120" loading="lazy"></a>
                <h2 class="sr-only">{{ $shop['name'] }}</h2>
                <p class="measure">{{ $shop['tagline'] }}</p>
                <ul class="footer-contact">
                    <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $shop['phone']) }}" data-track="phone_click">{{ $shop['phone'] }}</a></li>
                    <li><a href="mailto:{{ $shop['email'] }}">{{ $shop['email'] }}</a></li>
                    <li>{{ $shop['hours'] }}</li>
                </ul>
            </div>
            <div>
                <h2>Magazin</h2>
                <ul>
                    @foreach ($menu->take(6) as $cat)<li><a href="{{ site_url($cat->path()) }}">{{ $cat->name }}</a></li>@endforeach
                    <li><a href="{{ site_url('/branduri/') }}">Branduri</a></li>
                </ul>
            </div>
            <div>
                <h2>Ajutor</h2>
                <ul>
                    <li><a href="{{ site_url('/livrare/') }}">Livrare</a></li>
                    <li><a href="{{ site_url('/politica-de-retur/') }}">Retur</a></li>
                    <li><a href="{{ site_url('/intrebari-frecvente/') }}">Întrebări frecvente</a></li>
                    <li><a href="{{ site_url('/despre-noi/') }}">Despre noi</a></li>
                    <li><a href="{{ site_url('/contact/') }}">Contact</a></li>
                    <li><a href="{{ site_url('/contul-meu/comenzi/') }}">Comenzile mele</a></li>
                </ul>
            </div>
            <div>
                <h2>Legal</h2>
                <ul>
                    <li><a href="{{ site_url('/termeni-conditii/') }}">Termeni și condiții</a></li>
                    <li><a href="{{ site_url('/politica-confidentialitate/') }}">Confidențialitate</a></li>
                    <li><a href="{{ site_url('/politica-cookies/') }}">Cookies</a></li>
                    <li><a href="https://anpc.ro/" rel="noopener">ANPC</a></li>
                    @if ($shop['facebook'])<li><a href="{{ $shop['facebook'] }}" rel="noopener">Facebook</a></li>@endif
                    @if ($shop['instagram'])<li><a href="{{ $shop['instagram'] }}" rel="noopener">Instagram</a></li>@endif
                </ul>
            </div>
        </div>
        <div class="footer-word" aria-hidden="true">Pet Market</div>
        <div class="footer-bottom">
            <span>© {{ date('Y') }} {{ $shop['legal_name'] }}@if ($shop['cui']) · CUI {{ $shop['cui'] }}@endif @if ($shop['reg_com']) · {{ $shop['reg_com'] }}@endif. {{ $shop['name'] }} {{ $shop['byline'] }}.</span>
            <a class="made-by" href="https://vitim.ro/?utm_source=pet-market.ro&amp;utm_medium=referral&amp;utm_campaign=footer" rel="noopener" aria-label="Magazin dezvoltat de VITIM (vitim.ro)">
                <span class="made-by-face" aria-hidden="true"></span>
                <span class="made-by-text">Dezvoltat cu grijă (și multe recompense pentru Hiko) de <strong>VITIM</strong></span>
                <span class="made-by-tip" aria-hidden="true">Ham! Aici am învățat să vând recompense.</span>
            </a>
            <div class="footer-badges">
                <a href="https://reclamatiisal.anpc.ro/" rel="noopener"><img src="{{ asset('media/brand/anpc-sal.svg') }}" alt="ANPC – Soluționarea alternativă a litigiilor" width="250" height="62" loading="lazy"></a>
                <a href="https://ec.europa.eu/consumers/odr" rel="noopener"><img src="{{ asset('media/brand/anpc-sol.svg') }}" alt="Soluționarea online a litigiilor (SOL)" width="250" height="62" loading="lazy"></a>
            </div>
        </div>
    </div>
</footer>

{{-- Cuvinte secrete: scrie „miau” sau „ham” pe orice pagina --}}
<template id="secret-cat"><div class="secret-pop secret-pop--cat" aria-live="polite"><p class="secret-say"></p>@include('partials.mascot-cat')</div></template>
<template id="secret-dog"><div class="secret-pop secret-pop--dog" aria-live="polite"><p class="secret-say"></p>@include('partials.mascot-dog')</div></template>
<nav class="mobile-bar no-print" aria-label="Acțiuni rapide">
    <a href="{{ site_url('/') }}"><x-hv-icon name="home" width="22" height="22" /><span>Acasă</span></a>
    <button type="button" @click="menuOpen = true"><x-hv-icon name="grid" width="22" height="22" /><span>Categorii</span></button>
    <a href="{{ site_url(auth()->check() ? '/contul-meu/' : '/contul-meu/autentificare/') }}"><x-hv-icon name="user" width="22" height="22" /><span>Cont</span></a>
    <a href="{{ site_url('/cos/') }}" @click.prevent="openCart()"><x-hv-icon name="cart" width="22" height="22" /><span>Coș</span><b x-text="count" x-show="count > 0" x-cloak></b></a>
</nav>
@stack('scripts')
</body>
</html>
