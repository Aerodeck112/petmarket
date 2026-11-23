<table class="table order-items">
    <thead><tr><th>Produs</th><th class="num">Cant.</th><th class="num">Total</th></tr></thead>
    <tbody>
        @foreach ($order->items as $item)
            <tr><td><div class="oi">@if ($item->image)<img src="{{ $item->image }}" alt="" width="48" height="48" loading="lazy">@endif<span>{{ $item->name }}@if ($item->variant_label)<small>{{ $item->variant_label }}</small>@endif<small>{{ money($item->unit_price) }} / buc.</small></span></div></td>
                <td class="num">{{ $item->qty }}</td><td class="num">{{ money($item->total) }}</td></tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr><td colspan="2">Subtotal</td><td class="num">{{ money($order->subtotal) }}</td></tr>
        @if ($order->discount_total)<tr><td colspan="2">Reducere {{ $order->coupon_code }}</td><td class="num">−{{ money($order->discount_total) }}</td></tr>@endif
        <tr><td colspan="2">Livrare</td><td class="num">{{ $order->shipping_total ? money($order->shipping_total) : 'gratuită' }}</td></tr>
        @if ($order->fee_total)<tr><td colspan="2">Taxă ramburs</td><td class="num">{{ money($order->fee_total) }}</td></tr>@endif
        <tr class="grand"><td colspan="2">Total <small>(TVA inclus: {{ money($order->vat_total) }})</small></td><td class="num">{{ money($order->total) }}</td></tr>
    </tfoot>
</table>
