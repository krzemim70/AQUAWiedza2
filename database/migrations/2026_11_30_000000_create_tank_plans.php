<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tank_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('type', 64);
            $table->string('size_band', 64);
            $table->unsignedInteger('liters')->nullable();
            $table->string('state', 16)->default('draft');
            $table->foreignId('converted_tank_id')->nullable()->unique()->constrained('tanks')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'state', 'id']);
        });

        Schema::create('tank_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('tank_plans')->cascadeOnDelete();
            $table->string('area', 16);
            $table->string('catalog_id', 64)->collation('utf8mb4_bin');
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['plan_id', 'area', 'catalog_id']);
        });
        DB::statement('ALTER TABLE tank_plan_items ADD CONSTRAINT tank_plan_items_quantity_positive CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tank_plan_items');
        Schema::dropIfExists('tank_plans');
    }
};
