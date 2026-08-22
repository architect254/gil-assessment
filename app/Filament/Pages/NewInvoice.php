<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Item;
use App\Models\SalesEmployee;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form as FormComponent;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\DB;

class NewInvoice extends Page
{
    protected string $view = 'filament.pages.new-invoice';

    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = -1;

    public static function getNavigationLabel(): string
    {
        return 'New Invoice';
    }

    public function getTitle(): string
    {
        return 'New Invoice';
    }

    public function mount(): void
    {
        $this->form->fill([
            'posting_date' => now()->toDateString(),
            'lines' => [],
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

        $invoice = DB::transaction(function () use ($data, $customer, $lines, $totals): Invoice {
            $invoice = Invoice::query()->create([
                'no' => Invoice::nextNumber(),
                'customer_id' => $customer->id,
                'posting_date' => $data['posting_date'],
                'sales_employee_id' => ($data['sales_employee_id'] ?? null) ?: null,
                'remarks' => trim((string) ($data['remarks'] ?? '')),
                'total_before_discount' => sprintf('%.3F', $totals['before']),
                'discount' => sprintf('%.3F', $totals['discount']),
                'total_after_discount' => sprintf('%.3F', $totals['after']),
                'needs_approval' => $totals['after'] > Invoice::APPROVAL_THRESHOLD,
                'created_by' => auth()->id(),
            ]);

            $invoice->lines()->createMany($lines);

            return $invoice;
        });

        Notification::make()
            ->title('Invoice created')
            ->body("Invoice {$invoice->no} for {$customer->name} saved successfully.")
            ->success()
            ->send();

        $this->redirect(static::getUrl(), navigate: true);
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array{before: float, discount: float, after: float}}
     */
    protected function computeLines(array $rows): array
    {
        $lines = [];
        $before = 0.0;
        $after = 0.0;

        foreach ($rows as $index => $row) {
            $quantity = (float) ($row['quantity'] ?? 0);
            $priceBefore = (float) ($row['price_before_discount'] ?? 0);
            $discountPercent = min(max((float) ($row['discount'] ?? 0), 0), 100);

            if ($quantity <= 0 && blank($row['item_code'] ?? null)) {
                continue;
            }

            $priceAfter = round($priceBefore * (1 - ($discountPercent / 100)), 3);
            $total = round($quantity * $priceAfter, 3);
            $before += round($quantity * $priceBefore, 3);
            $after += $total;

            $lines[] = [
                'line_no' => $index + 1,
                'item_code' => trim((string) ($row['item_code'] ?? '')),
                'item_description' => trim((string) ($row['item_description'] ?? '')),
                'quantity' => sprintf('%.3F', $quantity),
                'price_before_discount' => sprintf('%.3F', $priceBefore),
                'discount' => sprintf('%.3F', $discountPercent),
                'price_after_discount' => sprintf('%.3F', $priceAfter),
                'total' => sprintf('%.3F', $total),
            ];
        }

        $before = round($before, 3);
        $after = round($after, 3);

        return [
            $lines,
            [
                'before' => $before,
                'discount' => round($before - $after, 3),
                'after' => $after,
            ],
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                $this->customerCodeField(),
                                $this->customerNameField(),
                                TextInput::make('document_no')
                                    ->label('No.')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->default((string) Invoice::nextNumber())
                                    ->maxLength(20),
                                DatePicker::make('posting_date')
                                    ->label('Posting Date')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d/m/Y'),
                            ]),
                        Placeholder::make('approval_notice')
                            ->label('')
                            ->hidden(fn (): bool => ! $this->needsApproval())
                            ->content(fn (): string => 'Invoice will go for approval – Amount: '
                                . number_format($this->totals()['after'], 3))
                            ->badge()
                            ->color('warning'),
                    ]),
                $this->linesRepeater(),
                $this->totalsSection(),
            ])
            ->statePath('data');
    }

    protected function customerCodeField(): TextInput
    {
        return TextInput::make('customer_code')
            ->label('Customer Code')
            ->required()
            ->maxLength(20)
            ->datalist(fn (): array => Customer::query()->orderBy('code')->pluck('code')->all())
            ->autocomplete('off')
            ->suffixAction(
                Action::make('chooseCustomerByCode')
                    ->iconButton()
                    ->icon('heroicon-m-list-bullet')
                    ->tooltip('Choose From List')
                    ->modalHeading('Choose Customer From List')
                    ->modalDescription('All customer records from the database.')
                    ->form([$this->customerPickerSelect(codeFirst: true)])
                    ->action(function (array $data, $livewire): void {
                        $this->applyPickedCustomer($data, $livewire);
                    }),
            )
            ->rule(
                static fn (): \Closure => static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (blank($value)) {
                        return;
                    }

                    if (! Customer::query()->where('code', trim((string) $value))->exists()) {
                        $fail("The customer code \"{$value}\" does not exist.");
                    }
                },
            );
    }

    protected function customerNameField(): TextInput
    {
        return TextInput::make('customer_name')
            ->label('Customer Name')
            ->required()
            ->maxLength(255)
            ->datalist(fn (): array => Customer::query()->orderBy('name')->pluck('name')->all())
            ->autocomplete('off')
            ->suffixAction(
                Action::make('chooseCustomerByName')
                    ->iconButton()
                    ->icon('heroicon-m-list-bullet')
                    ->tooltip('Choose From List')
                    ->modalHeading('Choose Customer From List')
                    ->modalDescription('Customer records listed by name.')
                    ->form([$this->customerPickerSelect(codeFirst: false)])
                    ->action(function (array $data, $livewire): void {
                        $this->applyPickedCustomer($data, $livewire);
                    }),
            )
            ->rule(
                static fn (): \Closure => static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (blank($value)) {
                        return;
                    }

                    if (! Customer::query()->where('name', trim((string) $value))->exists()) {
                        $fail("The customer name \"{$value}\" does not exist.");
                    }
                },
            );
    }

    protected function applyPickedCustomer(array $data, $livewire): void
    {
        if (! $customer = Customer::find($data['customer_id'] ?? null)) {
            return;
        }

        data_set($livewire, 'data.customer_code', $customer->code);
        data_set($livewire, 'data.customer_name', $customer->name);
    }

    /**
     * The list shows the customer name in the first column when
     * `$codeFirst` is false, otherwise the code comes first.
     */
    protected function customerPickerSelect(bool $codeFirst): Select
    {
        return Select::make('customer_id')
            ->label('Search customer')
            ->searchable()
            ->required()
            ->options(function () use ($codeFirst): array {
                return Customer::query()
                    ->orderBy($codeFirst ? 'code' : 'name')
                    ->get()
                    ->mapWithKeys(fn (Customer $customer): array => [
                        $customer->id => $codeFirst
                            ? "[{$customer->code}] — {$customer->name}"
                            : "{$customer->name} — [{$customer->code}]",
                    ])
                    ->all();
            })
            ->columnSpanFull();
    }

    protected function needsApproval(): bool
    {
        return $this->totals()['after'] > Invoice::APPROVAL_THRESHOLD;
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
                Placeholder::make('price_after_discount_display')
                    ->hiddenLabel()
                    ->content(fn ($get): string => number_format(
                        (float) ($get('price_before_discount') ?? 0)
                            * (1 - (min(max((float) ($get('discount') ?? 0), 0), 100) / 100)),
                        3,
                    )),
                Placeholder::make('line_total_display')
                    ->hiddenLabel()
                    ->content(fn ($get): string => number_format(
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

    protected function lineItemCodeField(): TextInput
    {
        return TextInput::make('item_code')
            ->label('Item No.')
            ->required()
            ->maxLength(20)
            ->datalist(fn (): array => Item::query()->orderBy('code')->pluck('code')->all())
            ->autocomplete('off')
            ->suffixAction(
                Action::make('chooseLineItemByCode')
                    ->iconButton()
                    ->icon('heroicon-m-list-bullet')
                    ->tooltip('Choose From List')
                    ->modalHeading('Choose Item From List')
                    ->modalDescription('All item records from the database.')
                    ->form([$this->itemPickerSelect(descriptionFirst: false)])
                    ->action(function (array $data, $livewire, $component): void {
                        $this->applyPickedItem($data, $livewire, $component);
                    }),
            )
            ->rule(
                static fn (): \Closure => static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (blank($value)) {
                        return;
                    }

                    if (! Item::query()->where('code', trim((string) $value))->exists()) {
                        $fail("The item \"{$value}\" does not exist. Create it under Items first.");
                    }
                },
            );
    }

    protected function lineItemDescriptionField(): TextInput
    {
        return TextInput::make('item_description')
            ->label('Item Description')
            ->required()
            ->maxLength(255)
            ->datalist(fn (): array => Item::query()->orderBy('description')->pluck('description')->all())
            ->autocomplete('off')
            ->suffixAction(
                Action::make('chooseLineItemByDescription')
                    ->iconButton()
                    ->icon('heroicon-m-list-bullet')
                    ->tooltip('Choose From List')
                    ->modalHeading('Choose Item From List')
                    ->modalDescription('Item records listed by description.')
                    ->form([$this->itemPickerSelect(descriptionFirst: true)])
                    ->action(function (array $data, $livewire, $component): void {
                        $this->applyPickedItem($data, $livewire, $component);
                    }),
            );
    }

    protected function applyPickedItem(array $data, $livewire, $component): void
    {
        if (! $item = Item::find($data['item_id'] ?? null)) {
            return;
        }

        $fieldPath = (string) str($component->getStatePath())->beforeLast('.');
        $quantity = (float) (data_get($livewire, "{$fieldPath}.quantity") ?? 0);
        $discountPercent = min(max((float) (data_get($livewire, "{$fieldPath}.discount") ?? 0), 0), 100);
        $priceAfter = round((float) $item->unit_price * (1 - ($discountPercent / 100)), 3);

        data_set($livewire, "{$fieldPath}.item_code", $item->code);
        data_set($livewire, "{$fieldPath}.item_description", $item->description);
        data_set($livewire, "{$fieldPath}.price_before_discount", sprintf('%.3F', (float) $item->unit_price));
    }

    /**
     * The list shows the item description in the first column when
     * `$descriptionFirst` is true, otherwise the item no. comes first.
     */
    protected function itemPickerSelect(bool $descriptionFirst): Select
    {
        return Select::make('item_id')
            ->label('Search item')
            ->searchable()
            ->required()
            ->options(function () use ($descriptionFirst): array {
                return Item::query()
                    ->orderBy($descriptionFirst ? 'description' : 'code')
                    ->get()
                    ->mapWithKeys(fn (Item $item): array => [
                        $item->id => $descriptionFirst
                            ? "{$item->description} — [{$item->code}]"
                            : "[{$item->code}] — {$item->description}",
                    ])
                    ->all();
            })
            ->columnSpanFull();
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
        $before = 0.0;
        $after = 0.0;

        foreach ($this->data['lines'] ?? [] as $row) {
            $quantity = (float) ($row['quantity'] ?? 0);
            $price = (float) ($row['price_before_discount'] ?? 0);
            $discountPercent = min(max((float) ($row['discount'] ?? 0), 0), 100);

            $before += $quantity * $price;
            $after += $quantity * round($price * (1 - ($discountPercent / 100)), 3);
        }

        $before = round($before, 3);
        $after = round($after, 3);

        return [
            'before' => $before,
            'discount' => round($before - $after, 3),
            'after' => $after,
        ];
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
