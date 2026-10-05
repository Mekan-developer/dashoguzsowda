<?php

namespace App\Console\Commands;

use App\Actions\ExpireTariffsAction;
use Illuminate\Console\Command;

class ExpireTariffs extends Command
{
    protected $signature = 'tariffs:expire';

    protected $description = 'Переводит пользователей с истёкшим платным тарифом на бесплатный и скрывает контент сверх его лимитов';

    public function handle(ExpireTariffsAction $action): int
    {
        $expired = $action->execute();

        if ($expired === null) {
            $this->warn('Бесплатный тариф не задан — переводить некуда');

            return self::FAILURE;
        }

        $this->info("Переведено на бесплатный тариф: {$expired}");

        return self::SUCCESS;
    }
}
