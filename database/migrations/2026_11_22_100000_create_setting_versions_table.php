<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setting_versions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 96);
            $table->json('value');
            $table->dateTime('effective_from', 6); // UTC
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason');
            $table->timestamps();

            $table->unique(['key', 'effective_from']);
        });
    }
};
