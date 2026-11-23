<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_watches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('area', 16);
            $table->string('legacy_id', 64)->collation('utf8mb4_bin');
            $table->uuid('activation');
            $table->timestamp('created_at');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->unique(['user_id', 'area', 'legacy_id']);
            $table->index(['removed_at', 'notified_at', 'area', 'legacy_id']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('topic', 32);
            $table->boolean('email');
            $table->primary(['user_id', 'topic']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('record_watches');
    }
};
