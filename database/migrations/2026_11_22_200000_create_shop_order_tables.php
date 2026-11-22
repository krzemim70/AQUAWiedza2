<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stały numer techniczny placówki w EAN (1–9999), nadawany przy pierwszej decyzji; niezależny od klucza głównego.
        Schema::table('shop_locations', function (Blueprint $table) {
            $table->unsignedSmallInteger('ean_no')->nullable()->unique()->after('active');
        });

        // Jednorzędowy licznik globalnej sekwencji EAN (blokowany przy decyzji, bez MAX()+1).
        Schema::create('shop_ean_sequence', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedInteger('last')->default(0);
        });
        DB::table('shop_ean_sequence')->insert(['id' => 1, 'last' => 0]);

        Schema::create('shop_cart_items', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('offer_id');
            $table->unsignedInteger('qty');
            $table->unsignedInteger('price_seen_grosze');
            $table->timestamps();

            $table->primary(['user_id', 'offer_id']);
            $table->foreign(['shop_id', 'offer_id'])->references(['shop_id', 'id'])->on('shop_offers')->restrictOnDelete();
        });
        DB::statement('alter table shop_cart_items add constraint shop_cart_items_qty_check check (qty > 0)');

        Schema::create('shop_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('idempotency_key', 64);
            $table->date('business_date');
            $table->unsignedSmallInteger('orders_count');
            $table->dateTime('submitted_at', 6);

            $table->unique(['user_id', 'idempotency_key']);
        });

        Schema::create('shop_customer_request_days', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->unsignedInteger('used_orders')->default(0);

            $table->primary(['user_id', 'business_date']);
        });

        Schema::create('shop_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkout_id')->constrained('shop_checkouts')->restrictOnDelete();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('location_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('state', 16)->default('new');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('total_grosze');
            $table->char('ean13', 13)->charset('ascii')->nullable()->unique();
            $table->string('rejection_code', 32)->nullable();
            $table->string('note')->nullable();
            $table->dateTime('decided_at', 6)->nullable();
            $table->dateTime('finished_at', 6)->nullable();
            $table->dateTime('pseudonymize_at', 6)->nullable();
            $table->dateTime('pseudonymized_at', 6)->nullable();
            $table->timestamps();

            $table->unique(['checkout_id', 'shop_id']);
            $table->unique(['shop_id', 'id']);
            $table->foreign(['shop_id', 'location_id'])->references(['shop_id', 'id'])->on('shop_locations')->restrictOnDelete();
            $table->index(['shop_id', 'state', 'created_at', 'id']);
            $table->index(['user_id', 'created_at', 'id']);
            $table->index(['pseudonymize_at', 'id']);
        });

        Schema::create('shop_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('offer_id');
            $table->string('area', 16);
            $table->string('legacy_id', 64)->collation('utf8mb4_bin');
            $table->string('name');
            $table->string('variant', 120)->default('');
            $table->unsignedInteger('price_grosze');
            $table->unsignedInteger('terms_version');
            $table->unsignedInteger('qty');

            $table->unique(['order_id', 'offer_id']);
            $table->foreign(['shop_id', 'order_id'])->references(['shop_id', 'id'])->on('shop_orders')->restrictOnDelete();
            $table->foreign(['shop_id', 'offer_id'])->references(['shop_id', 'id'])->on('shop_offers')->restrictOnDelete();
        });
        DB::statement('alter table shop_order_items add constraint shop_order_items_qty_check check (qty > 0)');

        // Migawka kontaktu; jedyny rekord z danymi klienta, usuwany przy pseudonimizacji.
        Schema::create('shop_order_contacts', function (Blueprint $table) {
            $table->foreignId('order_id')->primary()->constrained('shop_orders')->cascadeOnDelete();
            $table->string('nick');
            $table->string('email');
            $table->string('phone', 32);
        });

        Schema::create('shop_order_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->foreignId('order_id')->constrained('shop_orders')->restrictOnDelete();
            $table->unsignedInteger('order_version');
            $table->string('from_state', 16)->nullable();
            $table->string('to_state', 16);
            $table->string('reason')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('occurred_at', 6);

            $table->unique(['order_id', 'order_version']);
            $table->index(['shop_id', 'occurred_at', 'id']);
        });

        Schema::create('shop_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->unique()->constrained('shop_order_items')->restrictOnDelete();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('offer_id');
            $table->unsignedInteger('qty');
            $table->string('state', 12)->default('held');
            $table->dateTime('held_at', 6);
            $table->dateTime('resolved_at', 6)->nullable();

            $table->foreign(['shop_id', 'offer_id'])->references(['shop_id', 'id'])->on('shop_offers')->restrictOnDelete();
            $table->index(['offer_id', 'state', 'id']);
        });

        Schema::create('shop_order_payments', function (Blueprint $table) {
            $table->foreignId('order_id')->primary()->constrained('shop_orders')->restrictOnDelete();
            $table->unsignedBigInteger('amount_grosze');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('confirmed_at', 6);
        });

        Schema::create('shop_daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->date('business_date');
            $table->dateTime('period_from');
            $table->dateTime('period_to');
            $table->dateTime('generated_at');
            $table->json('data');

            $table->unique(['shop_id', 'business_date']);
        });
    }

    public function down(): void
    {
        foreach ([
            'shop_daily_reports', 'shop_order_payments', 'shop_reservations', 'shop_order_events', 'shop_order_contacts',
            'shop_order_items', 'shop_orders', 'shop_customer_request_days', 'shop_checkouts', 'shop_cart_items', 'shop_ean_sequence',
        ] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('shop_locations', fn (Blueprint $table) => $table->dropColumn('ean_no'));
    }
};
