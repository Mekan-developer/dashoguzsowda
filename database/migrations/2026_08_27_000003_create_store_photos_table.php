<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Галерея магазина (mobile_docs/BACKEND_API.md §1 — поле `photos`/`photo_urls`). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['store_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_photos');
    }
};
