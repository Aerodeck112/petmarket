@php $s = \App\Support\Settings::class; $proforma = $kind === 'proforma'; @endphp
<!doctype html>
<html lang="ro"><head><meta charset="utf-8"><title>{{ $proforma ? 'Proformă' : 'Picking' }} {{ $order->number }}</title>
<style>
    body { font: 13px/1.45 system-ui, Arial, sans-serif; color: #1f1638; margin: 0; padding: 28px; }
    .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #1f1638; padding-bottom: 14px; margin-bottom: 18px; }
    .head img { height: 54px; }
    h1 { font-size: 22px; margin: 0 0 4px; }
    .cols { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 18px; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; border-bottom: 2px solid #1f1638; padding: 6px; }
    td { border-bottom: 1px solid #ddd; padding: 7px 6px; vertical-align: top; }
    .r { text-align: right; white-space: nowrap; }
    .tot td { border: 0; padding: 3px 6px; }
    .grand td { font-size: 16px; font-weight: 800; border-top: 2px solid #1f1638; }
    .box { width: 18px; height: 18px; border: 2px solid #1f1638; border-radius: 4px; }
    .note { margin-top: 18px; padding: 10px 12px; background: #fff6d6; border-radius: 8px; }
    .muted { color: #6b6580; }
    @media print { .noprint { display: none; } body { padding: 0; } }
</style></head>
<body>
<p class="noprint"><button onclick="window.print()">Tipărește</button></p>
<div class="head">
    <div><img src="{{ asset('media/brand/pet-market-logo.png') }}" alt=""><div class="muted">{{ $s::get('shop.legal_name') }} · CUI {{ $s::get('shop.cui') }} · {{ $s::get('shop.reg_com') }}<br>{{ $s::get('shop.address') }}, {{ $s::get('shop.city') }}, jud. {{ $s::get('shop.county') }}<br>{{ $s::get('shop.phone') }} · {{ $s::get('shop.email') }}</div></div>
    <div style="text-align:right"><h1>{{ $proforma ? 'Factură proformă' : 'Listă de picking' }}</h1><div>Comanda <b>{{ $order->number }}</b><br>{{ $order->created_at->format('d.m.Y H:i') }}<br>{{ $order->payment_method->label() }}</div></div>
</div>
<div class="cols">
    <div><b>Cumpărător</b><br>@if ($order->customer_type === 'pj'){{ $order->billing['company'] ?? '' }}<br>CUI {{ $order->billing['cui'] ?? '' }} {{ $order->billing['reg_com'] ?? '' }}<br>@endif{{ trim(($order->billing['first_name'] ?? '').' '.($order->billing['last_name'] ?? '')) }}<br>{{ \App\Models\Order::addressLine($order->billing) }}<br>{{ $order->email }} · {{ $order->phone }}</div>
    <div><b>Livrare</b> ({{ $order->shipping_method_name }})<br>{{ trim(($order->shipping['first_name'] ?? '').' '.($order->shipping['last_name'] ?? '')) }}<br>{{ \App\Models\Order::addressLine($order->shipping) }}<br>{{ $order->phone }}@if ($order->tracking_number)<br>AWB {{ $order->tracking_number }}@endif</div>
</div>
<table>
    <thead><tr>@unless ($proforma)<th></th>@endunless<th>#</th><th>Produs</th><th>SKU</th><th class="r">Cant.</th>@if ($proforma)<th class="r">Preț unitar</th><th class="r">Valoare</th>@endif</tr></thead>
    <tbody>
    @foreach ($order->items as $i => $item)
        <tr>@unless ($proforma)<td><div class="box"></div></td>@endunless<td>{{ $i + 1 }}</td><td>{{ $item->name }}@if ($item->variant_label)<br><span class="muted">{{ $item->variant_label }}</span>@endif</td><td>{{ $item->sku }}</td><td class="r"><b>{{ $item->qty }}</b></td>
            @if ($proforma)<td class="r">{{ money($item->unit_price) }}</td><td class="r">{{ money($item->total) }}</td>@endif</tr>
    @endforeach
    </tbody>
    @if ($proforma)
    <tfoot>
        <tr class="tot"><td colspan="4"></td><td colspan="1" class="r">Subtotal</td><td class="r">{{ money($order->subtotal) }}</td></tr>
        @if ($order->discount_total)<tr class="tot"><td colspan="4"></td><td class="r">Reducere</td><td class="r">−{{ money($order->discount_total) }}</td></tr>@endif
        <tr class="tot"><td colspan="4"></td><td class="r">Livrare</td><td class="r">{{ money($order->shipping_total) }}</td></tr>
        @if ($order->fee_total)<tr class="tot"><td colspan="4"></td><td class="r">Taxă ramburs</td><td class="r">{{ money($order->fee_total) }}</td></tr>@endif
        <tr class="grand"><td colspan="4"></td><td class="r">Total</td><td class="r">{{ money($order->total) }}</td></tr>
    </tfoot>
    @endif
</table>
@if ($order->customer_note)<p class="note"><b>Mențiunea clientului:</b> {{ $order->customer_note }}</p>@endif
@if ($proforma)<p class="muted" style="margin-top:22px">Document fără valoare fiscală. Factura fiscală, cu TVA-ul aplicabil, se emite la livrare.@if ($d = $s::get('payments.transfer_details'))<br>{!! nl2br(e($d)) !!}@endif</p>@else
<p class="muted" style="margin-top:22px">Pregătit de: __________________ &nbsp; Verificat: __________________ &nbsp; Colete: ____</p>@endif
</body></html>
