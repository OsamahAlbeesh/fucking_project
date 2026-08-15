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
        Schema::create('flats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('governorate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->enum('category', ['flat', 'villa', 'land', 'shop', 'office']);
            $table->text('details');
            $table->string('location');
            $table->bigInteger('price')->nullable();
            $table->bigInteger('rent_price')->nullable();
            $table->enum('status', ['available', 'rented', 'sold'])->default('available');
            $table->float('rate')->default('0');
            $table->string('flat_image');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flats');
    }
};
