@extends('account._layout')
@section('account')
<h1 class="display h-md">Adrese</h1>
<div class="addr-grid">
    @foreach ($addresses as $a)
        <div class="card addr" x-data="{ edit: false }">
            <p class="addr-type">{{ $a->type === 'billing' ? 'Facturare' : 'Livrare' }} @if ($a->is_default)<span class="badge badge--ok">implicită</span>@endif</p>
            <p x-show="!edit">{{ $a->first_name }} {{ $a->last_name }}@if ($a->company)<br>{{ $a->company }} · CUI {{ $a->cui }}@endif<br>{{ $a->address_1 }} {{ $a->address_2 }}<br>{{ $a->city }}, {{ \App\Services\Shop\Counties::name($a->county) }} {{ $a->postcode }}<br>{{ $a->phone }}</p>
            <div x-show="!edit" class="addr-actions"><button class="link-btn" type="button" @click="edit = true">Modifică</button>
                <form method="post" action="{{ site_url('/contul-meu/adrese/'.$a->id.'/sterge') }}">@csrf<button class="link-btn link-btn--danger" type="submit">Șterge</button></form></div>
            <form x-show="edit" x-cloak method="post" action="{{ site_url('/contul-meu/adrese') }}">@csrf<input type="hidden" name="id" value="{{ $a->id }}">@include('account.partials.address-form', ['a' => $a])</form>
        </div>
    @endforeach
    <details class="card addr addr-new" @if ($errors->any()) open @endif>
        <summary><x-hv-icon name="plus" width="20" height="20" /> Adaugă o adresă</summary>
        <form method="post" action="{{ site_url('/contul-meu/adrese') }}">@csrf @include('account.partials.address-form', ['a' => null])</form>
    </details>
</div>
@endsection
