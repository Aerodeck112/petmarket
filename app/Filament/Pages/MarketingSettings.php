<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Models\Page as SitePage;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Product;
use App\Support\Audit;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use App\Support\Settings;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * Marketing & Google. Aici se configureaza doar ce functioneaza fara acces la conturile Google:
 * ID-urile de masurare si verificarea site-ului. Pentru restul, pagina ofera linkuri directe;
 * nu simuleaza conectari OAuth si nu afiseaza cifre care nu vin dintr-o integrare reala.
 */
class MarketingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Google și SEO';

    protected static ?string $title = 'Marketing, Google și SEO';

    protected static ?int $navigationSort = 80;

    protected string $view = 'filament.pages.marketing';

    protected const MAP = [
        'gtm_id' => 'marketing.gtm_id', 'ga4_id' => 'marketing.ga4_id', 'google_ads_id' => 'marketing.google_ads_id',
        'verification' => 'marketing.search_console_verification', 'default_description' => 'seo.default_description', 'robots_extra' => 'seo.robots_extra',
        'meta_pixel_id' => 'marketing.meta_pixel_id', 'announcement' => 'marketing.announcement',
    ];

    public function mount(): void
    {
        $this->form->fill(collect(static::MAP)->map(fn ($key) => Settings::get($key))->all());
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $changed = [];
        foreach (static::MAP as $field => $key) {
            if (Settings::get($key) !== ($state[$field] ?? null)) {
                Settings::set($key, $state[$field] ?? null);
                $changed[] = $key;
            }
        }
        if ($changed) {
            Audit::log('settings.updated', null, 'Setări modificate: '.implode(', ', $changed));
        }
        Notification::make()->title('Setările au fost salvate')->success()->send();
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(Permission::SEO_MANAGE);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Măsurare')->columns(2)
                ->description('Scripturile Google se încarcă doar după ce vizitatorul acceptă cookie-urile de măsurare. Folosește fie GTM, fie GA4 direct, nu ambele, ca să nu numeri de două ori.')
                ->schema([
                    TextInput::make('gtm_id')->label('Google Tag Manager (GTM-XXXXXXX)')->regex('/^GTM-[A-Z0-9]+$/'),
                    TextInput::make('ga4_id')->label('Google Analytics 4 (G-XXXXXXXXXX)')->regex('/^G-[A-Z0-9]+$/'),
                    TextInput::make('google_ads_id')->label('Google Ads (AW-XXXXXXXXX)')->regex('/^AW-[0-9]+$/')->helperText('Conversiile Ads se configurează în GTM, pe evenimentele de mai jos.'),
                    TextInput::make('verification')->label('Cod de verificare Search Console')->maxLength(120)->helperText('Valoarea din meta tag-ul google-site-verification.'),
                    TextInput::make('meta_pixel_id')->label('Meta (Facebook) Pixel ID')->regex('/^[0-9]{8,20}$/')->helperText('Se încarcă tot doar după acordul pentru cookie-uri.'),
                ]),
            Section::make('Bara de anunț din antet')->schema([
                TextInput::make('announcement')->label('Text')->maxLength(140)->helperText('Gol = bara nu apare.'),
            ]),
            Section::make('SEO')->columns(1)->schema([
                Textarea::make('default_description')->label('Descriere implicită pentru Google')->rows(2)->maxLength(160),
                Textarea::make('robots_extra')->label('Linii suplimentare în robots.txt')->rows(3)->helperText('Site-ul de test răspunde mereu cu „Disallow: /”. Regulile de aici se aplică doar în producție.'),
            ]),
        ]);
    }

    protected function getViewData(): array
    {
        $pages = SitePage::query()->where('status', 'published')->get(['title', 'slug', 'seo_title', 'seo_description', 'lead', 'body']);

        return [
            'events' => ['view_item' => 'Vizualizare produs (GA4 ecommerce)', 'add_to_cart' => 'Adăugare în coș', 'begin_checkout' => 'Început finalizare comandă',
                'purchase' => 'Comandă plasată (valoare, ID tranzacție, produse)', 'search' => 'Căutare în magazin', 'phone_click' => 'Click pe numărul de telefon', 'contact_submit' => 'Formular de contact trimis'],
            'stats' => ['Produse publicate' => Product::published()->count(), 'Pagini' => $pages->count(), 'Articole' => Post::published()->count(), 'Redirecturi 301' => Redirect::count()],
            'missingDescriptions' => $pages->filter(fn ($p) => blank($p->seo_description) && blank($p->lead) && blank(strip_tags((string) $p->body)))->pluck('title'),
            'links' => [
                'Google Search Console' => 'https://search.google.com/search-console', 'Google Analytics' => 'https://analytics.google.com/', 'Google Tag Manager' => 'https://tagmanager.google.com/',
                'Google Ads' => 'https://ads.google.com/', 'Google Merchant Center' => 'https://merchants.google.com/', 'Feed produse Google' => site_url('/feed/google-merchant.xml'), 'PageSpeed Insights pentru site' => 'https://pagespeed.web.dev/analysis?url='.urlencode(rtrim(config('app.url'), '/').'/'),
                'Test date structurate' => 'https://search.google.com/test/rich-results?url='.urlencode(rtrim(config('app.url'), '/').'/'), 'Sitemap' => site_url('/sitemap.xml'), 'robots.txt' => site_url('/robots.txt'),
            ],
            'gtmConfigured' => filled(Settings::get('marketing.gtm_id')) || filled(Settings::get('marketing.ga4_id')),
        ];
    }
}
