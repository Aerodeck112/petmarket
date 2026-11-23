@extends('account._layout')
@section('account')
<div class="acct-order-head">
    <h1 class="display h-md">Comanda {{ $order->number }}</h1>
    <span class="status status--{{ $order->status->value }}">{{ $order->status->label() }}</span>
</div>
<p class="muted">Plasată pe {{ $order->created_at->format('d.m.Y, H:i') }} · {{ $order->payment_method->label() }} · {{ $order->shipping_method_name }}</p>
@if ($order->tracking_number)<p class="notice notice--ok">AWB: <b>{{ $order->tracking_number }}</b>@if ($order->trackingUrl()) · <a href="{{ $order->trackingUrl() }}" rel="noopener" target="_blank">urmărește coletul</a>@endif</p>@endif
@include('account.partials.order-items', ['order' => $order])
<div class="grid-2 acct-addr">
    <div class="card"><h2 class="h-sm">Livrare</h2><p>{{ trim(($order->shipping['first_name'] ?? '').' '.($order->shipping['last_name'] ?? '')) }}<br>{{ \App\Models\Order::addressLine($order->shipping) }}</p></div>
    <div class="card"><h2 class="h-sm">Istoric</h2><ul class="timeline">@foreach ($order->events as $e)<li><time>{{ $e->created_at->format('d.m H:i') }}</time> {{ $e->to_status ? \App\Enums\OrderStatus::from($e->to_status)->label() : $e->message }}</li>@endforeach</ul></div>
</div>
<form method="post" action="{{ site_url('/contul-meu/comenzi/'.$order->number.'/din-nou') }}">@csrf<button class="btn btn-primary" type="submit"><x-hv-icon name="cart" width="20" height="20" /> Comandă din nou</button></form>
@endsection
