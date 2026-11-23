<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Order;

/** Documente de tiparit din pagina comenzii: factura proforma si lista de picking (aviz). */
class OrderDocumentController extends Controller
{
    public function show(Order $order, string $kind)
    {
        abort_unless(auth()->user()?->can(Permission::ORDERS_VIEW), 403);
        $order->load('items.product');

        return view('documents.order', ['order' => $order, 'kind' => $kind]);
    }
}
