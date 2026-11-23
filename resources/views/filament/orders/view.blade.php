<x-filament-panels::page>
@php
    /** @var \App\Models\Order $order */
    $order = $this->record->load(['items.product', 'events.user', 'payments', 'user']);
    $addr = fn ($a) => \App\Models\Order::addressLine($a);
    $name = fn ($a) => trim(($a['first_name'] ?? '').' '.($a['last_name'] ?? ''));
@endphp
<div class="pm-order">
    <div class="pm-order-top">
        <span class="pm-pill pm-pill--{{ $order->status->color() }}"><x-filament::icon :icon="$order->status->icon()" class="h-4 w-4" /> {{ $order->status->label() }}</span>
        <span class="pm-pill pm-pill--{{ $order->payment_status->color() }}">{{ $order->payment_status->label() }} · {{ $order->payment_method->label() }}</span>
        <span class="pm-muted">Plasată {{ $order->created_at->format('d.m.Y, H:i') }} ({{ $order->created_at->diffForHumans() }}) din {{ $order->source === 'admin' ? 'administrare' : 'site' }}</span>
        @if ($order->ecolet_order_to_send_id)<span class="pm-pill pm-pill--primary">Ecolet: {{ $order->ecolet_status ?: 'trimisă' }}@if ($order->ecolet_service) · {{ $order->ecolet_service }}@endif @if ($order->ecolet_synced_at)<span class="pm-muted">({{ $order->ecolet_synced_at->diffForHumans() }})</span>@endif</span>@endif
        @if ($order->tracking_number)<span class="pm-pill pm-pill--warning">AWB {{ $order->tracking_number }}@if ($order->trackingUrl()) · <a href="{{ $order->trackingUrl() }}" target="_blank" rel="noopener">urmărește</a>@endif</span>@endif
    </div>

    <div class="pm-order-grid">
        <div class="pm-stack">
            <section class="pm-card">
                <h3>Produse ({{ $order->items->sum('qty') }})</h3>
                <table class="pm-table">
                    <thead><tr><th></th><th>Produs</th><th class="r">Preț</th><th class="r">Cant.</th><th class="r">Total</th></tr></thead>
                    <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td class="pm-thumb">@if ($item->image)<img src="{{ $item->image }}" alt="">@endif</td>
                            <td>
                                @if ($item->product)<a href="{{ \App\Filament\Resources\Products\ProductResource::getUrl('edit', ['record' => $item->product_id]) }}" class="pm-link">{{ $item->name }}</a>@else{{ $item->name }}@endif
                                @if ($item->variant_label)<div class="pm-muted">{{ $item->variant_label }}</div>@endif
                                <div class="pm-muted">SKU {{ $item->sku ?: '—' }}</div>
                            </td>
                            <td class="r">{{ money($item->unit_price) }}@if ($item->regular_unit_price > $item->unit_price)<div class="pm-muted"><s>{{ money($item->regular_unit_price) }}</s></div>@endif</td>
                            <td class="r"><b>× {{ $item->qty }}</b></td>
                            <td class="r">{{ money($item->total) }}@if ($item->discount)<div class="pm-muted">−{{ money($item->discount) }} cupon</div>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <dl class="pm-totals">
                    <div><dt>Subtotal</dt><dd>{{ money($order->subtotal) }}</dd></div>
                    @if ($order->discount_total)<div><dt>Reducere @if ($order->coupon_code)<span class="pm-code">{{ $order->coupon_code }}</span>@endif</dt><dd>−{{ money($order->discount_total) }}</dd></div>@endif
                    <div><dt>Livrare · {{ $order->shipping_method_name }}</dt><dd>{{ $order->shipping_total ? money($order->shipping_total) : 'gratuită' }}</dd></div>
                    @if ($order->fee_total)<div><dt>Taxă ramburs</dt><dd>{{ money($order->fee_total) }}</dd></div>@endif
                    <div class="pm-grand"><dt>Total</dt><dd>{{ money($order->total) }}</dd></div>
                    @if ($order->refunded_total)<div><dt>Rambursat</dt><dd>−{{ money($order->refunded_total) }}</dd></div>@endif
                </dl>
            </section>

            @if ($order->customer_note)
                <section class="pm-card pm-card--note"><h3>Mențiunea clientului</h3><p>{{ $order->customer_note }}</p></section>
            @endif

            <section class="pm-card">
                <h3>Istoric</h3>
                <ol class="pm-timeline">
                    @foreach ($order->events as $e)
                        <li class="pm-ev pm-ev--{{ $e->type }}">
                            <span class="pm-ev-dot"></span>
                            <div>
                                <p>
                                    @if ($e->to_status)<b>{{ $e->from_status ? \App\Enums\OrderStatus::from($e->from_status)->label().' → ' : '' }}{{ \App\Enums\OrderStatus::from($e->to_status)->label() }}</b>@endif
                                    @if ($e->message) {{ $e->message }}@endif
                                </p>
                                <span class="pm-muted">{{ $e->created_at->format('d.m.Y H:i') }} · {{ $e->user?->name ?? 'sistem' }}@if ($e->type === 'customer_note') · trimis clientului @elseif ($e->type === 'note') · notă internă @endif</span>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>

        <aside class="pm-stack">
            <section class="pm-card">
                <h3>Client</h3>
                <p class="pm-big">{{ $order->customerName() }}</p>
                @if ($order->customer_type === 'pj')<p><span class="pm-pill pm-pill--info">Persoană juridică</span> {{ $order->billing['company'] ?? '' }}<br>CUI {{ $order->billing['cui'] ?? '—' }} @if (! empty($order->billing['reg_com']))· {{ $order->billing['reg_com'] }}@endif</p>@endif
                <p><a class="pm-link" href="mailto:{{ $order->email }}">{{ $order->email }}</a><br><a class="pm-link" href="tel:{{ preg_replace('/[^0-9+]/', '', $order->phone) }}">{{ $order->phone }}</a></p>
                @if ($order->user)
                    <p class="pm-muted">Cont: {{ $order->user->orders_count }} comenzi, {{ money($order->user->total_spent) }} în total ·
                        <a class="pm-link" href="{{ \App\Filament\Resources\Customers\CustomerResource::getUrl('edit', ['record' => $order->user]) }}">fișa clientului</a></p>
                @else
                    <p class="pm-muted">Comandă fără cont</p>
                @endif
                @if ($order->marketing_opt_in)<p class="pm-muted">✓ acord pentru newsletter</p>@endif
            </section>
            <section class="pm-card">
                <h3>Livrare</h3>
                <p><b>{{ $name($order->shipping) }}</b><br>{{ $addr($order->shipping) }}</p>
                <p class="pm-muted">{{ $order->shipping_method_name }}</p>
                <a class="pm-link" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query={{ urlencode($addr($order->shipping)) }}">Vezi pe hartă</a>
            </section>
            <section class="pm-card">
                <h3>Facturare</h3>
                <p><b>{{ $name($order->billing) }}</b><br>{{ $addr($order->billing) }}</p>
                @if ($order->invoice_number)<p>Factura <b>{{ $order->invoice_number }}</b>@if ($order->invoice_url) · <a class="pm-link" href="{{ $order->invoice_url }}" target="_blank">deschide</a>@endif</p>@endif
            </section>
            @if ($order->payments->isNotEmpty())
                <section class="pm-card">
                    <h3>Plăți</h3>
                    @foreach ($order->payments as $p)<p>{{ money($p->amount) }} · {{ $p->provider }} · {{ $p->status }}<br><span class="pm-muted">{{ $p->created_at->format('d.m.Y H:i') }} {{ $p->provider_ref }}</span></p>@endforeach
                </section>
            @endif
            <section class="pm-card pm-card--hiko">
                <img src="{{ asset('media/personaje/pisica-si-caine.png') }}" alt="" width="128" height="80">
                <p>@switch($order->status->value)
                    @case('processing') Hiko zice: împachetează și pune AWB-ul. @break
                    @case('on_hold') Raza așteaptă plata prin transfer. Verifică extrasul. @break
                    @case('pending') Plata cu cardul nu e confirmată încă. @break
                    @case('shipped') Coletul e pe drum. Când ajunge, marchează „Finalizată”. @break
                    @case('completed') Gata! Încă un client mulțumit. Ham! @break
                    @default Nimic de făcut aici. Miau.
                @endswitch</p>
            </section>
        </aside>
    </div>
</div>
</x-filament-panels::page>
