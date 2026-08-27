<?php

namespace App\Filament\Gate\Resources\GateEntries\Pages;

use App\Filament\Gate\Resources\GateEntries\GateEntryResource;
use Filament\Resources\Pages\ViewRecord;

class ViewGateEntry extends ViewRecord
{
    protected static string $resource = GateEntryResource::class;
}
