<?php

namespace App\Filament\Resources\MpesaTransactions;

use App\Filament\Resources\MpesaTransactions\Pages\ListMpesaTransactions;
use App\Filament\Resources\MpesaTransactions\Pages\ViewMpesaTransaction;
use App\Filament\Resources\MpesaTransactions\Schemas\MpesaTransactionInfolist;
use App\Filament\Resources\MpesaTransactions\Tables\MpesaTransactionsTable;
use App\Models\MpesaTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MpesaTransactionResource extends Resource
{
    protected static ?string $model = MpesaTransaction::class;

    protected static ?string $modelLabel = 'M-Pesa Transaction';

    protected static ?string $pluralModelLabel = 'M-Pesa Transactions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static UnitEnum|string|null $navigationGroup = 'Sales – AR';

    protected static ?int $navigationSort = 12;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return MpesaTransactionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MpesaTransactionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMpesaTransactions::route('/'),
            'view' => ViewMpesaTransaction::route('/{record}'),
        ];
    }
}
