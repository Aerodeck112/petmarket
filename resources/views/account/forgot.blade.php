@extends('layouts.site')
@section('content')
<div class="wrap page-pad narrow">
    <h1 class="display h-md">Parolă pierdută</h1>
    @if (session('status'))<p class="notice notice--ok">{{ session('status') }}</p>@endif
    <form class="card" method="post" action="{{ site_url('/contul-meu/parola-pierduta') }}">
        @csrf
        <p>Scrie emailul contului și îți trimitem un link pentru o parolă nouă.</p>
        <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" required autocomplete="email">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
        <button class="btn btn-dark" type="submit">Trimite linkul</button>
    </form>
</div>
@endsection
