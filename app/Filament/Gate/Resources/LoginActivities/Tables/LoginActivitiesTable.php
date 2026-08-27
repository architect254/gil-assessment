<?php

namespace App\Filament\Gate\Resources\LoginActivities\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LoginActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('logged_in_at')
                    ->label('Logged In At')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('user_agent')
                    ->label('User Agent')
                    ->limit(60)
                    ->tooltip(fn ($record): ?string => $record->user_agent)
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Recorded At')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('logged_in_at', 'desc');
    }
}
