<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Коды подтверждения переехали в кэш (Redis): запись нужна только на время
 * жизни кода и удаляется TTL-ом, а таблица росла без уборки — см.
 * App\Repositories\SmsCodeRepository.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('sms_codes');
    }

    public function down(): void
    {
        Schema::create('sms_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->index();
            $table->string('code');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
};
