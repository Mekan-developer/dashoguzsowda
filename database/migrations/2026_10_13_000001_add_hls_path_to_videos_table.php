<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HLS-версия ролика (master.m3u8 с несколькими качествами) — её собирает
     * ProcessVideoJob рядом с processed.mp4. null — HLS ещё нет (ролик в
     * обработке, нарезка не удалась или ролик загружен до перехода на HLS):
     * клиент играет processed.mp4.
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->string('hls_path')->nullable()->after('processed_path');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('hls_path');
        });
    }
};
