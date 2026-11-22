<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tank_livestock', function (Blueprint $table) {
            $table->json('acknowledged_findings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tank_livestock', function (Blueprint $table) {
            $table->dropColumn('acknowledged_findings');
        });
    }
};
