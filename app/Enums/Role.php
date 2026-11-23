<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case ShopManager = 'shop_manager';
    case Warehouse = 'warehouse';
    case Editor = 'editor';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Administrator',
            self::ShopManager => 'Manager magazin',
            self::Warehouse => 'Depozit / livrări',
            self::Editor => 'Marketing / Editor',
            self::Customer => 'Client',
        };
    }

    /** @return list<string> */
    public static function staff(): array
    {
        return [self::SuperAdmin->value, self::Admin->value, self::ShopManager->value, self::Warehouse->value, self::Editor->value];
    }

    /** Permisiunile fiecarui rol. Super Admin primeste tot prin Gate::before. */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => Permission::all(),
            self::Admin => array_values(array_diff(Permission::all(), [Permission::PLATFORM_UPDATE])),
            self::ShopManager => [
                Permission::ORDERS_VIEW, Permission::ORDERS_MANAGE, Permission::ORDERS_REFUND, Permission::PRODUCTS_MANAGE,
                Permission::STOCK_MANAGE, Permission::CUSTOMERS_VIEW, Permission::CUSTOMERS_MANAGE, Permission::COUPONS_MANAGE,
                Permission::REVIEWS_MANAGE, Permission::REPORTS_VIEW, Permission::MESSAGES_MANAGE, Permission::CMS_MANAGE, Permission::MEDIA_MANAGE,
            ],
            self::Warehouse => [Permission::ORDERS_VIEW, Permission::ORDERS_MANAGE, Permission::STOCK_MANAGE],
            self::Editor => [Permission::CMS_MANAGE, Permission::MEDIA_MANAGE, Permission::SEO_MANAGE, Permission::MESSAGES_MANAGE, Permission::REVIEWS_MANAGE],
            self::Customer => [],
        };
    }
}
