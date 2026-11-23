{{-- Rezumatul comenzii din emailuri (tabele simple, compatibile cu clientii de email). --}}
@php $s = \App\Support\Settings::class; @endphp
<p style="margin:0 0 18px;">{!! $intro !!}</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
    <tr><td colspan="3" style="padding:0 0 8px;font-weight:bold;color:#1F1638;">Comanda {{ $order->number }} · {{ $order->created_at->format('d.m.Y H:i') }}</td></tr>
    @foreach ($order->items as $item)
        <tr style="border-top:1px solid #E6E3F0;">
            <td style="padding:10px 8px 10px 0;width:56px;">@if ($item->image)<img src="{{ $item->image }}" width="48" height="48" alt="" style="display:block;border-radius:8px;object-fit:cover;">@endif</td>
            <td style="padding:10px 8px;">{{ $item->name }}@if ($item->variant_label)<br><span style="color:#6B6580;">{{ $item->variant_label }}</span>@endif<br><span style="color:#6B6580;">{{ $item->qty }} × {{ money($item->unit_price) }}</span></td>
            <td align="right" style="padding:10px 0;white-space:nowrap;">{{ money($item->total) }}</td>
        </tr>
    @endforeach
    <tr style="border-top:2px solid #1F1638;"><td></td><td style="padding:10px 8px 4px;">Subtotal</td><td align="right" style="padding:10px 0 4px;">{{ money($order->subtotal) }}</td></tr>
    @if ($order->discount_total)<tr><td></td><td style="padding:4px 8px;">Reducere{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : '' }}</td><td align="right" style="padding:4px 0;">−{{ money($order->discount_total) }}</td></tr>@endif
    <tr><td></td><td style="padding:4px 8px;">Livrare: {{ $order->shipping_method_name }}</td><td align="right" style="padding:4px 0;">{{ $order->shipping_total ? money($order->shipping_total) : 'gratuită' }}</td></tr>
    @if ($order->fee_total)<tr><td></td><td style="padding:4px 8px;">Taxă ramburs</td><td align="right" style="padding:4px 0;">{{ money($order->fee_total) }}</td></tr>@endif
    <tr><td></td><td style="padding:8px 8px;font-weight:bold;font-size:16px;color:#1F1638;">Total</td><td align="right" style="padding:8px 0;font-weight:bold;font-size:16px;color:#1F1638;">{{ money($order->total) }}</td></tr>
    <tr><td></td><td colspan="2" style="padding:0 8px 8px;color:#6B6580;font-size:12px;">din care TVA {{ money($order->vat_total) }} · plată: {{ $order->payment_method->label() }}</td></tr>
</table>
@if ($order->payment_method->value === 'transfer' && ! $forShop && $s::get('payments.transfer_details'))
    <p style="margin:16px 0 0;padding:14px;background:#FFF6D6;border-radius:10px;"><strong>Date pentru transfer:</strong><br>{!! nl2br(e($s::get('payments.transfer_details'))) !!}<br>La detalii plată scrie: <strong>{{ $order->number }}</strong></p>
@endif
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:18px;font-size:14px;">
    <tr>
        <td style="vertical-align:top;padding-right:10px;width:50%;"><strong>Livrare</strong><br>{{ trim(($order->shipping['first_name'] ?? '').' '.($order->shipping['last_name'] ?? '')) }}<br>{{ \App\Models\Order::addressLine($order->shipping) }}<br>{{ $order->phone }}</td>
        <td style="vertical-align:top;"><strong>Facturare</strong><br>@if ($order->customer_type === 'pj'){{ $order->billing['company'] ?? '' }}, CUI {{ $order->billing['cui'] ?? '' }}<br>@endif{{ trim(($order->billing['first_name'] ?? '').' '.($order->billing['last_name'] ?? '')) }}<br>{{ \App\Models\Order::addressLine($order->billing) }}</td>
    </tr>
</table>
@if ($order->tracking_number && $order->trackingUrl())
    <p style="margin:18px 0 0;"><a href="{{ $order->trackingUrl() }}" style="display:inline-block;background:#FFC21F;color:#1F1638;padding:12px 20px;border-radius:999px;font-weight:bold;text-decoration:none;border:2px solid #1F1638;">Urmărește coletul</a></p>
@endif
@if ($forShop)
    <p style="margin:18px 0 0;"><a href="{{ rtrim(config('app.url'), '/') }}/admin/orders/{{ $order->id }}" style="color:#1E3BA8;">Deschide comanda în administrare</a></p>
    @if ($order->customer_note)<p style="margin:10px 0 0;"><strong>Notă client:</strong> {{ $order->customer_note }}</p>@endif
@else
    <p style="margin:18px 0 0;"><a href="{{ $order->publicUrl() }}" style="color:#1E3BA8;">Vezi comanda online</a></p>
@endif
