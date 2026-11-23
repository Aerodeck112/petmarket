@extends('account._layout')
@section('account')
<h1 class="display h-md">Favorite</h1>
@if ($products->isEmpty())
    <div class="empty empty--sm">@include('partials.mascot-dog')<p>Încă nu ai produse favorite. Apasă inimioara de pe orice produs.</p></div>
@else
    <div class="grid-p grid-p--3">@foreach ($products as $p)@include('shop.partials.card', ['p' => $p])@endforeach</div>
@endif
@endsection
