@extends('layouts.site')
@section('content')
@include('site._head', ['title' => 'Branduri', 'lead' => 'Toate mărcile din Pet Market, de la hrana de zi cu zi la dietele veterinare.'])
<div class="wrap">
    <nav class="az" aria-label="Litere">@foreach ($brands->keys() as $letter)<a href="#lit-{{ $letter }}">{{ $letter }}</a>@endforeach</nav>
    @foreach ($brands as $letter => $group)
        <section class="az-group" id="lit-{{ $letter }}"><h2 class="display h-md">{{ $letter }}</h2>
            <ul class="brand-list">@foreach ($group as $b)<li><a href="{{ site_url($b->path()) }}">@if ($b->logo)<img src="{{ asset($b->logo) }}" alt="" loading="lazy">@endif<span>{{ $b->name }}</span><small>{{ $b->products_count }}</small></a></li>@endforeach</ul>
        </section>
    @endforeach
</div>
@endsection
