<?php

namespace App\Repositories;

use App\Repositories\Interfaces\SmsCodeRepositoryInterface;
use App\Services\Sms\SmsCode;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Коды подтверждения хранятся в кэше (Redis), а не в таблице.
 *
 * Запись нужна ровно на время жизни кода: TTL удаляет её сам, поэтому нет ни
 * растущей таблицы sms_codes, ни уборочной команды, а мониторинг в админке
 * показывает только то, что ещё может пригодиться.
 *
 * Ключи:
 *   otp:code:{id}      — сама запись, TTL до expires_at;
 *   otp:phone:{phone}  — id кодов номера, свежие первыми (проверка и cooldown);
 *   otp:feed           — id последних кодов по всем номерам (лента админки).
 * Оба индекса переживают запись кода ровно настолько же, насколько сам код.
 */
class SmsCodeRepository implements SmsCodeRepositoryInterface
{
    private const PREFIX = 'otp:';

    private const FEED_KEY = self::PREFIX.'feed';

    /** Сколько id держать в ленте: с запасом к OtpMonitorService::LIMIT под фильтр по номеру. */
    private const FEED_LIMIT = 200;

    /** Кодов на номер за время жизни TTL больше и не бывает — resend_cooldown не даёт. */
    private const PHONE_LIMIT = 10;

    private const LOCK_SECONDS = 5;

    private const LOCK_WAIT_SECONDS = 3;

    public function create(string $phone, string $code, int $ttlSeconds): SmsCode
    {
        $now = CarbonImmutable::now();

        $smsCode = new SmsCode(
            id: (string) Str::ulid(),
            phone: $phone,
            code: $code,
            attempts: 0,
            createdAt: $now,
            expiresAt: $now->addSeconds($ttlSeconds),
        );

        $this->put($smsCode);
        $this->index(self::FEED_KEY, $smsCode, self::FEED_LIMIT);
        $this->index($this->phoneKey($phone), $smsCode, self::PHONE_LIMIT);

        return $smsCode;
    }

    /** @return array<int, SmsCode> */
    public function recent(int $limit, ?string $phone = null): array
    {
        $codes = [];

        // Лента хранит только id: протухшие записи кэш уже удалил, поэтому
        // промахи здесь — это штатная уборка, а не потеря данных.
        foreach ($this->ids(self::FEED_KEY) as $id) {
            $code = $this->find($id);

            if (! $code || (filled($phone) && ! str_contains($code->phone, $phone))) {
                continue;
            }

            $codes[] = $code;

            if (count($codes) >= $limit) {
                break;
            }
        }

        return $codes;
    }

    public function findActive(string $phone): ?SmsCode
    {
        foreach ($this->codesOf($phone) as $code) {
            if (! $code->isUsed()) {
                return $code;
            }
        }

        return null;
    }

    public function findLatest(string $phone): ?SmsCode
    {
        return $this->codesOf($phone)[0] ?? null;
    }

    /**
     * Кулдаун — отдельный ключ, а не отметка времени на самом коде: код
     * удаляется по своему TTL, и при OTP_TTL меньше кулдауна запрет на
     * повторную отправку исчезал бы вместе с ним.
     */
    public function startResendCooldown(string $phone, int $seconds): void
    {
        $availableAt = CarbonImmutable::now()->addSeconds($seconds);

        Cache::put($this->cooldownKey($phone), $availableAt->getTimestamp(), $availableAt);
    }

    public function resendCooldownRemaining(string $phone): int
    {
        $availableAt = Cache::get($this->cooldownKey($phone));

        return $availableAt === null
            ? 0
            : max(0, $availableAt - CarbonImmutable::now()->getTimestamp());
    }

    public function markUsed(SmsCode $smsCode): void
    {
        $this->mutate($smsCode->id, fn (SmsCode $fresh) => $fresh->markedUsedAt(CarbonImmutable::now()));
    }

    public function incrementAttempts(SmsCode $smsCode): void
    {
        // Под блокировкой и с перечитыванием: счётчик попыток — защита от
        // перебора, и параллельная проверка кода не должна её обнулять.
        $this->mutate($smsCode->id, fn (SmsCode $fresh) => $fresh->withAttempts($fresh->attempts + 1));
    }

    public function invalidateActive(string $phone): void
    {
        foreach ($this->codesOf($phone) as $code) {
            if (! $code->isUsed()) {
                $this->markUsed($code);
            }
        }
    }

    /**
     * Коды номера, свежие первыми.
     *
     * @return array<int, SmsCode>
     */
    private function codesOf(string $phone): array
    {
        $codes = [];

        foreach ($this->ids($this->phoneKey($phone)) as $id) {
            if ($code = $this->find($id)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    private function find(string $id): ?SmsCode
    {
        $row = Cache::get($this->codeKey($id));

        return $row === null ? null : SmsCode::fromArray($row);
    }

    /** Запись живёт до expires_at — дальше кэш удаляет её сам. */
    private function put(SmsCode $smsCode): void
    {
        Cache::put($this->codeKey($smsCode->id), $smsCode->toArray(), $smsCode->expiresAt);
    }

    /** Перечитать запись под блокировкой, применить изменение и сохранить с прежним TTL. */
    private function mutate(string $id, callable $change): void
    {
        $apply = function () use ($id, $change) {
            if ($fresh = $this->find($id)) {
                $this->put($change($fresh));
            }
        };

        $this->locked($this->codeKey($id), $apply);
    }

    /**
     * Добавить код в начало индекса, обрезав тот до $limit записей.
     *
     * Индекс хранит не голые id, а срок жизни каждого: сам он живёт ровно до
     * самой долгой своей записи. Иначе код с коротким TTL укоротил бы индекс
     * целиком и унёс из мониторинга чужие, ещё живые коды.
     *
     * Пишем под блокировкой: индекс общий, и параллельный запрос кода на
     * другой номер затёр бы чужую запись.
     */
    private function index(string $key, SmsCode $smsCode, int $limit): void
    {
        $this->locked($key, function () use ($key, $smsCode, $limit) {
            $now = CarbonImmutable::now()->getTimestamp();

            $entries = [$smsCode->id => $smsCode->expiresAt->getTimestamp()] + $this->entries($key);
            // is_int — на случай записи, оставшейся в кэше от прежнего формата:
            // индекс должен самовосстанавливаться, а не падать на чужих данных.
            $entries = array_filter($entries, fn ($expiresAt) => is_int($expiresAt) && $expiresAt > $now);
            $entries = array_slice($entries, 0, $limit, preserve_keys: true);

            if ($entries === []) {
                Cache::forget($key);

                return;
            }

            Cache::put($key, $entries, max($entries) - $now);
        });
    }

    /**
     * Записи индекса, свежие первыми: id => время истечения.
     *
     * @return array<string, int>
     */
    private function entries(string $key): array
    {
        return Cache::get($key, []);
    }

    /** @return array<int, string> */
    private function ids(string $key): array
    {
        return array_keys($this->entries($key));
    }

    private function locked(string $key, callable $callback): void
    {
        try {
            Cache::lock($key.':lock', self::LOCK_SECONDS)->block(self::LOCK_WAIT_SECONDS, $callback);
        } catch (LockTimeoutException) {
            // Ждать дольше нечего: отправка кода важнее строки в мониторинге,
            // поэтому пишем без блокировки и миримся с гонкой.
            $callback();
        }
    }

    private function codeKey(string $id): string
    {
        return self::PREFIX.'code:'.$id;
    }

    private function phoneKey(string $phone): string
    {
        return self::PREFIX.'phone:'.$phone;
    }

    private function cooldownKey(string $phone): string
    {
        return self::PREFIX.'cooldown:'.$phone;
    }
}
