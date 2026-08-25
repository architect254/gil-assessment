<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ApproveInvoiceAction
{
    public static function make(): Action
    {
        return Action::make('approveInvoice')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Invoice $record): bool => ! $record->needs_approval)
            ->requiresConfirmation()
            ->modalHeading('Approve Invoice')
            ->modalDescription(fn (Invoice $record): string => "Are you sure you want to approve Invoice #{$record->no} for KES " . number_format((float) $record->total_after_discount, 2) . "?")
            ->modalSubmitActionLabel('Approve')
            ->action(function (Invoice $record): void {
                $record->update(['needs_approval' => true]);

                Notification::make()
                    ->title('Invoice Approved')
                    ->body("Invoice #{$record->no} has been approved.")
                    ->success()
                    ->send();
            });
    }
}
