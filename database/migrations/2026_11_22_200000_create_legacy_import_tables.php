<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mapa importu z WordPressa: ponowny import nie dubluje rekordów.
        Schema::create('legacy_id_map', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 32);
            $table->string('legacy_id', 64);
            $table->unsignedBigInteger('new_id');
            $table->unique(['entity', 'legacy_id']);
        });

        // Stary litraż zbiornika zostaje osobno: pasma wymagają ręcznego uzupełnienia.
        Schema::table('tanks', fn (Blueprint $table) => $table->unsignedSmallInteger('legacy_volume_liters')->nullable()->after('notes'));
    }

    public function down(): void
    {
        Schema::table('tanks', fn (Blueprint $table) => $table->dropColumn('legacy_volume_liters'));
        Schema::dropIfExists('legacy_id_map');
    }
};
