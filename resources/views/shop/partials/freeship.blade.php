{{-- Bara „livrare gratuita”: Hiko alearga spre prag. --}}
@if ($quote->freeShippingRemaining !== null && ! $quote->isEmpty())
    @php $threshold = (int) \App\Support\Settings::get('commerce.free_shipping_threshold'); $done = $threshold ? min(100, (int) round(($threshold - $quote->freeShippingRemaining) * 100 / $threshold)) : 100; @endphp
    <div class="freeship {{ $quote->freeShippingRemaining === 0 ? 'is-done' : '' }}">
        <p>@if ($quote->freeShippingRemaining === 0)<b>Ai livrare gratuită!</b> Hiko a ajuns la ușă.@else Mai adaugă <b>{{ money($quote->freeShippingRemaining) }}</b> pentru livrare gratuită.@endif</p>
        <div class="freeship-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $done }}" aria-label="Progres spre livrarea gratuită"><span style="width: {{ $done }}%"></span><i style="left: {{ $done }}%" aria-hidden="true"><x-hv-icon name="paw" width="16" height="16" /></i></div>
    </div>
@endif
