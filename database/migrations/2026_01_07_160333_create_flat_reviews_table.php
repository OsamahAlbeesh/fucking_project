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
    Schema::create('flat_reviews', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('flat_id');
        $table->unsignedBigInteger('user_id');
        $table->unsignedTinyInteger('rating');
        $table->text('review')->nullable();
        $table->timestamps();

        $table->foreign('flat_id')->references('id')->on('flats')->onDelete('cascade');
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flat_reviews');
    }
};
