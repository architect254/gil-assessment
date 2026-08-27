<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Item;
use App\Models\SalesEmployee;
use App\Services\InvoiceCalculator;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form as FormComponent;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;

class NewInvoice extends Page
{
    protected static string $resource = \App\Filament\Resources\Invoices\InvoiceResource::class;

    protected string $view = 'filament.pages.new-invoice';

    public ?array $data = [];

    public function getTitle(): string
    {
        return 'New Invoice';
    }

    public function mount(): void
    {
        $this->form->fill([
            'posting_date' => now()->toDateString(),
            'lines' => [
                [
                    'quantity' => 0,
                    'price_before_discount' => 0,
                    'discount' => 0,
                ],
            ],
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $customer = Customer::query()
            ->where('code', trim((string) ($data['customer_code'] ?? '')))
            ->first();

        if (! $customer) {
            Notification::make()
                ->title('Invalid customer')
                ->body('Please select a valid customer code or name.')
                ->danger()
                ->send();

            return;
        }

        [$lines, $totals] = $this->computeLines($data['lines'] ?? []);

        $invoice = Invoice::createWithNextNumber([
            'customer_id' => $customer->id,
            'posting_date' => $data['posting_date'],
            'sales_employee_id' => ($data['sales_employee_id'] ?? null) ?: null,
            'remarks' => trim((string) ($data['remarks'] ?? '')),
            'total_before_discount' => InvoiceCalculator::format($totals['before']),
            'discount' => InvoiceCalculator::format($totals['discount']),
            'total_after_discount' => InvoiceCalculator::format($totals['after']),
            'needs_approval' => InvoiceCalculator::needsApproval($totals['after']),
            'created_by' => auth()->id(),
        ], $lines);

        Notification::make()
            ->title('Invoice created')
            ->body("Invoice {$invoice->no} for {$customer->name} saved successfully.")
            ->success()
            ->send();

        $this->redirect(static::getResource()::getUrl('index'), navigate: true);
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array{before: float, discount: float, after: float}}
     */
    protected function computeLines(array $rows): array
    {
        return InvoiceCalculator::computeLines($rows);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Section::make('Customer')
                                    ->schema([$this->customerCodeField(),
                                        $this->customerNameField()])->columnSpan(1),
                                            Group::make()->columnSpan(2),
                                            Section::make('Invoice')
                                                ->schema([TextInput::make('document_no')
                                                    ->label('No.')
                                                    ->disabled()
                                                    ->dehydrated(false)
                                                    ->default((string) Invoice::nextNumber())
                                                    ->maxLength(20),
                                                    DatePicker::make('posting_date')
                                                        ->label('Posting Date')
                                                        ->required()
                                                        ->native(false)
                                                        ->displayFormat('d/m/Y')])->columnSpan(1)
                            ]),
                        TextEntry::make('approval_notice')
                            ->label('')
                            ->hidden(fn (): bool => ! $this->needsApproval())
                            ->state(fn (): string => 'Invoice will go for approval – Amount: '
                                .number_format($this->totals()['after'], 3))
                            ->badge()
                            ->color('warning'),
                    ]),
                $this->linesRepeater(),
                $this->totalsSection(),
            ])
            ->statePath('data');
    }

    protected function customerCodeField(): Select
    {
        return Select::make('customer_code')
            ->label('Customer Code')
            ->searchable()
            ->required()
            ->placeholder('Choose a customer…')
            ->options(fn (): array => Customer::query()
                ->orderBy('code')
                ->get()
                ->mapWithKeys(fn (Customer $c): array => [
                    $c->code => "{$c->code} — {$c->name}",
                ])
                ->all())
            ->live(onBlur: true)
            ->afterStateUpdated(function ($state, $set): void {
                $customer = Customer::where('code', $state)->first();
                if ($customer) {
                    $set('customer_name', $customer->name);
                }
            });
    }

    protected function customerNameField(): Select
    {
        return Select::make('customer_name')
            ->label('Customer Name')
            ->searchable()
            ->required()
            ->placeholder('Choose a customer…')
            ->options(fn (): array => Customer::query()
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Customer $c): array => [
                    $c->name => $c->name,
                ])
                ->all())
            ->live(onBlur: true)
            ->afterStateUpdated(function ($state, $set): void {
                $customer = Customer::where('name', $state)->first();
                if ($customer) {
                    $set('customer_code', $customer->code);
                }
            });
    }

    protected function needsApproval(): bool
    {
        return InvoiceCalculator::needsApproval($this->totals()['after']);
    }

    protected function linesRepeater(): Repeater
    {
        return Repeater::make('lines')
            ->hiddenLabel()
            ->table([
                TableColumn::make('Item No.'),
                TableColumn::make('Item Description'),
                TableColumn::make('Quantity'),
                TableColumn::make('Price Before Discount'),
                TableColumn::make('% Discount'),
                TableColumn::make('Price After Discount'),
                TableColumn::make('Total'),
            ])
            ->schema([
                $this->lineItemCodeField(),
                $this->lineItemDescriptionField(),
                TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(0.001)
                    ->default(0)
                    ->live(onBlur: true)
                    ->extraInputAttributes(['style' => 'text-align:right'])
                    ->validationMessages([
                        'numeric' => 'Quantity must be a number.',
                    ]),
                TextInput::make('price_before_discount')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.001)
                    ->default(0)
                    ->live(onBlur: true)
                    ->extraInputAttributes(['style' => 'text-align:right']),
                TextInput::make('discount')
                    ->numeric()
                    ->step(0.001)
                    ->default(0)
                    ->minValue(0)
                    ->live(onBlur: true)
                    ->extraInputAttributes(['style' => 'text-align:right'])
                    ->rules([sprintf('lte:%s', InvoiceLine::MAX_DISCOUNT)])
                    ->validationMessages([
                        'lte' => sprintf('Discount cannot be greater than %s.', InvoiceLine::MAX_DISCOUNT),
                    ]),
                TextEntry::make('price_after_discount_display')
                    ->hiddenLabel()
                    ->alignEnd()
                    ->state(fn ($get): string => number_format(
                        (float) ($get('price_before_discount') ?? 0)
                            * (1 - (min(max((float) ($get('discount') ?? 0), 0), 100) / 100)),
                        3,
                    )),
                TextEntry::make('line_total_display')
                    ->hiddenLabel()
                    ->alignEnd()
                    ->state(fn ($get): string => number_format(
                        (float) ($get('quantity') ?? 0)
                            * ((float) ($get('price_before_discount') ?? 0)
                                * (1 - (min(max((float) ($get('discount') ?? 0), 0), 100) / 100))),
                        3,
                    )),
            ])
            ->defaultItems(1)
            ->reorderable(false)
            ->addActionLabel('Add Line Item');
    }

    protected function lineItemCodeField(): Select
    {
        return Select::make('item_code')
            ->label('Item No.')
            ->searchable()
            ->required()
            ->placeholder('Choose an item…')
            ->options(fn (): array => Item::query()
                ->orderBy('code')
                ->get()
                ->mapWithKeys(fn (Item $item): array => [
                    $item->code => "{$item->code} — {$item->description}",
                ])
                ->all())
            ->live(onBlur: true)
            ->afterStateUpdated(function ($state, $set): void {
                $item = Item::where('code', $state)->first();
                if ($item) {
                    $set('item_description', $item->description);
                    $set('price_before_discount', sprintf('%.3F', (float) $item->unit_price));
                }
            });
    }

    protected function lineItemDescriptionField(): Select
    {
        return Select::make('item_description')
            ->label('Item Description')
            ->searchable()
            ->required()
            ->placeholder('Choose an item…')
            ->options(fn (): array => Item::query()
                ->orderBy('description')
                ->get()
                ->mapWithKeys(fn (Item $item): array => [
                    $item->description => $item->description,
                ])
                ->all())
            ->live(onBlur: true)
            ->afterStateUpdated(function ($state, $set): void {
                $item = Item::where('description', $state)->first();
                if ($item) {
                    $set('item_code', $item->code);
                    $set('price_before_discount', sprintf('%.3F', (float) $item->unit_price));
                }
            });
    }

    protected function totalsSection(): Section
    {
        return Section::make()
            ->schema([
                Grid::make(2)
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                Select::make('sales_employee_id')
                                    ->label('Sales Employee')
                                    ->searchable()
                                    ->placeholder('Choose a sales employee…')
                                    ->options(fn (): array => SalesEmployee::query()
                                        ->orderBy('code')
                                        ->get()
                                        ->mapWithKeys(fn (SalesEmployee $employee): array => [
                                            $employee->id => "[{$employee->code}] — {$employee->name}",
                                        ])
                                        ->all()),
                                Textarea::make('remarks')
                                    ->label('Remarks')
                                    ->required()
                                    ->rows(4)
                                    ->maxLength(1000)
                                    ->validationMessages([
                                        'required' => 'Remarks is mandatory. Please enter a remark before saving.',
                                    ]),
                            ]),
                        Grid::make(1)
                            ->schema([
                                $this->totalPlaceholder('before', 'Total Before Discount'),
                                $this->totalPlaceholder('discount', 'Discount'),
                                $this->totalPlaceholder('after', 'Total After Discount'),
                            ]),
                    ]),
            ]);
    }

    protected function totalPlaceholder(string $key, string $label): Placeholder
    {
        return Placeholder::make("display_total_{$key}")
            ->label($label)
            ->content(fn (): string => number_format($this->totals()[$key], 3));
    }

    /**
     * @return array{before: float, discount: float, after: float}
     */
    protected function totals(): array
    {
        return InvoiceCalculator::totals($this->data['lines'] ?? []);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                FormComponent::make([EmbeddedSchema::make('form')])
                    ->id('invoice-form')
                    ->footer([
                        Actions::make([
                            Action::make('save_invoice')
                                ->label('Save Invoice')
                                ->icon('heroicon-m-check-circle')
                                ->color('primary')
                                ->size('lg')
                                ->requiresConfirmation(false)
                                ->action('save'),
                        ])->alignment(Alignment::End),
                    ]),
            ]);
    }
}
