<?php

namespace App\Services\Shop;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Notifications\Notifier;
use App\Support\Settings;

/** Emailurile comenzii, cu textele magazinului vechi adaptate. Un email esuat nu opreste comanda. */
class OrderMailer
{
    public function __construct(private Notifier $notifier) {}

    public function placed(Order $order): void
    {
        $intro = match ($order->payment_method->value) {
            'transfer' => 'Am primit comanda ta. O pregătim imediat ce primim plata prin transfer bancar; datele pentru plată sunt mai jos.',
            'card' => 'Am primit comanda ta. Imediat ce plata cu cardul este confirmată, începem pregătirea coletului.',
            default => 'Am primit comanda ta și o pregătim pentru livrare. Vei plăti curierului la primirea coletului.',
        };
        $this->toCustomer($order, 'Comanda '.$order->number.' a fost primită', $intro, 'order.placed');
        $this->notifier->send(Settings::emails('mail.shop_recipients'), 'Comandă nouă '.$order->number.' – '.money($order->total),
            view('mail.order', ['order' => $order, 'intro' => 'Comandă nouă de la '.$order->customerName().' ('.$order->email.', '.$order->phone.').', 'forShop' => true])->render(),
            'order.admin', 'order', $order->id);
    }

    public function statusChanged(Order $order, OrderStatus $from): void
    {
        [$subject, $intro] = match ($order->status) {
            OrderStatus::Processing => ['Comanda '.$order->number.' este în pregătire', 'Comanda ta a fost confirmată și este în pregătire.'],
            OrderStatus::Shipped => ['Comanda '.$order->number.' a plecat spre tine', 'Coletul tău a fost predat curierului.'.($order->tracking_number ? ' Numărul AWB este '.$order->tracking_number.'.' : '')],
            OrderStatus::Completed => ['Comanda '.$order->number.' a fost finalizată', 'Îți mulțumim pentru comandă! Sperăm ca Hiko și Raza să fi avut dreptate cu alegerile tale.'],
            OrderStatus::Cancelled => ['Comanda '.$order->number.' a fost anulată', 'Comanda ta a fost anulată. Dacă nu ai cerut tu acest lucru, te rugăm să ne contactezi.'],
            OrderStatus::Refunded => ['Comanda '.$order->number.' a fost rambursată', 'Suma de '.money($order->refunded_total).' a fost rambursată.'],
            default => [null, null],
        };
        if ($subject) {
            $this->toCustomer($order, $subject, $intro, 'order.status');
        }
    }

    public function customerNote(Order $order, string $message): void
    {
        $this->toCustomer($order, 'Mesaj despre comanda '.$order->number, nl2br(e($message)), 'order.note', false);
    }

    private function toCustomer(Order $order, string $subject, string $intro, string $context, bool $escape = true): void
    {
        $html = view('mail.order', ['order' => $order, 'intro' => $escape ? e($intro) : $intro, 'forShop' => false])->render();
        $this->notifier->send([$order->email], $subject, $html, $context, 'order', $order->id, $order->user_id);
    }
}
