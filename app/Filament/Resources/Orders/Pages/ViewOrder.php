<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\ShippingMethod;
use App\Services\Shop\Counties;
use App\Services\Shop\OrderMailer;
use App\Services\Shop\OrderService;
use App\Support\Audit;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use InvalidArgumentException;

/** Pagina comenzii: produse, totaluri, client, adrese, istoric si actiunile zilnice, toate pe un singur ecran. */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected string $view = 'filament.orders.view';

    public function getTitle(): string
    {
        return 'Comanda '.$this->record->number;
    }

    private function can(): bool
    {
        return (bool) auth()->user()?->can(Permission::ORDERS_MANAGE);
    }

    private function service(): OrderService
    {
        return app(OrderService::class);
    }

    private function run(callable $fn, string $ok): void
    {
        try {
            $fn();
            $this->record->refresh();
            Notification::make()->title($ok)->success()->send();
        } catch (InvalidArgumentException|\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        /** @var Order $order */
        $order = $this->record;

        return [
            Action::make('status')->label('Schimbă statusul')->icon('heroicon-o-arrow-path')->color('primary')
                ->visible(fn () => $this->can() && $order->status->next() !== [])
                ->schema([
                    Select::make('status')->label('Status nou')->required()
                        ->options(fn () => collect($this->record->status->next())->mapWithKeys(fn (OrderStatus $s) => [$s->value => $s->label()])->all()),
                    Textarea::make('note')->label('Notă (opțional, apare în istoric)')->rows(2),
                    Toggle::make('notify')->label('Trimite email clientului')->default(true),
                ])
                ->action(fn (array $data) => $this->run(fn () => $this->service()->changeStatus($this->record, OrderStatus::from($data['status']), $data['note'] ?: null, (bool) $data['notify']), 'Statusul a fost schimbat')),

            Action::make('awb')->label('AWB / expediere')->icon('heroicon-o-truck')->color('warning')
                ->visible(fn () => $this->can())
                ->fillForm(fn () => ['carrier' => $this->record->carrier ?: 'ecolet', 'awb' => $this->record->tracking_number, 'ship' => true])
                ->schema([
                    Select::make('carrier')->label('Curier')->options(ShippingMethod::CARRIERS + ['altul' => 'Alt curier'])->required(),
                    TextInput::make('awb')->label('Număr AWB')->required()->maxLength(80),
                    Toggle::make('ship')->label('Marchează comanda ca expediată și anunță clientul')->default(true),
                ])
                ->action(fn (array $data) => $this->run(fn () => $this->service()->setTracking($this->record, $data['carrier'], $data['awb'], (bool) $data['ship']), 'AWB salvat')),

            Action::make('ecolet')->label('Trimite la Ecolet')->icon('heroicon-o-paper-airplane')->color('info')
                ->visible(fn () => $this->can() && \App\Services\Shipping\EcoletClient::configured() && ! $this->record->ecolet_order_to_send_id && ! $this->record->tracking_number)
                ->requiresConfirmation()->modalHeading('Trimite coletul la Ecolet')
                ->modalDescription(fn () => 'Comanda apare în Ecolet la „Comenzi de trimis”, unde alegi curierul și generezi AWB-ul. AWB-ul vine înapoi aici automat.'
                    .($this->record->payment_method->value === 'cod' ? ' Ramburs: '.money($this->record->total).'.' : ''))
                ->action(fn () => $this->run(fn () => app(\App\Services\Shipping\EcoletShipments::class)->push($this->record), 'Comanda a fost trimisă la Ecolet')),

            Action::make('paid')->label('Marchează încasată')->icon('heroicon-o-banknotes')->color('success')
                ->visible(fn () => $this->can() && $this->record->payment_status->value !== 'paid')
                ->schema([TextInput::make('reference')->label('Referință (extras, chitanță)')->maxLength(120)])
                ->requiresConfirmation()->modalDescription(fn () => 'Confirmi încasarea sumei de '.money($this->record->total).'?')
                ->action(fn (array $data) => $this->run(fn () => $this->service()->markPaid($this->record, $this->record->payment_method->value, $data['reference'] ?? null), 'Plata a fost înregistrată')),

            Action::make('note')->label('Notă')->icon('heroicon-o-chat-bubble-left-ellipsis')->color('gray')
                ->visible(fn () => $this->can())
                ->schema([
                    Textarea::make('message')->label('Mesaj')->required()->rows(4),
                    Toggle::make('customer')->label('Trimite clientului pe email (altfel rămâne notă internă)'),
                ])
                ->action(fn (array $data) => $this->run(fn () => $this->service()->note($this->record, $data['message'], (bool) $data['customer']), 'Nota a fost adăugată')),

            ActionGroup::make([
                Action::make('proforma')->label('Factură proformă')->icon('heroicon-o-document-text')
                    ->url(fn () => route('admin.order.document', ['order' => $this->record, 'kind' => 'proforma']), shouldOpenInNewTab: true),
                Action::make('aviz')->label('Listă de picking / aviz')->icon('heroicon-o-clipboard-document-list')
                    ->url(fn () => route('admin.order.document', ['order' => $this->record, 'kind' => 'aviz']), shouldOpenInNewTab: true),
                Action::make('addresses')->label('Modifică adresele')->icon('heroicon-o-map-pin')->visible(fn () => $this->can())
                    ->fillForm(fn () => ['billing' => $this->record->billing, 'shipping' => $this->record->shipping, 'phone' => $this->record->phone, 'email' => $this->record->email])
                    ->schema([
                        Grid::make(2)->schema([TextInput::make('email')->email()->required(), TextInput::make('phone')->label('Telefon')->required()]),
                        Section::make('Livrare')->columns(2)->schema(self::addressFields('shipping')),
                        Section::make('Facturare')->columns(2)->collapsed()->schema(array_merge(self::addressFields('billing'), [
                            TextInput::make('billing.company')->label('Firmă'), TextInput::make('billing.cui')->label('CUI'),
                        ])),
                    ])
                    ->action(function (array $data) {
                        $before = $this->record->only(['billing', 'shipping', 'phone', 'email']);
                        foreach (['billing', 'shipping'] as $k) {
                            $data[$k]['county_code'] = $data[$k]['county_code'] ?? null;
                            $data[$k]['county'] = Counties::name($data[$k]['county_code']) ?? ($data[$k]['county'] ?? null);
                        }
                        $this->record->update(['billing' => array_merge($this->record->billing, $data['billing']), 'shipping' => array_merge($this->record->shipping, $data['shipping']),
                            'phone' => $data['phone'], 'email' => $data['email']]);
                        Audit::log('order.addresses', $this->record, 'Adrese modificate la comanda '.$this->record->number, $before, $this->record->only(['billing', 'shipping', 'phone', 'email']));
                        Notification::make()->title('Adresele au fost salvate')->success()->send();
                    }),
                Action::make('ecoletSync')->label('Actualizează din Ecolet')->icon('heroicon-o-arrow-path')
                    ->visible(fn () => (bool) $this->record->ecolet_order_to_send_id && \App\Services\Shipping\EcoletClient::configured())
                    ->action(fn () => $this->run(fn () => app(\App\Services\Shipping\EcoletShipments::class)->sync($this->record), 'Date actualizate din Ecolet')),
                Action::make('ecoletAwb')->label('Descarcă AWB (PDF)')->icon('heroicon-o-printer')
                    ->visible(fn () => (bool) $this->record->ecolet_order_id && \App\Services\Shipping\EcoletClient::configured())
                    ->action(function () {
                        try {
                            $pdf = app(\App\Services\Shipping\EcoletShipments::class)->waybill($this->record);

                            return response()->streamDownload(fn () => print($pdf), 'AWB-'.($this->record->tracking_number ?: $this->record->number).'.pdf', ['Content-Type' => 'application/pdf']);
                        } catch (\RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->warning()->send();
                        }
                    }),
                Action::make('resend')->label('Retrimite confirmarea')->icon('heroicon-o-envelope')->visible(fn () => $this->can())
                    ->requiresConfirmation()->action(function () {
                        app(OrderMailer::class)->placed($this->record->load('items'));
                        Notification::make()->title('Emailul a fost retrimis')->success()->send();
                    }),
                Action::make('public')->label('Pagina comenzii pentru client')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn () => $this->record->publicUrl(), shouldOpenInNewTab: true),
            ])->label('Mai mult')->icon('heroicon-m-ellipsis-vertical')->button()->color('gray'),
        ];
    }

    private static function addressFields(string $p): array
    {
        return [
            TextInput::make("$p.first_name")->label('Prenume')->required(), TextInput::make("$p.last_name")->label('Nume')->required(),
            Select::make("$p.county_code")->label('Județ')->options(Counties::ALL)->searchable(),
            TextInput::make("$p.city")->label('Localitate')->required(),
            TextInput::make("$p.address_1")->label('Adresă')->required()->columnSpanFull(), TextInput::make("$p.address_2")->label('Bloc, ap.'),
            TextInput::make("$p.postcode")->label('Cod poștal'),
        ];
    }
}
