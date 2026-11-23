@if ($quote->isEmpty())
    <div class="mini-empty">
        @include('partials.mascot-cat')
        <p><b>Coșul e gol.</b> Raza a verificat de două ori.</p>
        <a class="btn btn-dark btn-sm" href="{{ site_url('/magazin/') }}">Mergi la magazin</a>
    </div>
@else
    @include('shop.partials.freeship')
    <ul class="mini-list">
        @foreach ($quote->lines as $line)
            <li>
                <a href="{{ site_url($line->product->path()) }}" class="mini-img">@if ($line->image())<img src="{{ $line->image() }}" alt="" loading="lazy">@endif</a>
                <div><a href="{{ site_url($line->product->path()) }}">{{ $line->product->name }}</a>@if ($line->label())<small>{{ $line->label() }}</small>@endif
                    <span class="mini-qty">{{ $line->qty }} × {{ money($line->unitPrice()) }}</span></div>
                <button type="button" class="icon-btn" @click="removeItem({{ $line->cartItemId }})" aria-label="Scoate din coș: {{ $line->product->name }}"><x-hv-icon name="trash" width="18" height="18" /></button>
            </li>
        @endforeach
    </ul>
    <div class="mini-foot">
        <p class="mini-total"><span>Subtotal</span><b>{{ money($quote->subtotal - $quote->discount) }}</b></p>
        <a class="btn btn-ghost" href="{{ site_url('/cos/') }}">Vezi coșul</a>
        <a class="btn btn-primary" href="{{ site_url('/finalizare/') }}" data-track="begin_checkout">Finalizează comanda</a>
    </div>
@endif
