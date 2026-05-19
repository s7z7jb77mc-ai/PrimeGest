<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('netikash_transaction_id')->nullable()->after('payment_reference');
            $table->string('netikash_order_id')->nullable()->after('netikash_transaction_id');
            $table->json('netikash_payload')->nullable()->after('netikash_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['netikash_transaction_id', 'netikash_order_id', 'netikash_payload']);
        });
    }
};
