<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Brak danych dostępu = Tank. Fishroom to tylko data końca; zawieszenie to znacznik czasu.
        Schema::table('users', function (Blueprint $table) {
            $table->string('type', 16)->default('aquarist')->after('password');
            $table->boolean('is_admin')->default(false)->after('type');
            $table->dateTime('fishroom_ends_at')->nullable()->after('is_admin');
            $table->dateTime('suspended_at')->nullable()->after('fishroom_ends_at');
            $table->dateTime('adult_declared_at')->nullable()->after('suspended_at');
            $table->index('fishroom_ends_at');
        });

        Schema::create('access_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 24);
            $table->dateTime('fishroom_ends_at')->nullable();
            $table->string('reason');
            $table->dateTime('created_at');
            $table->index(['user_id', 'id']);
        });

        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('document', 16);
            $table->string('document_version', 16);
            $table->boolean('granted');
            $table->string('ip', 45)->nullable();
            $table->dateTime('created_at');
            $table->index(['user_id', 'document']);
        });

        Schema::create('shop_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('nip', 10)->index();
            $table->string('address');
            $table->string('phone', 32);
            $table->string('status', 24)->default('pending_verification');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->string('decision_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_applications');
        Schema::dropIfExists('consents');
        Schema::dropIfExists('access_changes');
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['fishroom_ends_at']);
            $table->dropColumn(['type', 'is_admin', 'fishroom_ends_at', 'suspended_at', 'adult_declared_at']);
        });
    }
};
