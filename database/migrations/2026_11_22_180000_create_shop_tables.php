<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('nip', 10)->unique();
            $table->string('state', 16)->default('preparation');
            $table->dateTime('activated_at')->nullable();
            $table->timestamps();

            $table->index(['state', 'id']);
        });

        Schema::create('shop_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('address');
            $table->string('city', 120);
            $table->string('phone', 32)->nullable();
            $table->text('opening_hours')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['shop_id', 'id']);
            $table->index(['shop_id', 'active', 'id']);
        });

        Schema::create('shop_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 8);
            // jeden właściciel na sklep: kolumna wyliczana, unikalna tylko dla właściciela
            $table->unsignedBigInteger('owner_of')->nullable()->virtualAs("case when role = 'owner' then shop_id end")->unique();
            $table->timestamps();

            $table->unique(['shop_id', 'user_id']);
            $table->index(['user_id', 'shop_id']);
        });

        Schema::create('shop_activation_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->dateTime('effective_from');
            $table->dateTime('effective_to');
            $table->string('reason');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shop_id', 'effective_from', 'id']);
        });

        // Klucz katalogowy (area, legacy_id) bez FK: katalog należy do innego modułu i bywa wymieniany wydaniami.
        Schema::create('shop_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('location_id');
            $table->string('area', 16);
            $table->string('legacy_id', 64)->collation('utf8mb4_bin');
            $table->string('variant', 120)->default('');
            $table->string('variant_key', 120)->default('');
            $table->unsignedInteger('price_grosze');
            $table->dateTime('valid_from');
            $table->dateTime('valid_to');
            $table->string('state', 16)->default('draft');
            $table->unsignedInteger('free_qty')->default(0);
            $table->unsignedInteger('reserved_qty')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('terms_version')->default(1);
            $table->timestamps();

            $table->unique(['shop_id', 'id']);
            $table->unique(['shop_id', 'location_id', 'area', 'legacy_id', 'variant_key'], 'shop_offers_identity_unique');
            $table->foreign(['shop_id', 'location_id'])->references(['shop_id', 'id'])->on('shop_locations')->restrictOnDelete();
            $table->index(['area', 'legacy_id', 'state', 'valid_to', 'shop_id', 'id'], 'shop_offers_catalog_index');
            $table->index(['shop_id', 'state', 'valid_to', 'id']);
        });
        DB::statement('alter table shop_offers add constraint shop_offers_price_check check (price_grosze > 0)');
        DB::statement('alter table shop_offers add constraint shop_offers_validity_check check (valid_to > valid_from)');

        Schema::create('shop_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('offer_id');
            $table->string('kind', 16);
            $table->integer('delta_free');
            $table->integer('delta_reserved')->default(0);
            $table->unsignedInteger('free_after');
            $table->unsignedInteger('reserved_after');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->dateTime('occurred_at', 6);

            $table->foreign(['shop_id', 'offer_id'])->references(['shop_id', 'id'])->on('shop_offers')->restrictOnDelete();
            $table->index(['offer_id', 'occurred_at', 'id']);
        });
    }

    public function down(): void
    {
        foreach (['shop_stock_movements', 'shop_offers', 'shop_activation_exceptions', 'shop_members', 'shop_locations', 'shops'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
