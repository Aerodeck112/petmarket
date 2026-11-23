<nav class="acct-nav" aria-label="Contul meu">
    @foreach ([['/contul-meu/', 'Panou', 'home'], ['/contul-meu/comenzi/', 'Comenzi', 'box'], ['/contul-meu/favorite/', 'Favorite', 'heart-o'], ['/contul-meu/adrese/', 'Adrese', 'pin'], ['/contul-meu/detalii/', 'Detalii cont', 'user']] as [$href, $label, $icon])
        <a href="{{ site_url($href) }}" @if (rtrim(request()->getPathInfo(), '/').'/' === $href || ($href !== '/contul-meu/' && str_starts_with(request()->getPathInfo().'/', $href))) aria-current="page" @endif><x-hv-icon :name="$icon" width="20" height="20" /> {{ $label }}</a>
    @endforeach
    <form method="post" action="{{ site_url('/contul-meu/iesire') }}">@csrf<button type="submit" class="link-btn">Ieșire</button></form>
</nav>
