<?php

namespace App\Services\Seo;

use App\Support\Settings;

/** Metadatele unei pagini: titlu, descriere, canonical, Open Graph si JSON-LD. */
class Seo
{
    public string $title;

    public string $description;

    public string $canonical;

    public ?string $image = null;

    public bool $noindex = false;

    public string $type = 'website';

    /** @var list<array> */
    public array $jsonLd = [];

    /** @var list<array{name:string,url:string}> */
    public array $breadcrumbs = [];

    public function __construct()
    {
        $this->title = Settings::get('shop.name').' '.Settings::get('shop.byline');
        $this->description = __((string) Settings::get('seo.default_description'));
        $this->canonical = self::canonicalUrl(request()->path());
    }

    /** Adresa canonica, cu slash final ca in WooCommerce. */
    public static function canonicalUrl(string $path): string
    {
        $path = trim($path, '/');

        return rtrim(config('app.url'), '/').'/'.($path === '' ? '' : $path.'/');
    }

    public function page(string $title, ?string $description = null, ?string $image = null): static
    {
        $this->title = $title.' – '.Settings::get('shop.name');
        if ($description) {
            $this->description = mb_substr(trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(preg_replace('/<[^>]+>/', ' ', $description)), ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 0, 160);
        }
        if ($image) {
            $this->image = asset($image);
        }

        return $this;
    }

    /** @param list<array{0:string,1:string}> $items */
    public function crumbs(array $items): static
    {
        $this->breadcrumbs = array_map(fn ($i) => ['name' => $i[0], 'url' => self::canonicalUrl($i[1])], $items);
        $this->jsonLd[] = [
            '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn ($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c['name'], 'item' => $c['url']],
                $this->breadcrumbs, array_keys($this->breadcrumbs)),
        ];

        return $this;
    }

    public function schema(array $schema): static
    {
        $this->jsonLd[] = ['@context' => 'https://schema.org'] + $schema;

        return $this;
    }

    /** Schema magazinului, doar cu date confirmate din setari. */
    public static function storeSchema(): array
    {
        $schema = [
            '@type' => 'PetStore', '@id' => rtrim(config('app.url'), '/').'/#store',
            'name' => Settings::get('shop.name').' '.Settings::get('shop.byline'), 'legalName' => Settings::get('shop.legal_name'),
            'url' => rtrim(config('app.url'), '/').'/', 'telephone' => Settings::get('shop.phone'), 'email' => Settings::get('shop.email'),
            'address' => ['@type' => 'PostalAddress', 'streetAddress' => Settings::get('shop.address'),
                'addressLocality' => Settings::get('shop.city'), 'addressRegion' => Settings::get('shop.county'), 'addressCountry' => 'RO'],
            'logo' => asset('media/brand/pet-market-logo.png'), 'currenciesAccepted' => 'RON',
        ];
        $same = array_values(array_filter([Settings::get('shop.instagram'), Settings::get('shop.facebook'), Settings::get('shop.tiktok')]));
        if ($same) {
            $schema['sameAs'] = $same;
        }

        return $schema;
    }
}
