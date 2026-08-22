<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('description');
            $table->decimal('unit_price', 18, 3)->default(0);
            $table->timestamps();
        });

        Schema::create('sales_employees', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('no')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('posting_date');
            $table->foreignId('sales_employee_id')->nullable()->constrained()->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->decimal('total_before_discount', 18, 3)->default(0);
            $table->decimal('discount', 18, 3)->default(0);
            $table->decimal('total_after_discount', 18, 3)->default(0);
            $table->boolean('needs_approval')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('posting_date');
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('item_code', 20)->nullable();
            $table->string('item_description')->nullable();
            $table->decimal('quantity', 18, 3)->default(0);
            $table->decimal('price_before_discount', 18, 3)->default(0);
            $table->decimal('discount', 18, 3)->default(0);
            $table->decimal('price_after_discount', 18, 3)->default(0);
            $table->decimal('total', 18, 3)->default(0);
            $table->unsignedInteger('line_no')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('sales_employees');
        Schema::dropIfExists('items');
        Schema::dropIfExists('customers');
    }
};
