<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('reference_number', 30)->nullable()->unique()->after('payment_method');
            $table->enum('demo_status', [
                'pending',
                'paid_simulated',
                'refund_requested',
                'refunded_simulated',
                'frozen',
                'cancelled',
            ])->nullable()->after('status');
            $table->timestamp('paid_at')->nullable()->after('demo_status');
            $table->timestamp('refund_requested_at')->nullable()->after('paid_at');
            $table->timestamp('refunded_at')->nullable()->after('refund_requested_at');
            $table->foreignId('refunded_by')->nullable()->after('refunded_at')->constrained('users')->nullOnDelete();
            $table->text('admin_note')->nullable()->after('refunded_by');
        });

        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_user_id')->constrained('property_user')->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->enum('status', ['open', 'under_review', 'resolved_for_customer', 'resolved_for_landlord', 'closed'])->default('open');
            $table->text('admin_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reservation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_user_id')->constrained('property_user')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_events');
        Schema::dropIfExists('disputes');

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['refunded_by']);
            $table->dropUnique(['reference_number']);
            $table->dropColumn([
                'reference_number',
                'demo_status',
                'paid_at',
                'refund_requested_at',
                'refunded_at',
                'refunded_by',
                'admin_note',
            ]);
        });
    }
};
