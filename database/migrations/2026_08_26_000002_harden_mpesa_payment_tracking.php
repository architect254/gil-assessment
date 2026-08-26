<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mpesa_transactions', function (Blueprint $table) {
            $table->string('mpesa_receipt_number')->nullable()->after('merchant_request_id');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete()->after('mpesa_receipt_number');
            $table->timestamp('raised_at')->nullable()->after('invoice_id');
            $table->timestamp('resolved_at')->nullable()->after('raised_at');
        });
    }

    public function down(): void
    {
        Schema::table('mpesa_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'mpesa_receipt_number',
                'invoice_id',
                'raised_at',
                'resolved_at',
            ]);
        });
    }
};
