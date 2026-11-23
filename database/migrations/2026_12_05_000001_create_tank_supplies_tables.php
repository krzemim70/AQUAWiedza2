<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tank_supplies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tank_id')->constrained('tanks')->cascadeOnDelete();
            $table->string('catalog_id', 64);
            $table->string('unit', 16);
            $table->decimal('package_amount', 10, 2);
            $table->decimal('remaining_amount', 10, 2);
            $table->timestamp('balance_at');
            $table->decimal('dose_amount', 10, 2);
            $table->decimal('dose_per_liters', 10, 2)->nullable();
            $table->unsignedSmallInteger('interval_days')->nullable();
            $table->unsignedBigInteger('preferred_shop_id')->nullable();
            $table->timestamp('low_stock_notified_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['tank_id', 'archived_at']);
        });

        Schema::create('tank_supply_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supply_id')->constrained('tank_supplies')->cascadeOnDelete();
            $table->timestamp('occurred_at');
            $table->decimal('delta_amount', 10, 2);
            $table->string('source', 16)->default('manual');
            $table->string('device_sensor_id', 64)->nullable();
            $table->string('idempotency_key', 64);
            $table->timestamp('created_at')->nullable();

            $table->unique(['supply_id', 'idempotency_key']);
            $table->index(['supply_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tank_supply_events');
        Schema::dropIfExists('tank_supplies');
    }
};
