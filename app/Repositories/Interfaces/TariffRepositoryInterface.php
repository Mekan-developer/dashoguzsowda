<?php

namespace App\Repositories\Interfaces;

use App\Models\Tariff;
use Illuminate\Database\Eloquent\Collection;

interface TariffRepositoryInterface
{
    public function all(): Collection;
    /** Только активные тарифы — для селектов в формах админки. */
    public function active(): Collection;
    public function find(int $id): Tariff;
    public function create(array $data): Tariff;
    public function update(Tariff $tariff, array $data): Tariff;
    public function delete(Tariff $tariff): void;
    public function getFree(): ?Tariff;
    public function clearFree(): void;

    /** Тариф по мобильному slug (name) — PUT /v1/profile/subscription. */
    public function findByName(string $name): ?Tariff;

    /** Активные тарифы со slug — каталог для GET /v1/tariffs (без slug не попадают). */
    public function catalogActive(): Collection;
}
