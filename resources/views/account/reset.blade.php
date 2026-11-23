@extends('layouts.site')
@section('content')
<div class="wrap page-pad narrow">
    <h1 class="display h-md">Setează parola</h1>
    <form class="card" method="post" action="{{ site_url('/contul-meu/parola-noua') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email', $email) }}" required>@error('email')<p class="error">{{ $message }}</p>@enderror</div>
        <div class="field"><label for="password">Parola nouă</label><input id="password" type="password" name="password" required autocomplete="new-password">@error('password')<p class="error">{{ $message }}</p>@enderror</div>
        <div class="field"><label for="password_confirmation">Confirmă parola</label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"></div>
        <button class="btn btn-dark" type="submit">Salvează parola</button>
    </form>
</div>
@endsection
