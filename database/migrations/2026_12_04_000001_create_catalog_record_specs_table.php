<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_record_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id')->unique()->constrained('catalog_records')->cascadeOnDelete();
            $table->string('status', 24)->index();
            $table->string('source_kind', 32);
            $table->string('source_ref', 500)->nullable();
            $table->text('evidence_quote');
            $table->string('ean', 32)->nullable()->index();
            $table->json('attributes');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('disabled_reason', 255)->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_record_specs');
    }
};
