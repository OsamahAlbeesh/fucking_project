<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
            Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('flat_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('flat_user_id')->nullable()->constrained('flat_user')->onDelete('cascade');
            $table->string('type')->default('payment');
            $table->bigInteger('amount');
            $table->bigInteger('commission')->default(0);
            $table->string('payment_method')->default('wallet'); // 'wallet', 'stripe', 'manual'
            $table->string('stripe_payment_id')->nullable();
            $table->string('stripe_session_id')->nullable();
            $table->json('payment_details')->nullable();
            $table->enum('status', ['pending', 'completed', 'rejected'])->default('pending');
            $table->string('contract_pdf')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
