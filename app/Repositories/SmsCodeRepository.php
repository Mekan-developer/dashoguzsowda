<?php

namespace App\Repositories;

use App\Models\SmsCode;
use App\Models\User;
use App\Repositories\Interfaces\SmsCodeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class SmsCodeRepository implements SmsCodeRepositoryInterface
{
    public function create(string $phone, string $code, int $ttlSeconds): SmsCode
    {
        return SmsCode::create([
            'phone'      => $phone,
            'code'       => $code,
            'expires_at' => now()->addSeconds($ttlSeconds),
        ]);
    }

    public function recent(int $limit, ?string $phone = null): Collection
    {
        // Имя владельца номера — подзапросом: связи sms_codes → users нет,
        // номер может принадлежать ещё не зарегистрированному пользователю.
        return SmsCode::query()
            ->select('sms_codes.*')
            ->addSelect(['user_name' => User::select('name')
                ->whereColumn('users.phone', 'sms_codes.phone')
                ->limit(1)])
            ->when(filled($phone), fn ($query) => $query->where('phone', 'like', '%' . $phone . '%'))
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function findActive(string $phone): ?SmsCode
    {
        return SmsCode::where('phone', $phone)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    public function findLatest(string $phone): ?SmsCode
    {
        return SmsCode::where('phone', $phone)->latest('id')->first();
    }

    public function markUsed(SmsCode $smsCode): void
    {
        $smsCode->update(['used_at' => now()]);
    }

    public function incrementAttempts(SmsCode $smsCode): void
    {
        $smsCode->increment('attempts');
    }

    public function invalidateActive(string $phone): void
    {
        SmsCode::where('phone', $phone)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);
    }
}
