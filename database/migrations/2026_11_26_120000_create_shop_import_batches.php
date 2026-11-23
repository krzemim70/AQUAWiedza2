<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->char('file_sha256', 64);
            $table->string('file_path')->nullable();
            $table->enum('state', ['preview', 'queued', 'applied', 'failed'])->default('preview');
            $table->unsignedInteger('last_line')->default(0);
            $table->json('counts');
            $table->json('report');
            $table->json('approved')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_import_batches');
    }
};
