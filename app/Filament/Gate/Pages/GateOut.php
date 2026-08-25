<?php

namespace App\Filament\Gate\Pages;

use App\Filament\Gate\Resources\GateEntries\GateEntryResource;
use App\Models\GateLog;
use App\Services\RegisterGateExit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form as FormComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class GateOut extends Page
{
    protected string $view = 'filament.gate.pages.gate-out';

    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowLeftStartOnRectangle;

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'Gate Out';
    }

    public function getTitle(): string
    {
        return 'Register Gate Out';
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Vehicle Gate Out')
                    ->description('Select a vehicle currently on premises to record departure.')
                    ->schema([
                        Select::make('vehicle_id')
                            ->label('Vehicle Registration No.')
                            ->placeholder('Select vehicle on premises...')
                            ->options(function (): array {
                                return GateLog::query()
                                    ->where('status', GateLog::STATUS_IN)
                                    ->orderBy('gated_in_at', 'desc')
                                    ->get()
                                    ->mapWithKeys(fn (GateLog $log): array => [
                                        $log->vehicle_id => "{$log->vehicle_number} — {$log->driver_name} (In: {$log->gated_in_at?->format('d/m/Y H:i')})",
                                    ])
                                    ->all();
                            })
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->helperText('Only vehicles currently inside the premises (status = In) are shown.')
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                if (blank($state)) {
                                    $set('driver_name', null);
                                    $set('driver_id_number', null);
                                    $set('driver_phone', null);
                                    $set('gated_in_at', null);

                                    return;
                                }

                                $openLog = GateLog::query()
                                    ->where('vehicle_id', (int) $state)
                                    ->where('status', GateLog::STATUS_IN)
                                    ->latest('gated_in_at')
                                    ->first();

                                $set('driver_name', $openLog?->driver_name);
                                $set('driver_id_number', $openLog?->driver_id_number);
                                $set('driver_phone', $openLog?->driver_phone);
                                $set('gated_in_at', $openLog?->gated_in_at?->format('d/m/Y H:i'));
                            }),
                        TextInput::make('driver_name')
                            ->label('Driver Name')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('driver_id_number')
                            ->label('Driver ID / Passport No.')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('driver_phone')
                            ->label('Driver Phone')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('gated_in_at')
                            ->label('Gate In Timestamp')
                            ->disabled()
                            ->dehydrated(false),
                        Textarea::make('remarks')
                            ->label('Exit Remarks')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $vehicleId = (int) ($data['vehicle_id'] ?? 0);

        if (! $vehicleId) {
            Notification::make()
                ->title('Vehicle required')
                ->body('Please select a valid vehicle currently on premises.')
                ->danger()
                ->send();

            return;
        }

        try {
            $log = RegisterGateExit::forVehicle($vehicleId, Auth::id());

            if (! empty($data['remarks'])) {
                $log->update([
                    'remarks' => trim(($log->remarks ? $log->remarks.' | ' : '').'Exit: '.$data['remarks']),
                ]);
            }

            Notification::make()
                ->title('Gate out registered')
                ->body("Vehicle {$log->vehicle_number} has successfully gated out.")
                ->success()
                ->send();

            $this->redirect(GateEntryResource::getUrl('index'), navigate: true);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gate out failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                FormComponent::make([EmbeddedSchema::make('form')])
                    ->id('gate-out-form')
                    ->footer([
                        Actions::make([
                            Action::make('register_gate_out')
                                ->label('Register Gate Out')
                                ->icon('heroicon-m-arrow-right-start-on-rectangle')
                                ->color('primary')
                                ->size('lg')
                                ->requiresConfirmation(false)
                                ->action('save'),
                        ])->alignment(Alignment::End),
                    ]),
            ]);
    }
}
