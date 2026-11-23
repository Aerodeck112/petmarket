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

    private const INTS = ['commerce.vat_rate', 'commerce.low_stock_threshold', 'commerce.new_badge_days'];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(Permission::SETTINGS_MANAGE);
    }

    private static function keys(): array
    {
        return array_values(array_filter(array_keys(Settings::DEFAULTS), fn ($k) => preg_match('/^(shop|commerce|payments|mail)\./', $k)));
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
                    Section::make('Prețuri și taxe')->columns(3)->schema([
                        TextInput::make($f('commerce.vat_rate'))->label('Cota TVA standard')->numeric()->suffix('%')->required()->minValue(0)->maxValue(30),
                        Toggle::make($f('commerce.prices_include_vat'))->label('Prețurile includ TVA')->disabled()->helperText('Ca în magazinul vechi („TVA inclus”).'),
                        TextInput::make($f('commerce.order_prefix'))->label('Prefix număr comandă')->maxLength(6)->required(),
                    ]),
                    Section::make('Livrare și comandă')->columns(3)->schema([
                        TextInput::make($f('commerce.free_shipping_threshold'))->label('Livrare gratuită de la')->numeric()->suffix('lei')->helperText('0 = fără livrare gratuită.'),
                        TextInput::make($f('commerce.min_order'))->label('Comandă minimă')->numeric()->suffix('lei'),
                        Toggle::make($f('commerce.guest_checkout'))->label('Comandă fără cont'),
                    ]),
                    Section::make('Stoc și catalog')->columns(3)->schema([
                        TextInput::make($f('commerce.low_stock_threshold'))->label('Prag stoc redus')->numeric()->suffix('buc.'),
                        Toggle::make($f('commerce.allow_backorders'))->label('Precomenzi implicite')->helperText('Pentru produsele noi.'),
                        TextInput::make($f('commerce.new_badge_days'))->label('Eticheta „Nou” timp de')->numeric()->suffix('zile'),
                        Toggle::make($f('commerce.reviews_enabled'))->label('Recenzii activate'),
                        Toggle::make($f('commerce.reviews_need_approval'))->label('Recenziile se aprobă înainte de publicare'),
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
        if ($changed) {
            Audit::log('settings.updated', null, 'Setări modificate: '.implode(', ', $changed));
        }
        Notification::make()->title('Setările au fost salvate')->success()->send();
    }
}
