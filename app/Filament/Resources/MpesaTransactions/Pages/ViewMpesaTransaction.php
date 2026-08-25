<?php

namespace App\Filament\Resources\MpesaTransactions\Pages;

use App\Filament\Resources\MpesaTransactions\MpesaTransactionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewMpesaTransaction extends ViewRecord
{
    protected static string $resource = MpesaTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
