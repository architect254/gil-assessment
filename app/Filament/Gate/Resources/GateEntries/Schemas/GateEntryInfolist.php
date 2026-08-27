<?php

namespace App\Filament\Gate\Resources\GateEntries\Schemas;

use App\Models\GateLog;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GateEntryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Gate In Details')
                    ->icon('heroicon-o-arrow-left-end-on-rectangle')
                    ->schema([
                        TextEntry::make('vehicle_number')
                            ->label('Vehicle No.')
                            ->weight('bold')
                            ->size('lg'),
                        TextEntry::make('vehicle.description')
                            ->label('Vehicle Description')
                            ->placeholder('—'),
                        TextEntry::make('driver_name')
                            ->label('Driver'),
                        TextEntry::make('driver_id_number')
                            ->label('Driver ID / Passport No.')
                            ->placeholder('—'),
                        TextEntry::make('driver_phone')
                            ->label('Phone')
                            ->icon('heroicon-m-phone')
                            ->placeholder('—'),
                        TextEntry::make('gated_in_at')
                            ->label('Date & Time In')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('gatedInUser.name')
                            ->label('Gated In By')
                            ->placeholder('—'),
                    ])
                    ->columns(2),

                Section::make('Gate Out Details')
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->visible(fn (GateLog $record): bool => $record->status === GateLog::STATUS_OUT)
                    ->schema([
                        TextEntry::make('gated_out_at')
                            ->label('Date & Time Out')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('gatedOutUser.name')
                            ->label('Gated Out By')
                            ->placeholder('—'),
                    ])
                    ->columns(2),

                Section::make('Remarks')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->schema([
                        TextEntry::make('remarks')
                            ->placeholder('No remarks for this entry.')
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }
}
