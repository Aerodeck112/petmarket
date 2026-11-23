@extends('account._layout')
@section('account')
<h1 class="display h-md">Detalii cont</h1>
<form class="card" method="post" action="{{ site_url('/contul-meu/detalii') }}">
    @csrf
    <div class="grid-2">
        <div class="field"><label for="first_name">Prenume</label><input id="first_name" name="first_name" value="{{ old('first_name', $user->first_name) }}" required>@error('first_name')<p class="error">{{ $message }}</p>@enderror</div>
        <div class="field"><label for="last_name">Nume</label><input id="last_name" name="last_name" value="{{ old('last_name', $user->last_name) }}" required>@error('last_name')<p class="error">{{ $message }}</p>@enderror</div>
    </div>
    <div class="grid-2">
        <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required>@error('email')<p class="error">{{ $message }}</p>@enderror</div>
        <div class="field"><label for="phone">Telefon</label><input id="phone" name="phone" value="{{ old('phone', $user->phone) }}"></div>
    </div>
    <h2 class="h-sm">Schimbă parola</h2>
    <div class="field"><label for="current_password">Parola actuală</label><input id="current_password" type="password" name="current_password" autocomplete="current-password">@error('current_password')<p class="error">{{ $message }}</p>@enderror</div>
    <div class="grid-2">
        <div class="field"><label for="password">Parola nouă</label><input id="password" type="password" name="password" autocomplete="new-password">@error('password')<p class="error">{{ $message }}</p>@enderror</div>
        <div class="field"><label for="password_confirmation">Confirmă parola nouă</label><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password"></div>
    </div>
    <button class="btn btn-dark" type="submit">Salvează</button>
</form>
@endsection
