<?php

namespace Tests\Feature;

use App\Jobs\QueryStkPushStatus;
use App\Models\MpesaTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MpesaReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function createPendingWithAge(int $minutesAgo): MpesaTransaction
    {
        $id = DB::table('mpesa_transactions')->insertGetId([
            'status' => MpesaTransaction::STATUS_PENDING,
            'msisdn' => '254712345678',
            'trans_amount' => '1000.00',
            'bill_ref_number' => 'INV-RECON',
            'created_at' => now()->subMinutes($minutesAgo),
            'updated_at' => now()->subMinutes($minutesAgo),
        ]);

        return MpesaTransaction::findOrFail($id);
    }

    public function test_pending_transactions_older_than_3_minutes_are_dispatched_for_query(): void
    {
        Queue::fake();

        $stale = $this->createPendingWithAge(5);
        DB::table('mpesa_transactions')
            ->where('id', $stale->id)
            ->update(['checkout_request_id' => 'ws_CO_RECON_STALE']);

        $stale->refresh();

        Artisan::call('mpesa:reconcile');

        Queue::assertPushed(QueryStkPushStatus::class, function ($job) use ($stale) {
            return $job->mpesaTransactionId === $stale->id;
        });
    }

    public function test_recent_pending_transactions_are_skipped(): void
    {
        Queue::fake();

        $recent = $this->createPendingWithAge(1);
        DB::table('mpesa_transactions')
            ->where('id', $recent->id)
            ->update(['checkout_request_id' => 'ws_CO_RECON_RECENT']);

        Artisan::call('mpesa:reconcile');

        Queue::assertNotPushed(QueryStkPushStatus::class);
    }

    public function test_transactions_stuck_beyond_15_minutes_are_force_timed_out(): void
    {
        Queue::fake();

        $expired = $this->createPendingWithAge(20);
        DB::table('mpesa_transactions')
            ->where('id', $expired->id)
            ->update(['checkout_request_id' => 'ws_CO_RECON_EXPIRED']);

        Artisan::call('mpesa:reconcile');

        $expired->refresh();
        $this->assertSame(MpesaTransaction::STATUS_TIMEOUT, $expired->status);
        $this->assertNotNull($expired->resolved_at);
        $this->assertStringContainsString('15 minutes', $expired->result_desc);
    }

    public function test_already_settled_transactions_are_not_dispatched(): void
    {
        Queue::fake();

        DB::table('mpesa_transactions')->insert([
            'status' => MpesaTransaction::STATUS_SUCCESS,
            'checkout_request_id' => 'ws_CO_RECON_SETTLED',
            'msisdn' => '254712345678',
            'trans_amount' => '3000.00',
            'bill_ref_number' => 'INV-43',
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        Artisan::call('mpesa:reconcile');

        Queue::assertNotPushed(QueryStkPushStatus::class);
    }

    public function test_reconcile_command_logs_summary(): void
    {
        Queue::fake();

        $tx = $this->createPendingWithAge(5);
        DB::table('mpesa_transactions')
            ->where('id', $tx->id)
            ->update(['checkout_request_id' => 'ws_CO_LOG_TEST']);

        Artisan::call('mpesa:reconcile');

        $this->assertStringContainsString('1 dispatched', Artisan::output());
    }
}
