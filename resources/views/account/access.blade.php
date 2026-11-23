@extends('layouts.site')
@section('content')
<div class="wrap page-pad">
    <div class="auth-head">
        <h1 class="display h-lg">Contul meu</h1>
        <x-buddy kind="cat" pose="sit" :say="\App\Support\Buddies::forPath('contul-meu/autentificare')['say']" />
    </div>
    @if (session('status'))<p class="notice notice--ok">{{ session('status') }}</p>@endif
    <div class="auth-grid">
        <form class="card" method="post" action="{{ site_url('/contul-meu/autentificare') }}">
            @csrf
            <h2 class="h-sm">Intră în cont</h2>
            <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
            <div class="field"><label for="password">Parola</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
            <label class="check"><input type="checkbox" name="remember" value="1"> <span>Ține-mă minte</span></label>
            <button class="btn btn-dark" type="submit">Intră în cont</button>
            <p><a href="{{ site_url('/contul-meu/parola-pierduta/') }}">Ai uitat parola?</a></p>
            <p class="muted small">Ai avut cont pe vechiul pet-market.ro? Folosește același email și aceeași parolă.</p>
        </form>
        <form class="card" method="post" action="{{ site_url('/contul-meu/inregistrare') }}">
            @csrf
            <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
            <h2 class="h-sm">Cont nou</h2>
            <div class="field"><label for="name">Nume și prenume</label><input id="name" name="name" value="{{ old('name') }}" required autocomplete="name">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
            <div class="field"><label for="reg_email">Email</label><input id="reg_email" type="email" name="reg_email" value="{{ old('reg_email') }}" required autocomplete="email">@error('reg_email')<p class="error">{{ $message }}</p>@enderror</div>
            <div class="field"><label for="reg_phone">Telefon <span class="muted">(opțional)</span></label><input id="reg_phone" name="phone" value="{{ old('phone') }}" autocomplete="tel"></div>
            <div class="grid-2">
                <div class="field"><label for="reg_password">Parola</label><input id="reg_password" type="password" name="reg_password" required autocomplete="new-password">@error('reg_password')<p class="error">{{ $message }}</p>@enderror</div>
                <div class="field"><label for="reg_password_confirmation">Confirmă parola</label><input id="reg_password_confirmation" type="password" name="reg_password_confirmation" required autocomplete="new-password"></div>
            </div>
            <label class="check"><input type="checkbox" name="newsletter" value="1"> <span>Vreau oferte și sfaturi pe email</span></label>
            <label class="check"><input type="checkbox" name="terms" value="1" required> <span>Accept <a href="{{ site_url('/termeni-conditii/') }}">termenii</a> și <a href="{{ site_url('/politica-confidentialitate/') }}">politica de confidențialitate</a>.</span></label>
            @error('terms')<p class="error">{{ $message }}</p>@enderror
            <button class="btn btn-primary" type="submit">Creează contul</button>
        </form>
    </div>
</div>
@endsection
