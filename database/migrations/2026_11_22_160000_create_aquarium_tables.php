<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pasma i typ to etykiety słowników z kodu (te same co w katalogu i Doradcy).
        Schema::create('tanks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('type', 64);
            $table->string('size_band', 64);
            $table->string('fish_size_band', 64)->nullable();
            $table->string('temp_band', 64)->nullable();
            $table->string('ph_band', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'id']);
        });

        Schema::create('tank_livestock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tank_id')->constrained()->cascadeOnDelete();
            $table->string('area', 16);
            $table->string('catalog_id', 64)->collation('utf8mb4_bin');
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['tank_id', 'area', 'catalog_id']);
        });
        DB::statement('ALTER TABLE tank_livestock ADD CONSTRAINT tank_livestock_quantity_positive CHECK (quantity > 0)');

        Schema::create('water_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tank_id')->constrained()->cascadeOnDelete();
            $table->dateTime('measured_at');
            $table->decimal('ph', 4, 2)->nullable();
            $table->decimal('temp_c', 4, 1)->nullable();
            $table->decimal('kh', 5, 1)->nullable();
            $table->decimal('gh', 5, 1)->nullable();
            $table->decimal('no3', 6, 1)->nullable();
            $table->decimal('po4', 6, 3)->nullable();
            $table->decimal('no2', 6, 3)->nullable();
            $table->decimal('nh4', 6, 3)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->index(['tank_id', 'measured_at', 'id']);
        });
        DB::statement('ALTER TABLE water_measurements ADD CONSTRAINT water_measurements_any_value CHECK (
            ph IS NOT NULL OR temp_c IS NOT NULL OR kh IS NOT NULL OR gh IS NOT NULL
            OR no3 IS NOT NULL OR po4 IS NOT NULL OR no2 IS NOT NULL OR nh4 IS NOT NULL)');

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tank_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date');
            $table->text('content');
            $table->timestamps();
            $table->index(['tank_id', 'entry_date', 'id']);
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tank_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->string('label', 100)->nullable();
            $table->date('starts_on');
            $table->unsignedSmallInteger('interval_days')->nullable();
            $table->string('state', 16)->default('active');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['tank_id', 'state', 'id']);
        });
        DB::statement('ALTER TABLE reminders ADD CONSTRAINT reminders_interval_positive CHECK (interval_days IS NULL OR interval_days > 0)');

        Schema::create('reminder_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reminder_id')->constrained()->cascadeOnDelete();
            $table->date('due_on');
            $table->string('state', 16)->default('pending');
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['reminder_id', 'due_on']);
            $table->index(['state', 'due_on', 'id']);
        });
        DB::statement("ALTER TABLE reminder_occurrences ADD CONSTRAINT reminder_occurrences_done_has_time CHECK (state <> 'done' OR completed_at IS NOT NULL)");
    }
};
