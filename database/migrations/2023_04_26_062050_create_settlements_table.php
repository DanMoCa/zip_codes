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
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('key');
            $table->string('name');
            $table->string('zone_type');
            $table->unsignedBigInteger('settlement_type_id');
            $table->string('zip_code');
            $table->foreign('settlement_type_id')->references('id')->on('settlement_types')->onDelete('cascade');
            $table->foreign('zip_code')->references('zip_code')->on('zip_codes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
