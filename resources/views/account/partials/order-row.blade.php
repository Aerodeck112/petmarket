<a class="order-row" href="{{ site_url('/contul-meu/comenzi/'.$order->number.'/') }}">
    <span class="order-row-no"><b>{{ $order->number }}</b><small>{{ $order->created_at->format('d.m.Y') }}</small></span>
    <span class="order-row-imgs">@foreach ($order->items->take(4) as $i)@if ($i->image)<img src="{{ $i->image }}" alt="" width="40" height="40" loading="lazy">@endif @endforeach</span>
    <span class="status status--{{ $order->status->value }}">{{ $order->status->label() }}</span>
    <b class="order-row-total">{{ money($order->total) }}</b>
    <x-hv-icon name="chevron" width="18" height="18" />
</a>
