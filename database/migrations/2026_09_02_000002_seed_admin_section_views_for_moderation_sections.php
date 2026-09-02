<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Как и в 2026_09_02_000001: без этого нынешние admin/manager сразу
     * после деплоя увидят точку «есть новое» на разделах модерации со
     * всем, что уже накопилось в очереди — отмечаем им как «уже видел».
     */
    public function up(): void
    {
        $adminIds = DB::table('users')->whereIn('role', ['admin', 'manager'])->pluck('id');

        if ($adminIds->isEmpty()) {
            return;
        }

        $sections = ['listings', 'videos', 'reviews', 'complaints', 'stores', 'tariff_requests'];

        foreach ($sections as $section) {
            $maxId = (int) DB::table($section)->max('id');

            $rows = $adminIds->map(fn ($userId) => [
                'user_id' => $userId, 'section' => $section, 'last_seen_id' => $maxId,
            ])->all();

            // insertOrIgnore: не перетирать watermark, если админ уже успел
            // открыть раздел до накатки этой миграции.
            DB::table('admin_section_views')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        DB::table('admin_section_views')
            ->whereIn('section', ['listings', 'videos', 'reviews', 'complaints', 'stores', 'tariff_requests'])
            ->delete();
    }
};
