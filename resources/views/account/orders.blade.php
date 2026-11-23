@extends('account._layout')
@section('account')
<h1 class="display h-md">Comenzile mele</h1>
@forelse ($orders as $order)
    @php $order->load('items'); @endphp
    @include('account.partials.order-row', ['order' => $order])
@empty
    <div class="empty empty--sm">@include('partials.mascot-cat')<p>Încă nu ai comenzi.</p></div>
@endforelse
{{ $orders->links('partials.pagination') }}
@endsection
