<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Несколько FCM-токенов на пользователя (несколько устройств).
     * users.fcm_token остаётся как legacy-колонка — новая логика её не читает и не пишет.
     */
    public function up(): void
    {
        Schema::create('fcm_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token')->unique();
            $table->enum('platform', ['android', 'ios'])->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        // Переносим уже сохранённые токены, чтобы не терять push для существующих пользователей
        DB::table('users')
            ->whereNotNull('fcm_token')
            ->select('id', 'fcm_token')
            ->orderBy('id')
            ->chunk(500, function ($users) {
                $now = now();
                DB::table('fcm_tokens')->insertOrIgnore(
                    $users->map(fn ($user) => [
                        'user_id'      => $user->id,
                        'token'        => $user->fcm_token,
                        'platform'     => null,
                        'last_used_at' => $now,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('fcm_tokens');
    }
};
