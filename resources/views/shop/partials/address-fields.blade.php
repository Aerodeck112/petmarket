{{-- Campurile unei adrese: prefix b_ (facturare) sau s_ (livrare). --}}
@php $v = fn ($k, $d = null) => old($p.$k, $values[$k] ?? $d); @endphp
<div class="grid-2">
    <div class="field"><label for="{{ $p }}first_name">Prenume</label><input id="{{ $p }}first_name" name="{{ $p }}first_name" value="{{ $v('first_name') }}" required autocomplete="{{ $ac }} given-name">@error($p.'first_name')<p class="error">{{ $message }}</p>@enderror</div>
    <div class="field"><label for="{{ $p }}last_name">Nume</label><input id="{{ $p }}last_name" name="{{ $p }}last_name" value="{{ $v('last_name') }}" required autocomplete="{{ $ac }} family-name">@error($p.'last_name')<p class="error">{{ $message }}</p>@enderror</div>
</div>
<div class="grid-2">
    <div class="field"><label for="{{ $p }}county">Județ</label>
        <select id="{{ $p }}county" name="{{ $p }}county" required autocomplete="{{ $ac }} address-level1" @if ($p === 's_') @change="county = $event.target.value; refresh()" @else @change="if (!shipDifferent) { county = $event.target.value; refresh() }" @endif>
            <option value="">Alege județul</option>
            @foreach ($counties as $code => $name)<option value="{{ $code }}" @selected($v('county', 'MS') === $code)>{{ $name }}</option>@endforeach
        </select>@error($p.'county')<p class="error">{{ $message }}</p>@enderror</div>
    <div class="field"><label for="{{ $p }}city">Localitate</label><input id="{{ $p }}city" name="{{ $p }}city" value="{{ $v('city') }}" required autocomplete="{{ $ac }} address-level2">@error($p.'city')<p class="error">{{ $message }}</p>@enderror</div>
</div>
<div class="field"><label for="{{ $p }}address_1">Adresă (stradă, număr)</label><input id="{{ $p }}address_1" name="{{ $p }}address_1" value="{{ $v('address_1') }}" required autocomplete="{{ $ac }} address-line1">@error($p.'address_1')<p class="error">{{ $message }}</p>@enderror</div>
<div class="grid-2">
    <div class="field"><label for="{{ $p }}address_2">Bloc, scară, apartament <span class="muted">(opțional)</span></label><input id="{{ $p }}address_2" name="{{ $p }}address_2" value="{{ $v('address_2') }}" autocomplete="{{ $ac }} address-line2"></div>
    <div class="field"><label for="{{ $p }}postcode">Cod poștal <span class="muted">(opțional)</span></label><input id="{{ $p }}postcode" name="{{ $p }}postcode" value="{{ $v('postcode') }}" inputmode="numeric" maxlength="6" autocomplete="{{ $ac }} postal-code">@error($p.'postcode')<p class="error">{{ $message }}</p>@enderror</div>
</div>
