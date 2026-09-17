<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->after('user_id')
                ->constrained();

            // Лента с фильтром по категории: WHERE category_id=? AND status='approved' ORDER BY created_at DESC
            $table->index(['category_id', 'status', 'created_at'], 'videos_category_feed_index');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex('videos_category_feed_index');
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
