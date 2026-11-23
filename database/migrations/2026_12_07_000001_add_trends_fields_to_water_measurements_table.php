<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('water_measurements', function (Blueprint $table): void {
            $table->string('source', 16)->default('manual')->after('measured_at');
            $table->string('method', 16)->nullable()->after('source');
            $table->string('calibration_status', 20)->nullable()->after('method');
            $table->string('device_sensor_id', 64)->nullable()->after('calibration_status');
            $table->string('idempotency_key', 64)->nullable()->after('device_sensor_id');
            $table->decimal('ec_us_cm', 8, 1)->nullable()->after('nh4');

            $table->index(['tank_id', 'measured_at']);
            $table->unique(['tank_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('water_measurements', function (Blueprint $table): void {
            $table->dropUnique(['tank_id', 'idempotency_key']);
            $table->dropIndex(['tank_id', 'measured_at']);
            $table->dropColumn([
                'source',
                'method',
                'calibration_status',
                'device_sensor_id',
                'idempotency_key',
                'ec_us_cm',
            ]);
        });
    }
};
