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
        'shop.legal_name' => 'S.C. FEST4PETS S.R.L.',
        'shop.cui' => '',
        'shop.reg_com' => '',
        'shop.tagline' => 'Hrană, accesorii și grijă pentru animalul tău, alese de medici veterinari.',
        'shop.phone' => '+40 771 280 345',
        'shop.email' => 'contact@pet-market.ro',
        'shop.address' => 'Str. Băneasa nr. 23',
        'shop.city' => 'Târgu Mureș',
        'shop.county' => 'Mureș',
        'shop.hours' => 'Luni – Vineri 09:00 – 18:00',
        'shop.instagram' => '',
        'shop.facebook' => '',
        'shop.tiktok' => '',

        // Reguli comerciale (valorile se confirma din auditul magazinului vechi, docs/AUDIT_SITE.md).
        'commerce.vat_rate' => 21,
        'commerce.prices_include_vat' => true,
        'commerce.free_shipping_threshold' => 25000, // bani
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
        'payments.transfer_details' => '',
        'payments.stripe_public' => '',

        'mail.shop_recipients' => ['contact@pet-market.ro'],

        'marketing.gtm_id' => '',
        'marketing.ga4_id' => '',
        'marketing.meta_pixel_id' => '',
        'marketing.search_console_verification' => '',
        'marketing.google_ads_id' => '',
        'marketing.announcement' => 'Livrare gratuită pentru comenzile de peste 250 lei',

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
