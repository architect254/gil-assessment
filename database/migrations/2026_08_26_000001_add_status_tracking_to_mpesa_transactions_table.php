<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mpesa_transactions', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('id');
            $table->string('result_code', 10)->nullable()->after('status');
            $table->text('result_desc')->nullable()->after('result_code');
            $table->string('checkout_request_id')->nullable()->index()->after('result_desc');
            $table->string('merchant_request_id')->nullable()->after('checkout_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('mpesa_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'result_code',
                'result_desc',
                'checkout_request_id',
                'merchant_request_id',
            ]);
        });
    }
};
