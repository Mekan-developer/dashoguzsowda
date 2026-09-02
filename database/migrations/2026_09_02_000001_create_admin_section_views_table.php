<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_section_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('section');
            // ID последней записи, видимой на момент открытия раздела — не
            // timestamp: секундная точность колонок created_at даёт ничью,
            // если запись появилась в ту же секунду, что и открытие раздела.
            $table->unsignedBigInteger('last_seen_id')->default(0);

            $table->unique(['user_id', 'section']);
        });

        // Иначе все нынешние admin/manager после деплоя разом увидят бейдж
        // «N новых» на всю историю регистраций — отмечаем им как «уже видел»
        // всех пользователей, что существуют на момент накатки миграции.
        $maxUserId = (int) DB::table('users')->max('id');

        $rows = DB::table('users')
            ->whereIn('role', ['admin', 'manager'])
            ->pluck('id')
            ->map(fn ($id) => ['user_id' => $id, 'section' => 'users', 'last_seen_id' => $maxUserId])
            ->all();

        if ($rows) {
            DB::table('admin_section_views')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_section_views');
    }
};
