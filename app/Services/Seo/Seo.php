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
        // Paginile 2, 3... ale unei liste au titlu și descriere proprii („Hrană – pagina 2”), ca să nu fie duplicate.
        $pageNo = (int) request()->query('page', 1);
        if ($pageNo > 1) {
            $title .= ' – pagina '.$pageNo;
            $description = $description ? 'Pagina '.$pageNo.'. '.self::excerpt($description, 140) : null;
        }
        // Titlul complet are cel mult ~65 de caractere (cât afișează Google); la titlurile lungi numele magazinului se omite.
        $full = $title.' – '.Settings::get('shop.name');
        $this->title = mb_strlen($full) > 65 && mb_strlen($title) >= 30 ? $title : $full;
        if ($description) {
            $this->description = self::excerpt($description, 158);
        }
        if ($image) {
            $this->image = asset($image);
        }

        return $this;
    }

    /** Text simplu din HTML, tăiat la ultimul cuvânt întreg, cu „…” dacă a fost scurtat. */
    public static function excerpt(?string $html, int $max = 158): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('/<[^>]+>/', ' ', (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max - 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space > $max * 0.6 ? mb_substr($cut, 0, $space) : $cut, ' ,.;:–-').'…';
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
