<?php

namespace App\Repositories;

use App\Models\Setting;
use App\Repositories\Interfaces\SettingRepositoryInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Настройки меняются раз в месяц, а читаются на каждом запросе к новостям и
 * баннерам админки (EnsureNewsPermission/EnsureBannerPermission) и при каждом
 * поднятии объявления. Поэтому значение кэшируется и сбрасывается при записи.
 */
class SettingRepository implements SettingRepositoryInterface
{
    private const CACHE_PREFIX = 'setting:';

    public function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever(
            self::CACHE_PREFIX.$key,
            // null не сохраняется как «нет значения» — заворачиваем в массив,
            // иначе rememberForever ходил бы в БД при каждом промахе.
            fn () => ['value' => Setting::where('key', $key)->value('value')],
        );

        return $value['value'] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::CACHE_PREFIX.$key);
    }
}
