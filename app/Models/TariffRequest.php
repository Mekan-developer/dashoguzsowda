<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TariffRequest extends Model
{
    protected $fillable = [
        'user_id', 'tariff_id', 'amount', 'status', 'comment',
        'processed_by', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function user()      { return $this->belongsTo(User::class); }
    public function tariff()    { return $this->belongsTo(Tariff::class); }
    public function processor() { return $this->belongsTo(User::class, 'processed_by'); }
}
