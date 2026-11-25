<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/** Setari editabile din admin, cu valori implicite luate din magazinul WooCommerce existent. */
class Settings
{
    public const DEFAULTS = [
        // Datele magazinului (afisate in subsol, pe facturi si in emailuri).
        'shop.name' => 'Pet Market',
        'shop.byline' => 'by HiKeVet',
        'shop.legal_name' => 'FEST4PETS SRL',
        'shop.cui' => '46349603',
        'shop.reg_com' => 'J2022001060265',
        'shop.tagline' => 'Pentru că nu e doar un animal — e familie. Hrană, diete veterinare și accesorii alese de medicii HiKeVet.',
        'shop.phone' => '+40 771 280 345',
        'shop.email' => 'office@pet-market.ro',
        'shop.address' => 'Str. Băneasa 23, Spațiu Comercial 2',
        'shop.postcode' => '540198',
        'shop.city' => 'Târgu Mureș',
        'shop.county' => 'Mureș',
        'shop.hours' => 'Suport online 09:00 – 16:30',
        'shop.instagram' => 'https://www.instagram.com/petmarket.ro/',
        'shop.facebook' => 'https://www.facebook.com/profile.php?id=61591455733059',
        'shop.tiktok' => '',

        // Reguli comerciale (valorile se confirma din auditul magazinului vechi, docs/AUDIT_SITE.md).
        'commerce.vat_rate' => 21,
        'commerce.prices_include_vat' => true,
        'commerce.free_shipping_threshold' => 100000, // bani; magazinul vechi: livrare gratuita de la 1.000 lei cu TVA
        'commerce.shipping_vat' => false, // magazinul vechi nu aplica TVA pe livrare
        'commerce.min_order' => 0,
        'commerce.low_stock_threshold' => 3,
        'commerce.allow_backorders' => false,
        'commerce.new_badge_days' => 30,
        'commerce.order_prefix' => 'PM',
        'commerce.guest_checkout' => true,
        'commerce.reviews_enabled' => true,
        'commerce.reviews_need_approval' => true,

        'payments.cod_enabled' => true,
        'payments.cod_fee' => 0,
        'payments.card_enabled' => false,
        'payments.transfer_enabled' => true,
        'payments.transfer_details' => "Beneficiar: FEST4PETS SRL\nCUI: 46349603\nIBAN: RO22 BREL 0002 0042 1262 0100\nBanca: Libra Internet Bank",
        'payments.transfer_note' => 'Fă plata direct în contul nostru bancar și folosește numărul comenzii ca referință. Comanda pleacă după ce plata intră în cont.',
        'payments.stripe_public' => '',

        'mail.shop_recipients' => ['office@pet-market.ro'],

        // Ecolet (platforma de curierat folosita de magazin). Datele de acces se pastreaza criptat.
        'ecolet.enabled' => false,
        'ecolet.client_id' => '',
        'ecolet.username' => '',
        'ecolet.auto_send' => false,
        'ecolet.complete_on_delivery' => true,
        'ecolet.parcel_length' => 30,
        'ecolet.parcel_width' => 20,
        'ecolet.parcel_height' => 15,
        'ecolet.content' => 'Produse pentru animale de companie',
        'ecolet.service' => '',

        'marketing.gtm_id' => '',
        'marketing.ga4_id' => '',
        'marketing.meta_pixel_id' => '',
        'marketing.search_console_verification' => 'Nmi3g_K1aueIq-uEn5Yh-4BUJqez7297a_xu0fxoM2g', // meta-tagul de pe site-ul vechi: Search Console ramane verificat
        'marketing.google_ads_id' => 'AW-16651227502',
        'marketing.announcement' => 'Livrare gratuită la comenzi de peste 1.000 lei · Taxă de livrare 23 lei',
        'commerce.not_online_notice' => 'Acest produs nu se comercializează online. Este un produs pentru care este necesară rețetă medical-veterinară, conform regimului aplicabil. Pentru disponibilitate și pașii următori, sună-ne.',

        'seo.robots_extra' => '',
        'seo.default_description' => 'Pet Market by HiKeVet: hrană, recompense, accesorii și produse de îngrijire pentru câini și pisici, cu livrare în toată țara.',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        return $all[$key] ?? self::DEFAULTS[$key] ?? $default;
    }

    public static function set(string $key, mixed $value, bool $encrypted = false): void
    {
        Setting::updateOrCreate(['key' => $key], [
            'group' => explode('.', $key)[0],
            'value' => ['v' => $encrypted ? Crypt::encryptString(json_encode($value)) : $value],
            'is_encrypted' => $encrypted,
        ]);
        Cache::forget('settings.all');
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        try {
            return Cache::rememberForever('settings.all', function () {
                return Setting::all()->mapWithKeys(function (Setting $s) {
                    $value = $s->value['v'] ?? null;
                    if ($s->is_encrypted && is_string($value)) {
                        $value = json_decode(Crypt::decryptString($value), true);
                    }

                    return [$s->key => $value];
                })->all();
            });
        } catch (\Throwable) {
            // Inainte de instalare tabela nu exista inca.
            return [];
        }
    }

    /** @return list<string> */
    public static function emails(string $key): array
    {
        return array_values(array_filter(array_map('trim', (array) self::get($key, [])), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
    }
}
