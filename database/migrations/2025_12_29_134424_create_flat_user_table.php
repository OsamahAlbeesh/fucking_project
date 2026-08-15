<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void{
        Schema::create('flat_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('flat_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['rent', 'buy'])->default('rent');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('rate')->default(1);
            $table->enum('status',['Accepted','Pending','Rejected','Sold','Awaiting_Payment'])->default('Pending');
            $table->timestamps();
    });

    }
    public function down(): void{
        Schema::dropIfExists('flat_user');
    }
};
