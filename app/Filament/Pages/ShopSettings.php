<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Support\Audit;
use App\Support\Settings;
use BackedEnum;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use UnitEnum;

/** Setarile magazinului: date firma, reguli comerciale, plati, notificari (echivalentul WooCommerce → Setări). */
class ShopSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'Setări magazin';

    protected static ?string $title = 'Setări magazin';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    /** Campurile cu sume se editeaza in lei si se salveaza in bani. */
    private const MONEY = ['commerce.free_shipping_threshold', 'commerce.min_order', 'payments.cod_fee'];

    private const INTS = ['commerce.vat_rate', 'commerce.low_stock_threshold', 'commerce.new_badge_days', 'ecolet.parcel_length', 'ecolet.parcel_width', 'ecolet.parcel_height'];

    /** Secretele Ecolet: se salveaza criptat si nu se afiseaza inapoi. */
    private const SECRETS = ['ecolet__client_secret' => 'ecolet.client_secret', 'ecolet__password' => 'ecolet.password'];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(Permission::SETTINGS_MANAGE);
    }

    private static function keys(): array
    {
        return array_values(array_filter(array_keys(Settings::DEFAULTS), fn ($k) => preg_match('/^(shop|commerce|payments|mail|ecolet)\./', $k)));
    }

    private static function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    public function mount(): void
    {
        $state = [];
        foreach (self::keys() as $key) {
            $value = Settings::get($key);
            $state[self::field($key)] = in_array($key, self::MONEY, true) ? ((int) $value) / 100 : $value;
        }
        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $f = fn (string $key) => self::field($key);

        return $schema->statePath('data')->components([
            Tabs::make()->tabs([
                Tab::make('Magazin')->icon('heroicon-o-building-storefront')->schema([
                    Section::make('Identitate')->columns(2)->schema([
                        TextInput::make($f('shop.name'))->label('Nume afișat')->required()->maxLength(80),
                        TextInput::make($f('shop.byline'))->label('Semnătură')->maxLength(80)->helperText('Apare lângă logo: „by HiKeVet”.'),
                        TextInput::make($f('shop.tagline'))->label('Descriere scurtă')->maxLength(200)->columnSpanFull(),
                    ]),
                    Section::make('Date firmă (facturi, subsol, ANPC)')->columns(3)->schema([
                        TextInput::make($f('shop.legal_name'))->label('Denumire juridică')->maxLength(120),
                        TextInput::make($f('shop.cui'))->label('CUI')->maxLength(30),
                        TextInput::make($f('shop.reg_com'))->label('Nr. Reg. Com.')->maxLength(40),
                        TextInput::make($f('shop.capital_social'))->label('Capital social')->maxLength(60)->placeholder('ex. 200 lei')->helperText('Apare în subsol, lângă datele firmei (Legea 31/1990, art. 74).'),
                    ]),
                    Section::make('Contact')->columns(2)->schema([
                        TextInput::make($f('shop.phone'))->label('Telefon')->required()->maxLength(30),
                        TextInput::make($f('shop.email'))->label('Email public')->email()->required(),
                        TextInput::make($f('shop.address'))->label('Adresă')->maxLength(160),
                        TextInput::make($f('shop.city'))->label('Localitate')->maxLength(80),
                        TextInput::make($f('shop.county'))->label('Județ')->maxLength(80),
                        TextInput::make($f('shop.hours'))->label('Program')->maxLength(120),
                    ]),
                    Section::make('Rețele sociale')->columns(3)->collapsed()->schema([
                        TextInput::make($f('shop.instagram'))->label('Instagram')->url(),
                        TextInput::make($f('shop.facebook'))->label('Facebook')->url(),
                        TextInput::make($f('shop.tiktok'))->label('TikTok')->url(),
                    ]),
                ]),
                Tab::make('Reguli comerciale')->icon('heroicon-o-scale')->schema([
                    Section::make('Prețuri și comenzi')->description('Prețurile din magazin sunt prețuri finale de vânzare. TVA-ul se aplică la emiterea facturii, în programul de facturare.')->columns(3)->schema([
                        TextInput::make($f('commerce.order_prefix'))->label('Prefix număr comandă')->maxLength(6)->required(),
                    ]),
                    Section::make('Livrare și comandă')->columns(3)->schema([
                        TextInput::make($f('commerce.free_shipping_threshold'))->label('Livrare gratuită de la')->numeric()->suffix('lei')->helperText('0 = fără livrare gratuită.'),
                        TextInput::make($f('commerce.min_order'))->label('Comandă minimă')->numeric()->suffix('lei'),
                        TextInput::make($f('commerce.delivery_time'))->label('Termen de livrare afișat')->maxLength(60)->placeholder('1–4 zile lucrătoare'),
                        Toggle::make($f('commerce.guest_checkout'))->label('Comandă fără cont'),
                    ]),
                    Section::make('Stoc și catalog')->columns(3)->schema([
                        TextInput::make($f('commerce.low_stock_threshold'))->label('Prag stoc redus')->numeric()->suffix('buc.'),
                        Toggle::make($f('commerce.allow_backorders'))->label('Precomenzi implicite')->helperText('Pentru produsele noi.'),
                        TextInput::make($f('commerce.new_badge_days'))->label('Eticheta „Nou” timp de')->numeric()->suffix('zile'),
                        Toggle::make($f('commerce.reviews_enabled'))->label('Recenzii activate'),
                        Toggle::make($f('commerce.reviews_need_approval'))->label('Recenziile se aprobă înainte de publicare'),
                    ]),
                    Section::make('Medicamente veterinare vândute online')->description('Regulamentul (UE) 2019/6 și 2021/1904: magazinele care vând online medicamente veterinare fără rețetă afișează pe fiecare pagină logo-ul comun UE, cu link către înregistrarea din lista ANSVSA. Imaginea o primești de la ANSVSA; o urci în public_html/petmarket/public/media/brand/.')->columns(2)->schema([
                        TextInput::make($f('legal.vmp_logo_url'))->label('Link către înregistrarea ANSVSA')->url(),
                        TextInput::make($f('legal.vmp_logo_image'))->label('Calea imaginii logo-ului')->placeholder('media/brand/logo-ue-medicamente.png'),
                    ]),
                    Section::make('Produse care nu se vând online')->description('Textul afișat pe pagina produsului, după restricția din fișa lui. „:adresa” se înlocuiește cu adresa magazinului.')->schema([
                        Textarea::make($f('commerce.not_online_notice'))->label('Medicament cu rețetă veterinară')->rows(3),
                        Textarea::make($f('commerce.not_online_notice_vet'))->label('Uz exclusiv al medicului veterinar')->rows(3),
                        Textarea::make($f('commerce.not_online_notice_location'))->label('Se vinde doar la sediu')->rows(3),
                    ]),
                ]),
                Tab::make('Plăți')->icon('heroicon-o-credit-card')->schema([
                    Section::make('Plată la livrare (ramburs)')->columns(2)->schema([
                        Toggle::make($f('payments.cod_enabled'))->label('Activă'),
                        TextInput::make($f('payments.cod_fee'))->label('Taxă ramburs')->numeric()->suffix('lei'),
                    ]),
                    Section::make('Transfer bancar (OP)')->schema([
                        Toggle::make($f('payments.transfer_enabled'))->label('Activ'),
                        Textarea::make($f('payments.transfer_details'))->label('Datele contului')->rows(4)
                            ->placeholder("Beneficiar: …\nIBAN: RO…\nBanca: …")->helperText('Apar în emailul de confirmare și pe pagina comenzii.'),
                    ]),
                    Section::make('Card online')->schema([
                        Toggle::make($f('payments.card_enabled'))->label('Activ')
                            ->helperText('Necesită contul procesatorului de plăți (de confirmat: Netopia, EuPlătesc sau Stripe). Până atunci rămâne oprit.'),
                    ]),
                ]),
                Tab::make('Ecolet')->icon('heroicon-o-truck')->schema([
                    Section::make('Conexiune')->description('În panoul Ecolet: Setări cont → API → creează o aplicație OAuth; de acolo iei Client ID și Client Secret. Emailul și parola sunt cele ale contului Ecolet.')->columns(2)->schema([
                        Toggle::make($f('ecolet.enabled'))->label('Integrarea Ecolet este activă')->columnSpanFull(),
                        TextInput::make($f('ecolet.client_id'))->label('Client ID')->maxLength(120),
                        TextInput::make('ecolet__client_secret')->label('Client Secret')->password()->revealable()->autocomplete('off')
                            ->placeholder(fn () => filled(Settings::get('ecolet.client_secret')) ? 'salvat; completează doar pentru a-l schimba' : ''),
                        TextInput::make($f('ecolet.username'))->label('Email cont Ecolet')->email(),
                        TextInput::make('ecolet__password')->label('Parolă cont Ecolet')->password()->revealable()->autocomplete('off')
                            ->placeholder(fn () => filled(Settings::get('ecolet.password')) ? 'salvată; completează doar pentru a o schimba' : ''),
                    ]),
                    Section::make('Colete')->columns(3)->schema([
                        TextInput::make($f('ecolet.parcel_length'))->label('Lungime implicită')->numeric()->suffix('cm'),
                        TextInput::make($f('ecolet.parcel_width'))->label('Lățime implicită')->numeric()->suffix('cm'),
                        TextInput::make($f('ecolet.parcel_height'))->label('Înălțime implicită')->numeric()->suffix('cm'),
                        TextInput::make($f('ecolet.content'))->label('Conținut declarat')->maxLength(100)->columnSpan(2),
                        TextInput::make($f('ecolet.service'))->label('Serviciu implicit (opțional)')->placeholder('ex. dpd_standard')
                            ->helperText('Gol = alegi curierul în Ecolet, la fiecare colet.'),
                        Toggle::make($f('ecolet.auto_send'))->label('Trimite automat comenzile noi în Ecolet')->helperText('Comenzile „În procesare” ajung singure în „Comenzi de trimis”, în maximum 10 minute.')->columnSpan(2),
                        Toggle::make($f('ecolet.complete_on_delivery'))->label('Marchează „Finalizată” la livrare'),
                    ]),
                ]),
                Tab::make('Notificări')->icon('heroicon-o-envelope')->schema([
                    TagsInput::make($f('mail.shop_recipients'))->label('Comenzile noi și mesajele ajung la')->placeholder('adresa@exemplu.ro')->nestedRecursiveRules(['email']),
                ]),
            ])->persistTabInQueryString(),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $changed = [];
        foreach (self::keys() as $key) {
            if (! array_key_exists(self::field($key), $state) || $key === 'commerce.prices_include_vat') {
                continue;
            }
            $value = $state[self::field($key)];
            if (in_array($key, self::MONEY, true)) {
                $value = (int) round(((float) $value) * 100);
            } elseif (in_array($key, self::INTS, true)) {
                $value = (int) $value;
            } elseif (is_bool(Settings::DEFAULTS[$key])) {
                $value = (bool) $value;
            }
            if (Settings::get($key) !== $value) {
                Settings::set($key, $value);
                $changed[] = $key;
            }
        }
        foreach (self::SECRETS as $field => $key) {
            if (filled($state[$field] ?? null)) {
                Settings::set($key, trim((string) $state[$field]), true);
                Settings::set('ecolet.token', null);
                $changed[] = $key;
            }
            $this->data[$field] = null;
        }
        if (in_array('ecolet.auto_send', $changed, true) && Settings::get('ecolet.auto_send')) {
            Settings::set('ecolet.auto_since', now()->toDateTimeString());
        }
        if ($changed) {
            Audit::log('settings.updated', null, 'Setări modificate: '.implode(', ', $changed));
        }
        Notification::make()->title('Setările au fost salvate')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('ecoletTest')->label('Testează Ecolet')->icon('heroicon-o-signal')->color('gray')
                ->visible(fn () => \App\Services\Shipping\EcoletClient::configured())
                ->action(function () {
                    try {
                        $me = app(\App\Services\Shipping\EcoletClient::class)->me();
                        Notification::make()->title('Conexiunea cu Ecolet funcționează')->body('Cont: '.($me['data']['email'] ?? $me['email'] ?? Settings::get('ecolet.username')))->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Ecolet nu răspunde')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
