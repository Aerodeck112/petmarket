<?php

namespace App\Services\Shop;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Ciclul de viata al comenzii: plasare (in tranzactie, cu stocul blocat), schimbari de status cu
 * restituirea stocului, plati, AWB si note. Totalurile se calculeaza o singura data, la plasare,
 * din PricingEngine; dupa aceea comanda nu mai depinde de preturile curente.
 */
class OrderService
{
    public function __construct(private PricingEngine $pricing, private StockService $stock, private OrderMailer $mailer) {}

    /**
     * @param  list<Line>  $lines
     * @param  array{email:string,phone:string,customer_type:string,billing:array,shipping:array,note:?string,marketing:bool}  $customer
     */
    public function place(array $lines, ?string $couponCode, ShippingMethod $method, PaymentMethod $payment, array $customer, ?User $user = null, string $source = 'site'): Order
    {
        if ($lines === []) {
            throw new InvalidArgumentException('Coșul este gol.');
        }

        $order = DB::transaction(function () use ($lines, $couponCode, $method, $payment, $customer, $user, $source) {
            // Produsele se recitesc blocate: doua comenzi simultane nu pot vinde aceeasi ultima bucata.
            $fresh = [];
            foreach ($lines as $line) {
                $product = Product::query()->with('categories')->lockForUpdate()->findOrFail($line->product->id);
                $variant = $line->variant ? ProductVariant::query()->lockForUpdate()->findOrFail($line->variant->id) : null;
                $variant?->setRelation('product', $product);
                $fresh[] = new Line($product, $variant, $line->qty);
            }
            $county = $customer['shipping']['county'] ?? null;
            $quote = $this->pricing->quote($fresh, $couponCode, $method, $payment, $customer['email'], $user?->id, $county);
            if ($quote->couponError) {
                throw new InvalidArgumentException($quote->couponError);
            }
            if ($quote->problems) {
                throw new InvalidArgumentException(implode(' ', $quote->problems));
            }

            $order = Order::query()->create([
                'number' => $this->nextNumber(),
                'token' => Str::random(48),
                'user_id' => $user?->id,
                'status' => $payment === PaymentMethod::Card ? OrderStatus::Pending : ($payment === PaymentMethod::Transfer ? OrderStatus::OnHold : OrderStatus::Processing),
                'payment_status' => PaymentStatus::Unpaid,
                'payment_method' => $payment,
                'shipping_method_id' => $method->id,
                'shipping_method_name' => $method->name,
                'carrier' => $method->carrier,
                'subtotal' => $quote->subtotal, 'discount_total' => $quote->discount, 'shipping_total' => $quote->shipping,
                'fee_total' => $quote->fee, 'vat_total' => $quote->vat, 'total' => $quote->total,
                'coupon_code' => $quote->coupon?->code,
                'customer_type' => $customer['customer_type'] === 'pj' ? 'pj' : 'pf',
                'email' => mb_strtolower($customer['email']), 'phone' => $customer['phone'],
                'billing' => $customer['billing'], 'shipping' => $customer['shipping'],
                'customer_note' => $customer['note'] ?? null,
                'marketing_opt_in' => (bool) ($customer['marketing'] ?? false),
                'ip' => request()?->ip(), 'user_agent' => mb_substr((string) request()?->userAgent(), 0, 300),
                'source' => $source, 'accepted_terms_at' => now(),
            ]);

            foreach ($quote->lines as $line) {
                $order->items()->create([
                    'product_id' => $line->product->id, 'product_variant_id' => $line->variant?->id, 'sku' => $line->sku(),
                    'name' => $line->product->name, 'variant_label' => $line->label(), 'image' => $line->image(),
                    'qty' => $line->qty, 'unit_price' => $line->unitPrice(), 'regular_unit_price' => $line->regularUnitPrice(),
                    'discount' => $line->discount, 'total' => $line->total(), 'vat_rate' => $line->product->vatRate(),
                    'vat_amount' => PricingEngine::vatFromGross($line->total(), $line->product->vatRate()),
                ]);
                $this->stock->adjust($line->product, $line->variant, -$line->qty, 'order', $order);
                $line->product->increment('sold_count', $line->qty);
            }
            $order->update(['stock_reduced' => true]);

            if ($quote->coupon) {
                Coupon::query()->whereKey($quote->coupon->id)->increment('used_count');
                CouponUsage::query()->create(['coupon_id' => $quote->coupon->id, 'order_id' => $order->id, 'user_id' => $user?->id,
                    'email' => $order->email, 'discount' => $quote->discount]);
            }

            $this->event($order, 'status', null, $order->status->value, 'Comandă plasată din '.($source === 'admin' ? 'administrare' : 'site').'.');

            return $order;
        });

        $user?->refreshOrderStats();
        $this->mailer->placed($order->fresh(['items']));

        return $order;
    }

    public function changeStatus(Order $order, OrderStatus $to, ?string $note = null, bool $notify = true): Order
    {
        $from = $order->status;
        if ($from === $to) {
            return $order;
        }
        if (! in_array($to, $from->next(), true)) {
            throw new InvalidArgumentException('Comanda nu poate trece din „'.$from->label().'” în „'.$to->label().'”.');
        }

        DB::transaction(function () use ($order, $from, $to, $note) {
            // Stocul revine la anulare / esec / rambursare si se ia din nou la reactivare.
            if ($from->holdsStock() && ! $to->holdsStock() && $order->stock_reduced) {
                $this->restock($order, $to === OrderStatus::Refunded ? 'refund' : 'cancel');
                $order->stock_reduced = false;
            } elseif (! $from->holdsStock() && $to->holdsStock() && ! $order->stock_reduced) {
                foreach ($order->items as $item) {
                    if ($item->product) {
                        $this->stock->adjust($item->product, $item->variant, -$item->qty, 'order', $order, 'Comandă reactivată');
                    }
                }
                $order->stock_reduced = true;
            }

            $order->status = $to;
            match ($to) {
                OrderStatus::Shipped => $order->shipped_at ??= now(),
                OrderStatus::Completed => $order->completed_at = now(),
                OrderStatus::Cancelled, OrderStatus::Failed => $order->cancelled_at = now(),
                default => null,
            };
            // La ramburs, comanda finalizata inseamna ca banii au fost incasati de curier.
            if ($to === OrderStatus::Completed && $order->payment_status === PaymentStatus::Unpaid) {
                $order->payment_status = PaymentStatus::Paid;
                $order->paid_at ??= now();
            }
            if ($to === OrderStatus::Refunded) {
                $order->payment_status = PaymentStatus::Refunded;
                $order->refunded_total = $order->total;
            }
            $order->save();
            $this->event($order, 'status', $from->value, $to->value, $note);
            Audit::log('order.status', $order, 'Comanda '.$order->number.': '.$from->label().' → '.$to->label());
        });

        $order->user?->refreshOrderStats();
        if ($notify) {
            $this->mailer->statusChanged($order->fresh(['items']), $from);
        }

        return $order;
    }

    public function markPaid(Order $order, string $provider, ?string $reference = null, ?array $payload = null): void
    {
        if ($order->payment_status === PaymentStatus::Paid) {
            return;
        }
        $order->payments()->create(['provider' => $provider, 'provider_ref' => $reference, 'amount' => $order->total, 'status' => 'succeeded', 'payload' => $payload, 'user_id' => auth()->id()]);
        $order->update(['payment_status' => PaymentStatus::Paid, 'paid_at' => now()]);
        $this->event($order, 'payment', null, null, 'Plată încasată ('.$provider.($reference ? ', '.$reference : '').'): '.money($order->total).'.');
        if (in_array($order->status, [OrderStatus::Pending, OrderStatus::OnHold], true)) {
            $this->changeStatus($order, OrderStatus::Processing, 'Plata a fost confirmată.');
        }
    }

    public function setTracking(Order $order, string $carrier, string $awb, bool $markShipped = true): void
    {
        $order->update(['carrier' => $carrier, 'tracking_number' => trim($awb)]);
        $this->event($order, 'shipping', null, null, 'AWB '.$awb.' ('.(\App\Models\ShippingMethod::CARRIERS[$carrier] ?? $carrier).').', true);
        if ($markShipped && in_array(OrderStatus::Shipped, $order->status->next(), true)) {
            $this->changeStatus($order, OrderStatus::Shipped);
        }
    }

    public function note(Order $order, string $message, bool $toCustomer = false): OrderEvent
    {
        $event = $this->event($order, $toCustomer ? 'customer_note' : 'note', null, null, $message, $toCustomer);
        if ($toCustomer) {
            $this->mailer->customerNote($order, $message);
        }

        return $event;
    }

    private function restock(Order $order, string $reason): void
    {
        foreach ($order->items as $item) {
            if ($item->product) {
                $this->stock->adjust($item->product, $item->variant, $item->qty, $reason, $order);
                $item->product->decrement('sold_count', min($item->qty, $item->product->sold_count));
            }
        }
    }

    private function event(Order $order, string $type, ?string $from, ?string $to, ?string $message, bool $visible = false): OrderEvent
    {
        return $order->events()->create([
            'type' => $type, 'from_status' => $from, 'to_status' => $to, 'message' => $message,
            'is_customer_visible' => $visible || $type === 'status', 'user_id' => auth()->id(),
        ]);
    }

    /** Numar de comanda consecutiv cu prefix (PM10001), sigur la comenzi simultane. */
    private function nextNumber(): string
    {
        $prefix = (string) Settings::get('commerce.order_prefix');
        $last = Order::withTrashed()->where('number', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('id')->value('number');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 10001;

        return $prefix.$next;
    }
}
