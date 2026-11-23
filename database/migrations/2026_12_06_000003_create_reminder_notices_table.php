<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occurrence_id')->unique()->constrained('reminder_occurrences')->cascadeOnDelete();
            $table->timestamp('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_notices');
    }
};
