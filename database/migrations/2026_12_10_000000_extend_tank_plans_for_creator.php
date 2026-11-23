<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tank_plans', function (Blueprint $table) {
            $table->string('temp_band', 32)->nullable()->after('liters');
            $table->string('ph_band', 32)->nullable()->after('temp_band');
        });

        Schema::table('tank_plan_items', function (Blueprint $table) {
            $table->string('area', 32)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tank_plans', function (Blueprint $table) {
            $table->dropColumn(['temp_band', 'ph_band']);
        });
    }
};
