<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UserService::block()/unblock() с самого начала писали 'blocked_at', но такой
 * колонки в схеме не было: значение молча отбрасывалось mass-assignment-ом
 * (его не было и в $fillable). Дата блокировки нигде не сохранялась, хотя код
 * выглядел так, будто сохраняется.
 *
 * Колонка добавляется, потому что намерение очевидно из кода, а дата нужна при
 * разборе жалоб. Существующим заблокированным проставляется updated_at —
 * точнее данных нет.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('blocked_at')->nullable()->after('blocked_reason');
        });

        \Illuminate\Support\Facades\DB::table('users')
            ->where('status', 'blocked')
            ->update(['blocked_at' => \Illuminate\Support\Facades\DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('blocked_at');
        });
    }
};
