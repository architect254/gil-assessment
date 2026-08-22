<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mpesa_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type')->nullable();
            $table->string('transaction_id')->nullable()->index();
            $table->string('trans_time', 20)->nullable();
            $table->string('trans_amount', 30)->nullable();
            $table->string('business_short_code', 20)->nullable();
            $table->string('bill_ref_number')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('org_account_balance', 30)->nullable();
            $table->string('third_party_trans_id')->nullable();
            $table->string('msisdn', 20)->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->text('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mpesa_transactions');
    }
};
