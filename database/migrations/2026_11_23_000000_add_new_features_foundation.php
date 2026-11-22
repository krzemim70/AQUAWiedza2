<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('city', 100)->nullable();
        });

        Schema::table('tanks', function (Blueprint $table) {
            $table->unsignedInteger('volume_liters')->nullable();
        });

        Schema::create('tank_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tank_id')->constrained()->cascadeOnDelete();
            $table->string('area', 16);
            $table->string('catalog_id', 64)->collation('utf8mb4_bin');
            $table->date('installed_on')->nullable();
            $table->timestamps();
            $table->index('tank_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tank_equipment');
        Schema::table('tanks', fn (Blueprint $table) => $table->dropColumn('volume_liters'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('city'));
    }
};
