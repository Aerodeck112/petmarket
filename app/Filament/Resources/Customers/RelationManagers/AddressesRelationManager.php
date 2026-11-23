<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Models\Address;
use App\Services\Shop\Counties;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    protected static ?string $title = 'Adrese';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('type')->label('Tip')->options(['shipping' => 'Livrare', 'billing' => 'Facturare'])->required(),
            Toggle::make('is_default')->label('Implicită')->inline(false),
            TextInput::make('first_name')->label('Prenume')->required(), TextInput::make('last_name')->label('Nume')->required(),
            TextInput::make('company')->label('Firmă'), TextInput::make('cui')->label('CUI'),
            Select::make('county')->label('Județ')->options(Counties::ALL)->required()->searchable(), TextInput::make('city')->label('Localitate')->required(),
            TextInput::make('address_1')->label('Adresă')->required()->columnSpanFull(),
            TextInput::make('address_2')->label('Bloc, ap.'), TextInput::make('postcode')->label('Cod poștal'), TextInput::make('phone')->label('Telefon'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')->label('Tip')->formatStateUsing(fn ($state) => $state === 'billing' ? 'Facturare' : 'Livrare')->badge(),
                TextColumn::make('line')->label('Adresă')->state(fn (Address $a) => trim($a->first_name.' '.$a->last_name).', '.$a->address_1.', '.$a->city.', '.Counties::name($a->county))->wrap(),
                IconColumn::make('is_default')->label('Implicită')->boolean(),
            ])
            ->headerActions([CreateAction::make()])->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
