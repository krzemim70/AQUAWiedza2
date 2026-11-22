<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rejestr identyfikatorów: wiersze nigdy nie są usuwane (blokuje to model), więc ID nie wraca do puli.
        Schema::create('catalog_id_registry', function (Blueprint $table) {
            $table->id();
            $table->enum('area', ['fauna', 'flora', 'equipment', 'care']);
            $table->string('legacy_id', 64)->collation('utf8mb4_bin');
            $table->enum('status', ['active', 'future', 'retired']);
            $table->string('source_status', 64)->nullable();
            $table->string('name')->nullable();
            $table->timestamps();

            $table->unique(['area', 'legacy_id']);
        });

        Schema::create('catalog_records', function (Blueprint $table) {
            $table->id();
            $table->enum('area', ['fauna', 'flora', 'equipment', 'care']);
            $table->string('legacy_id', 64)->collation('utf8mb4_bin');
            $table->timestamps();

            $table->unique(['area', 'legacy_id']);
            $table->foreign(['area', 'legacy_id'])->references(['area', 'legacy_id'])->on('catalog_id_registry')->restrictOnDelete();
        });

        Schema::create('catalog_import_batches', function (Blueprint $table) {
            $table->id();
            $table->enum('state', ['pending', 'active', 'superseded', 'failed']);
            $table->unsignedTinyInteger('active_slot')->nullable()->unique(); // 1 = aktywna; UNIQUE pilnuje jednej aktywnej partii
            $table->char('checksum', 64)->nullable()->unique(); // pusty dla nieudanych, żeby poprawioną paczkę dało się wgrać ponownie
            $table->json('files');
            $table->json('counts');
            $table->json('errors');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('activated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('catalog_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('catalog_import_batches')->cascadeOnDelete();
            $table->foreignId('record_id')->constrained('catalog_records');
            $table->unsignedInteger('position');
            $table->string('name_pl')->nullable();
            $table->string('name_latin')->nullable();
            $table->string('product_name')->nullable();
            $table->json('fields');
            $table->json('tokens');
            $table->text('search_text');
            $table->timestamps();

            $table->unique(['batch_id', 'record_id']);
            $table->index(['batch_id', 'position']);
        });

        Schema::create('catalog_descriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id')->unique()->constrained('catalog_records');
            $table->longText('body');
            $table->text('source_ref')->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('edited_at')->nullable();
            $table->timestamps();
        });

        Schema::create('catalog_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id')->constrained('catalog_records');
            $table->string('url', 512)->nullable();
            $table->string('file_path', 512)->nullable();
            $table->string('source_url', 512)->nullable();
            $table->string('author')->nullable();
            $table->string('licence')->nullable();
            $table->boolean('verified')->default(false);
            $table->dateTime('verified_at')->nullable();
            $table->timestamps();
        });
    }
};
