@extends('layouts.site')
@section('content')
<div class="wrap page-pad">
    @if ($fresh)
        <div class="thanks">
            <div class="thanks-text">
                <p class="script">Ham-ham! Miau!</p>
                <h1 class="display h-lg">Mulțumim, {{ $order->billing['first_name'] ?? '' }}!</h1>
                <p class="lead">Comanda <b>{{ $order->number }}</b> a ajuns la noi. Ți-am trimis confirmarea pe <b>{{ $order->email }}</b>.</p>
            </div>
            <div class="thanks-pets" aria-hidden="true">@include('partials.mascot-dog')@include('partials.mascot-cat')</div>
        </div>
        @php
            $gaItems = $order->items->map(fn ($i) => ['item_id' => $i->sku ?: (string) $i->product_id, 'item_name' => $i->name, 'price' => $i->unit_price / 100, 'quantity' => $i->qty])->values();
        @endphp
        @push('scripts')
        <script>
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ ecommerce: null });
            window.dataLayer.push({ event: 'purchase', ecommerce: { transaction_id: @js($order->number), value: {{ $order->total / 100 }}, tax: {{ $order->vat_total / 100 }}, shipping: {{ $order->shipping_total / 100 }}, currency: 'RON', coupon: @js($order->coupon_code),
                items: {!! json_encode($gaItems, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) !!} } });
        </script>
        @endpush
    @else
        <h1 class="display h-lg">Comanda {{ $order->number }}</h1>
    @endif

    <ol class="steps steps--done" aria-label="Pașii comenzii"><li>Coș</li><li>Date și livrare</li><li aria-current="step">Confirmare</li></ol>

    <div class="order-grid">
        <section class="card">
            <dl class="order-facts">
                <div><dt>Număr</dt><dd>{{ $order->number }}</dd></div>
                <div><dt>Data</dt><dd>{{ $order->created_at->format('d.m.Y H:i') }}</dd></div>
                <div><dt>Status</dt><dd><span class="status status--{{ $order->status->value }}">{{ $order->status->label() }}</span></dd></div>
                <div><dt>Total</dt><dd>{{ money($order->total) }}</dd></div>
                <div><dt>Plată</dt><dd>{{ $order->payment_method->label() }}</dd></div>
            </dl>
            @if ($order->payment_method->value === 'transfer' && $order->payment_status->value !== 'paid')
                <div class="notice notice--warn">
                    <p><b>Plata prin transfer bancar</b></p>
                    <p>{{ \App\Support\Settings::get('payments.transfer_note') }}</p>
                    @if ($details = \App\Support\Settings::get('payments.transfer_details'))<p>{!! nl2br(e($details)) !!}</p>@endif
                    <p>Referință: <b>{{ $order->number }}</b> · Suma: <b>{{ money($order->total) }}</b></p>
                </div>
            @endif
            @if ($order->tracking_number)
                <p class="notice notice--ok">Coletul a plecat. AWB: <b>{{ $order->tracking_number }}</b>@if ($order->trackingUrl()) · <a href="{{ $order->trackingUrl() }}" rel="noopener" target="_blank">urmărește coletul</a>@endif</p>
            @endif
            @include('account.partials.order-items', ['order' => $order])
        </section>
        <aside class="card">
            <h2 class="h-sm">Livrare</h2>
            <p>{{ trim(($order->shipping['first_name'] ?? '').' '.($order->shipping['last_name'] ?? '')) }}<br>{{ \App\Models\Order::addressLine($order->shipping) }}<br>{{ $order->phone }}</p>
            <p class="muted">{{ $order->shipping_method_name }}</p>
            <h2 class="h-sm">Facturare</h2>
            <p>@if ($order->customer_type === 'pj'){{ $order->billing['company'] ?? '' }}<br>CUI {{ $order->billing['cui'] ?? '' }}<br>@endif{{ trim(($order->billing['first_name'] ?? '').' '.($order->billing['last_name'] ?? '')) }}<br>{{ \App\Models\Order::addressLine($order->billing) }}</p>
            @if ($order->events->isNotEmpty())
                <h2 class="h-sm">Istoric</h2>
                <ul class="timeline">@foreach ($order->events as $e)<li><time>{{ $e->created_at->format('d.m H:i') }}</time> {{ $e->to_status ? \App\Enums\OrderStatus::from($e->to_status)->label() : $e->message }}</li>@endforeach</ul>
            @endif
            <p><a class="btn btn-ghost btn-sm" href="{{ site_url('/magazin/') }}">Înapoi la magazin</a></p>
        </aside>
    </div>
</div>
@endsection
