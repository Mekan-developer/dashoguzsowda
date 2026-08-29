<?php

namespace App\Services\Sms;

use Carbon\CarbonImmutable;

/**
 * Запись о выданном OTP-коде.
 *
 * Не Eloquent-модель: коды живут в кэше (Redis) и удаляются TTL-ом сами,
 * таблицы под них нет — см. SmsCodeRepository.
 */
class SmsCode
{
    public function __construct(
        public readonly string $id,
        public readonly string $phone,
        public readonly string $code,
        public readonly int $attempts,
        public readonly CarbonImmutable $createdAt,
        public readonly CarbonImmutable $expiresAt,
        public readonly ?CarbonImmutable $usedAt = null,
    ) {}

    /** @param  array<string, mixed>  $row */
    public static function fromArray(array $row): self
    {
        return new self(
            id: $row['id'],
            phone: $row['phone'],
            code: $row['code'],
            attempts: (int) $row['attempts'],
            createdAt: CarbonImmutable::parse($row['created_at']),
            expiresAt: CarbonImmutable::parse($row['expires_at']),
            usedAt: $row['used_at'] === null ? null : CarbonImmutable::parse($row['used_at']),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'phone'      => $this->phone,
            'code'       => $this->code,
            'attempts'   => $this->attempts,
            'created_at' => $this->createdAt->toIso8601String(),
            'expires_at' => $this->expiresAt->toIso8601String(),
            'used_at'    => $this->usedAt?->toIso8601String(),
        ];
    }

    public function isUsed(): bool
    {
        return $this->usedAt !== null;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt->isPast();
    }

    /** Сколько секунд коду осталось жить; 0 — если уже использован или протух. */
    public function secondsLeft(): int
    {
        if ($this->isUsed() || $this->isExpired()) {
            return 0;
        }

        return (int) CarbonImmutable::now()->diffInSeconds($this->expiresAt, absolute: true);
    }

    public function withAttempts(int $attempts): self
    {
        return new self(
            id: $this->id,
            phone: $this->phone,
            code: $this->code,
            attempts: $attempts,
            createdAt: $this->createdAt,
            expiresAt: $this->expiresAt,
            usedAt: $this->usedAt,
        );
    }

    public function markedUsedAt(CarbonImmutable $usedAt): self
    {
        return new self(
            id: $this->id,
            phone: $this->phone,
            code: $this->code,
            attempts: $this->attempts,
            createdAt: $this->createdAt,
            expiresAt: $this->expiresAt,
            usedAt: $usedAt,
        );
    }
}
