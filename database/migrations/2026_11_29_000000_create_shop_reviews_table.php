<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->unique();
            $table->foreignId('customer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('hidden_reason')->nullable();
            $table->timestamps();

            $table->foreign(['shop_id', 'order_id'])->references(['shop_id', 'id'])->on('shop_orders')->restrictOnDelete();
            $table->index(['shop_id', 'hidden_reason', 'id']);
        });

        DB::statement('ALTER TABLE shop_reviews ADD CONSTRAINT shop_reviews_rating_check CHECK (rating BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_reviews');
    }
};
