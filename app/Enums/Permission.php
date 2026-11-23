<?php

namespace App\Enums;

/** Permisiuni granulare. Sunt constante (nu enum) pentru a putea fi folosite direct in Gate / can(). */
final class Permission
{
    public const ORDERS_VIEW = 'orders.view';

    public const ORDERS_MANAGE = 'orders.manage';

    public const ORDERS_REFUND = 'orders.refund';

    public const PRODUCTS_MANAGE = 'products.manage';

    public const STOCK_MANAGE = 'stock.manage';

    public const CUSTOMERS_VIEW = 'customers.view';

    public const CUSTOMERS_MANAGE = 'customers.manage';

    public const COUPONS_MANAGE = 'coupons.manage';

    public const REVIEWS_MANAGE = 'reviews.manage';

    public const REPORTS_VIEW = 'reports.view';

    public const SHIPPING_MANAGE = 'shipping.manage';

    public const CMS_MANAGE = 'cms.manage';

    public const MEDIA_MANAGE = 'media.manage';

    public const SEO_MANAGE = 'seo.manage';

    public const MESSAGES_MANAGE = 'messages.manage';

    public const USERS_MANAGE = 'users.manage';

    public const SETTINGS_MANAGE = 'settings.manage';

    public const AUDIT_VIEW = 'audit.view';

    public const IMPORT_RUN = 'import.run';

    public const PLATFORM_UPDATE = 'platform.update';

    /** @return list<string> */
    public static function all(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }

    public static function label(string $permission): string
    {
        return [
            self::ORDERS_VIEW => 'Vede comenzile',
            self::ORDERS_MANAGE => 'Procesează comenzi (status, AWB, note)',
            self::ORDERS_REFUND => 'Rambursări și anulări de comenzi plătite',
            self::PRODUCTS_MANAGE => 'Produse, categorii, branduri, atribute',
            self::STOCK_MANAGE => 'Stocuri',
            self::CUSTOMERS_VIEW => 'Vede clienții',
            self::CUSTOMERS_MANAGE => 'Editează clienții',
            self::COUPONS_MANAGE => 'Cupoane și promoții',
            self::REVIEWS_MANAGE => 'Moderează recenziile',
            self::REPORTS_VIEW => 'Rapoarte de vânzări',
            self::SHIPPING_MANAGE => 'Livrare și metode de plată',
            self::CMS_MANAGE => 'Pagini, blog, bannere, întrebări frecvente',
            self::MEDIA_MANAGE => 'Bibliotecă media',
            self::SEO_MANAGE => 'SEO, redirecturi, marketing',
            self::MESSAGES_MANAGE => 'Mesaje din formularul de contact',
            self::USERS_MANAGE => 'Personal și roluri',
            self::SETTINGS_MANAGE => 'Setări magazin și integrări',
            self::AUDIT_VIEW => 'Jurnal de audit',
            self::IMPORT_RUN => 'Import catalog din magazinul vechi',
            self::PLATFORM_UPDATE => 'Actualizări platformă',
        ][$permission] ?? $permission;
    }
}
