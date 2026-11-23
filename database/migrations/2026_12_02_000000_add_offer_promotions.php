<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_offers', function (Blueprint $table) {
            $table->unsignedInteger('promo_price_grosze')->nullable()->after('price_grosze');
            $table->dateTime('promo_from')->nullable()->after('promo_price_grosze');
            $table->dateTime('promo_to')->nullable()->after('promo_from');
        });

        // Okresy faktycznie stosowanych cen (regularna: valid_to null = trwa; promocja: okno). Oferta bez wiersza nie ma historii.
        Schema::create('shop_offer_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('shop_offers')->cascadeOnDelete();
            $table->string('kind', 8);
            $table->unsignedInteger('price_grosze');
            $table->dateTime('valid_from');
            $table->dateTime('valid_to')->nullable();

            $table->index(['offer_id', 'valid_from']);
        });

        Schema::table('shop_order_items', function (Blueprint $table) {
            $table->unsignedInteger('regular_price_grosze')->nullable()->after('price_grosze');
        });

        // Dla ofert już opublikowanych nie znamy dawnych cen: historia zaczyna się dziś.
        DB::table('shop_offers')->where('state', 'published')->orderBy('id')->each(fn ($o) => DB::table('shop_offer_price_history')->insert([
            'offer_id' => $o->id, 'kind' => 'regular', 'price_grosze' => $o->price_grosze, 'valid_from' => now(),
        ]));
    }

    public function down(): void
    {
        Schema::table('shop_order_items', fn (Blueprint $table) => $table->dropColumn('regular_price_grosze'));
        Schema::dropIfExists('shop_offer_price_history');
        Schema::table('shop_offers', fn (Blueprint $table) => $table->dropColumn(['promo_price_grosze', 'promo_from', 'promo_to']));
    }
};
