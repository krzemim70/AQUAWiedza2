<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Pasma zbiornika zapisujemy jako stabilne kody Doradcy zamiast etykiet; brak pasma wielkości jest dozwolony (np. po imporcie). */
return new class extends Migration
{
    /** @var array<string, array<string, string>> kolumna => etykieta => kod */
    private const MAP = [
        'size_band' => [
            'Nano (< 40L)' => 'nano', 'Małe (40L - 100L)' => 'small', 'Średnie (101L - 250L)' => 'medium',
            'Duże (251L - 500L)' => 'large', 'Bardzo duże (> 500L)' => 'huge',
        ],
        'fish_size_band' => [
            'Nano (do 3 cm)' => 'nano', 'Mała (3-5 cm)' => 'small', 'Średnia (5-10 cm)' => 'medium',
            'Duża (10-15 cm)' => 'large', 'B. duża (>15 cm)' => 'huge',
        ],
        'temp_band' => [
            'Zimnolubne (poniżej 18)' => 'cold', 'Grzałka sporadycznie (18-21,5)' => 'cool',
            'Grzałka często (22-25,5)' => 'warm', 'Grzałka b. często (26-30)' => 'hot',
        ],
        'ph_band' => [
            'Woda kwaśna (poniżej 6.8)' => 'acidic', 'Woda obojętna (6.8 - 7.5)' => 'neutral', 'Woda zasadowa (powyżej 7.5)' => 'alkaline',
        ],
    ];

    public function up(): void
    {
        Schema::table('tanks', fn (Blueprint $table) => $table->string('size_band', 64)->nullable()->change());
        foreach (self::MAP as $column => $codes) {
            foreach ($codes as $label => $code) {
                DB::table('tanks')->where($column, $label)->update([$column => $code]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::MAP as $column => $codes) {
            foreach ($codes as $label => $code) {
                DB::table('tanks')->where($column, $code)->update([$column => $label]);
            }
        }
    }
};
