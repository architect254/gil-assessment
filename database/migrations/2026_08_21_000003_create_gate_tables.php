<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->string('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('id_number', 50)->nullable();
            $table->string('phone', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('gate_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('vehicle_number', 30);
            $table->string('driver_name');
            $table->string('driver_id_number', 50)->nullable();
            $table->string('driver_phone', 30)->nullable();
            $table->timestamp('gated_in_at')->nullable();
            $table->foreignId('gated_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('gated_out_at')->nullable();
            $table->foreignId('gated_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 10)->default('in')->index();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_logs');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('vehicles');
    }
};
