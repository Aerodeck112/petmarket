<dl class="totals">
    <div><dt>Subtotal ({{ $quote->itemsCount() }} {{ $quote->itemsCount() === 1 ? 'produs' : 'produse' }})</dt><dd>{{ money($quote->subtotal) }}</dd></div>
    @if ($quote->discount)<div class="totals-discount"><dt>Reducere {{ $quote->coupon?->code }}</dt><dd>−{{ money($quote->discount) }}</dd></div>@endif
    <div><dt>Livrare @if ($quote->shippingMethod)<small>{{ $quote->shippingMethod->name }}</small>@endif</dt>
        <dd>@if (! $quote->shippingMethod)<small>se calculează la finalizare</small>@elseif ($quote->shipping === 0)<b class="free">gratuită</b>@else{{ money($quote->shipping) }}@endif</dd></div>
    @if ($quote->fee)<div><dt>Taxă ramburs</dt><dd>{{ money($quote->fee) }}</dd></div>@endif
    <div class="totals-grand"><dt>Total</dt><dd>{{ money($quote->total) }}</dd></div>
    @if ($quote->savings || $quote->discount)<p class="totals-vat">Economisești {{ money($quote->savings + $quote->discount) }}</p>@endif
</dl>
@foreach ($quote->problems as $problem)<p class="notice notice--error">{{ $problem }}</p>@endforeach
