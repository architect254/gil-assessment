<?php

namespace Tests\Feature;

use App\Filament\Resources\MpesaTransactions\Pages\ListMpesaTransactions;
use App\Filament\Resources\MpesaTransactions\Pages\ViewMpesaTransaction;
use App\Models\MpesaTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MpesaTransactionResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_transactions_page_requires_authentication(): void
    {
        $this->get('/admin/mpesa-transactions')->assertRedirect();
    }

    public function test_authenticated_user_can_access_transactions_list(): void
    {
        $this->actingAs($this->user)
            ->get('/admin/mpesa-transactions')
            ->assertOk();
    }

    public function test_transactions_table_renders_records_and_columns(): void
    {
        // Create a transaction via the C2B confirmation callback
        $this->postJson('/api/c2b/confirmation', [
            'TransactionType' => 'Pay Bill',
            'TransID' => 'TX_RENDER_1',
            'TransTime' => '20260822143015',
            'TransAmount' => '3500.00',
            'BusinessShortCode' => '174379',
            'BillRefNumber' => 'INV-42',
            'MSISDN' => '254712345678',
            'FirstName' => 'Wanjiku',
            'LastName' => 'Njoroge',
        ]);

        $tx = MpesaTransaction::query()->where('transaction_id', 'TX_RENDER_1')->first();

        Livewire::actingAs($this->user)
            ->test(ListMpesaTransactions::class)
            ->assertCanSeeTableRecords([$tx])
            ->assertTableColumnExists('transaction_id')
            ->assertTableColumnExists('status')
            ->assertTableColumnExists('trans_amount')
            ->assertTableColumnExists('msisdn')
            ->assertTableColumnExists('bill_ref_number')
            ->assertTableColumnExists('first_name')
            ->assertTableColumnExists('created_at')
            ->assertSee('TX_RENDER_1')
            ->assertSee('3,500.00')
            ->assertSee('254712345678')
            ->assertSee('INV-42')
            ->assertSee('Wanjiku Njoroge');
    }

    public function test_view_transaction_page_renders_payload_details(): void
    {
        // Create a transaction via the C2B confirmation callback
        $this->postJson('/api/c2b/confirmation', [
            'TransactionType' => 'Pay Bill',
            'TransID' => 'TX_VIEW_DETAIL_1',
            'TransTime' => '20260822143015',
            'TransAmount' => '7500.00',
            'BusinessShortCode' => '174379',
            'BillRefNumber' => 'INV-99',
            'MSISDN' => '254712345678',
            'FirstName' => 'Test',
            'LastName' => 'User',
        ]);

        $tx = MpesaTransaction::query()->where('transaction_id', 'TX_VIEW_DETAIL_1')->first();

        Livewire::actingAs($this->user)
            ->test(ViewMpesaTransaction::class, ['record' => $tx->getRouteKey()])
            ->assertSee('Transaction Overview')
            ->assertSee('TX_VIEW_DETAIL_1')
            ->assertSee('7,500.00')
            ->assertSee('254712345678')
            ->assertSee('Daraja API Response')
            ->assertSee('Raw Daraja Webhook Payload');
    }
}
