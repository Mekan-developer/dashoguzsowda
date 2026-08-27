<?php

namespace App\Repositories;

use App\Models\Tariff;
use App\Repositories\Interfaces\TariffRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TariffRepository implements TariffRepositoryInterface
{
    public function all(): Collection
    {
        return Tariff::withCount('users')->orderBy('id')->get();
    }

    public function active(): Collection
    {
        return Tariff::where('is_active', true)->orderBy('id')->get();
    }

    public function find(int $id): Tariff
    {
        return Tariff::findOrFail($id);
    }

    public function create(array $data): Tariff
    {
        return Tariff::create($data);
    }

    public function update(Tariff $tariff, array $data): Tariff
    {
        $tariff->update($data);
        return $tariff->fresh();
    }

    public function delete(Tariff $tariff): void
    {
        $tariff->delete();
    }

    public function getFree(): ?Tariff
    {
        return Tariff::where('is_free', true)->first();
    }

    public function clearFree(): void
    {
        Tariff::where('is_free', true)->update(['is_free' => false]);
    }

    public function findByName(string $name): ?Tariff
    {
        return Tariff::where('name', $name)->where('is_active', true)->first();
    }

    public function catalogActive(): Collection
    {
        return Tariff::where('is_active', true)->whereNotNull('name')->orderBy('id')->get();
    }
}
