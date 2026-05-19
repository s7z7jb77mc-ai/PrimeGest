<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {
            $table->enum('plan', ['free', 'premium', 'pro'])
                ->default('free')
                ->after('name');
            $table->timestamp('plan_expires_at')->nullable()->after('plan');
            $table->integer('storage_used_mb')->default(0)->after('plan_expires_at');
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained()->cascadeOnDelete();
            $table->enum('plan', ['free', 'premium', 'pro']);
            $table->decimal('amount', 8, 2);
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'expired', 'failed'])->default('pending');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::table('entreprises', function (Blueprint $table) {
            $table->dropColumn(['plan', 'plan_expires_at', 'storage_used_mb']);
        });
    }
};
