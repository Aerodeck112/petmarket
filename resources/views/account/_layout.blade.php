@extends('layouts.site')
@section('content')
<div class="wrap page-pad">
    <nav aria-label="Ești aici"><ol class="crumbs">@foreach ($seo->breadcrumbs as $crumb)<li>@if (! $loop->last)<a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a>@else<span aria-current="page">{{ $crumb['name'] }}</span>@endif</li>@endforeach</ol></nav>
    <div class="acct">
        @include('account._nav')
        <div class="acct-main">
            @if (session('status'))<p class="notice notice--ok">{{ session('status') }}</p>@endif
            @yield('account')
        </div>
    </div>
</div>
@endsection
