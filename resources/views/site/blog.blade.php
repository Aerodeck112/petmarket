@extends('layouts.site')

@section('content')
@include('site._head', ['title' => __('Blog'), 'lead' => $page->lead ?: 'Sfaturi de nutriție și îngrijire de la medicii HiKeVet.'])
<section style="padding-bottom: var(--section)">
    <div class="wrap">
        @if ($posts->isEmpty())
            <p class="notice">{{ __('Încă nu am publicat articole.') }}</p>
        @else
            <div class="posts">
                @foreach ($posts as $post)
                    @include('partials.post-card', ['post' => $post])
                @endforeach
            </div>
            <div style="margin-top:2rem">{{ $posts->links('partials.pagination') }}</div>
        @endif
    </div>
</section>
@endsection
