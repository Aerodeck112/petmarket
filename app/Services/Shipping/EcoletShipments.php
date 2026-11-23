<?php

namespace App\Services\Shipping;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Shop\Counties;
use App\Services\Shop\OrderService;
use App\Support\Settings;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Fluxul Ecolet al magazinului: comanda se trimite in „Comenzi de trimis” din panoul Ecolet (cu adresa,
 * greutatea, rambursul), acolo se alege curierul si se genereaza AWB-ul, iar sincronizarea aduce AWB-ul
 * si statusurile inapoi in comanda: Expediata cand apare AWB-ul, Finalizata la livrare.
 */
class EcoletShipments
{
    public function __construct(private EcoletClient $client, private OrderService $orders) {}

    /** Datele coletului pentru Ecolet (schema „Order Body”). */
    public function payload(Order $order): array
    {
        $s = $order->shipping;
        $countyName = $s['county'] ?? Counties::name($s['county_code'] ?? null) ?? '';
        $receiverLocality = $this->client->localityId((string) ($s['city'] ?? ''), $countyName);
        if (! $receiverLocality) {
            throw new InvalidArgumentException('Ecolet nu a găsit localitatea „'.($s['city'] ?? '').'”, județul '.$countyName.'. Corectează adresa de livrare din „Mai mult → Modifică adresele”.');
        }
        $senderLocality = $this->client->localityId((string) Settings::get('shop.city'), (string) Settings::get('shop.county'));
        if (! $senderLocality) {
            throw new InvalidArgumentException('Ecolet nu a găsit localitatea magazinului. Verifică adresa din Setări magazin.');
        }

        [$street, $number] = self::splitStreet((string) ($s['address_1'] ?? ''));
        [$shopStreet, $shopNumber] = self::splitStreet((string) Settings::get('shop.address'));
        $grams = (int) $order->items->sum(fn ($i) => (int) (($i->variant?->weight_grams ?? $i->product?->weight_grams) ?? 0) * $i->qty);
        $cod = $order->payment_method->value === 'cod' && $order->payment_status->value !== 'paid';
        $name = trim(($s['first_name'] ?? '').' '.($s['last_name'] ?? ''));

        return [
            'sender' => [
                'name' => Str::limit((string) Settings::get('shop.legal_name'), 60, ''), 'country' => 'ro', 'county' => Settings::get('shop.county'),
                'locality_id' => $senderLocality, 'locality' => Settings::get('shop.city'), 'postal_code' => (string) (Settings::get('shop.postcode') ?: '000000'),
                'street_name' => Str::limit($shopStreet, 50, ''), 'street_number' => Str::limit($shopNumber ?: '-', 10, ''),
                'contact_person' => Str::limit((string) Settings::get('shop.name'), 60, ''), 'email' => Settings::get('shop.email'),
                'phone' => self::phone((string) Settings::get('shop.phone')), 'has_map_point' => false, 'map_point_id' => null,
            ],
            'receiver' => [
                'name' => Str::limit($order->customer_type === 'pj' && ! empty($order->billing['company']) ? $order->billing['company'] : $name, 60, ''),
                'country' => 'ro', 'county' => $countyName, 'locality_id' => $receiverLocality, 'locality' => $s['city'] ?? '',
                'postal_code' => (string) ($s['postcode'] ?? '') ?: '000000',
                'street_name' => Str::limit($street, 50, ''), 'street_number' => Str::limit($number ?: '-', 10, ''),
                'block' => ! empty($s['address_2']) ? Str::limit((string) $s['address_2'], 10, '') : null,
                'contact_person' => Str::limit($name, 60, ''), 'email' => $order->email, 'phone' => self::phone($order->phone),
                'has_map_point' => false, 'map_point_id' => null,
            ],
            'parcel' => [
                'type' => 'package', 'weight' => max(1, (int) ceil($grams / 1000)), 'shape' => 'standard', 'amount' => 1,
                'dimensions' => ['length' => (int) Settings::get('ecolet.parcel_length'), 'width' => (int) Settings::get('ecolet.parcel_width'), 'height' => (int) Settings::get('ecolet.parcel_height')],
                'content' => Str::limit((string) Settings::get('ecolet.content'), 100, ''), 'declared_value' => null,
                'observations' => Str::limit('Comanda '.$order->number.(! empty($s['address_2']) ? ' · '.$s['address_2'] : '').($order->customer_note ? ' · '.$order->customer_note : ''), 250, ''),
            ],
            'additional_services' => [
                'cod' => ['status' => $cod, 'amount' => $cod ? round($order->total / 100, 2) : null],
                'open_package' => ['status' => false], 'rod' => ['status' => false, 'rod_code' => null], 'rop' => ['status' => false],
                'saturday_delivery' => ['status' => false], 'sms_notify' => ['status' => false], 'swap' => ['status' => false], 'epod' => ['status' => false],
            ],
        ] + (filled(Settings::get('ecolet.service')) ? ['courier' => ['service' => Settings::get('ecolet.service'), 'pickup' => ['type' => 'courier']]] : []);
    }

    /** Trimite comanda in „Comenzi de trimis” din Ecolet. */
    public function push(Order $order): int
    {
        if ($order->ecolet_order_to_send_id) {
            throw new InvalidArgumentException('Comanda a fost deja trimisă la Ecolet (#'.$order->ecolet_order_to_send_id.').');
        }
        $order->loadMissing('items.product', 'items.variant');
        $response = $this->client->send('POST', '/add-parcel/save-order-to-send', $this->payload($order));
        if (! $response->successful() || ! $response->json('order_to_send_id')) {
            $errors = collect((array) $response->json('errors'))->flatten()->implode(' ') ?: ($response->json('general_error') ?: $response->json('message'));
            throw new RuntimeException('Ecolet a refuzat comanda ('.$response->status().'): '.($errors ?: 'eroare necunoscută').'.');
        }
        $id = (int) $response->json('order_to_send_id');
        $order->update(['ecolet_order_to_send_id' => $id, 'ecolet_status' => 'trimisă la Ecolet', 'ecolet_synced_at' => now()]);
        $order->events()->create(['type' => 'shipping', 'message' => 'Comanda a fost trimisă la Ecolet (comandă de trimis #'.$id.').', 'user_id' => auth()->id()]);

        return $id;
    }

    /**
     * Aduce din Ecolet AWB-ul si statusul. Returneaza true daca s-a schimbat ceva.
     */
    public function sync(Order $order): bool
    {
        $changed = false;
        if ($order->ecolet_order_to_send_id && ! $order->ecolet_order_id) {
            $ots = $this->client->send('GET', '/order-to-send/'.$order->ecolet_order_to_send_id)->json('order_to_send', []);
            if (($ots['status'] ?? null) === 'error') {
                $order->update(['ecolet_status' => 'eroare: '.Str::limit((string) ($ots['error'] ?? ''), 60), 'ecolet_synced_at' => now()]);

                return true;
            }
            if (($ots['status'] ?? null) === 'ordered' && ! empty($ots['order_id'])) {
                $order->update(['ecolet_order_id' => (int) $ots['order_id']]);
                $changed = true;
            }
        }
        if (! $order->ecolet_order_id) {
            $order->update(['ecolet_synced_at' => now()]);

            return $changed;
        }

        $data = $this->client->send('GET', '/order/'.$order->ecolet_order_id)->json('data', []);
        $statuses = collect($data['statuses'] ?? []);
        $last = $statuses->last();
        $statusLabel = $last['real_name'] ?? $last['name'] ?? ($data['status'] ?? null);
        $order->update(['ecolet_status' => $statusLabel ? Str::limit($statusLabel, 80, '') : $order->ecolet_status,
            'ecolet_service' => $data['service']['full_name'] ?? $order->ecolet_service, 'ecolet_synced_at' => now()]);

        $awb = $data['awb'] ?? null;
        if ($awb && $awb !== $order->tracking_number) {
            $carrier = self::carrier((string) ($data['service']['courier_slug'] ?? ''));
            $this->orders->setTracking($order, $carrier, $awb, in_array(OrderStatus::Shipped, $order->status->next(), true));
            $changed = true;
        }

        $delivered = $statuses->contains(fn ($st) => preg_match('/deliver|livrat/i', ($st['name'] ?? '').' '.($st['real_name'] ?? '')));
        if ($delivered && Settings::get('ecolet.complete_on_delivery') && in_array(OrderStatus::Completed, $order->fresh()->status->next(), true)) {
            $this->orders->changeStatus($order->fresh(), OrderStatus::Completed, 'Livrată (confirmare Ecolet).');
            $changed = true;
        }

        return $changed;
    }

    /** AWB-ul (PDF) pentru tiparire. */
    public function waybill(Order $order): string
    {
        $response = $this->client->send('GET', '/order/'.$order->ecolet_order_id.'/download-waybill');
        if (! $response->successful()) {
            throw new RuntimeException('AWB-ul nu este încă disponibil în Ecolet.');
        }

        return $response->body();
    }

    /** „Str. Băneasa 23, Spațiu Comercial 2” → [„Str. Băneasa”, „23”]. */
    public static function splitStreet(string $address): array
    {
        $address = trim($address);
        if (preg_match('/^(.*?)[,\s]+(?:nr\.?|număr|numar)\s*([0-9][0-9A-Za-z\/\-]*)/iu', $address, $m)) {
            return [trim($m[1], " ,"), $m[2]];
        }
        if (preg_match('/^(.*?\D)\s+([0-9][0-9A-Za-z\/\-]*)\b/u', $address, $m)) {
            return [trim($m[1], " ,"), $m[2]];
        }

        return [$address, ''];
    }

    public static function phone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (str_starts_with($digits, '40') && strlen($digits) === 11) {
            $digits = '0'.substr($digits, 2);
        }

        return substr($digits, 0, 15);
    }

    public static function carrier(string $slug): string
    {
        foreach (['sameday' => 'sameday', 'fan' => 'fan', 'cargus' => 'cargus', 'dpd' => 'dpd', 'gls' => 'gls'] as $needle => $code) {
            if (str_contains(strtolower($slug), $needle)) {
                return $code;
            }
        }

        return $slug ?: 'ecolet';
    }
}
