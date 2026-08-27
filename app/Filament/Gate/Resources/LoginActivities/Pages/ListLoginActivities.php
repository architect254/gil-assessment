<?php

namespace App\Filament\Gate\Resources\LoginActivities\Pages;

use App\Filament\Gate\Resources\LoginActivities\LoginActivityResource;
use Filament\Resources\Pages\ListRecords;

class ListLoginActivities extends ListRecords
{
    protected static string $resource = LoginActivityResource::class;
}
