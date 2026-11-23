@extends('account._layout')
@section('account')
<div class="acct-hello">
    <div>
        <h1 class="display h-md">Salut, {{ $user->first_name ?: $user->name }}!</h1>
        <p>De aici îți vezi comenzile, adresele și produsele favorite.</p>
    </div>
    <x-buddy kind="dog" pose="peek" :say="\App\Support\Buddies::forPath('contul-meu')['say']" />
</div>
<dl class="acct-stats">
    <div><dt>Comenzi</dt><dd>{{ $user->orders_count }}</dd></div>
    <div><dt>Total cumpărături</dt><dd>{{ money($user->total_spent) }}</dd></div>
    <div><dt>Favorite</dt><dd>{{ $wishlistCount }}</dd></div>
</dl>
<h2 class="h-sm">Comenzi recente</h2>
@forelse ($orders as $order)
    @include('account.partials.order-row', ['order' => $order])
@empty
    <div class="empty empty--sm">@include('partials.mascot-cat')<p>Încă nu ai comenzi. <a href="{{ site_url('/magazin/') }}">Hai la cumpărături!</a></p></div>
@endforelse
@if ($orders->isNotEmpty())<p><a href="{{ site_url('/contul-meu/comenzi/') }}">Toate comenzile</a></p>@endif
@endsection
