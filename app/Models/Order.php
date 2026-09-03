<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id', 'status', 'total',
        'contact_name', 'phone', 'region_id', 'city_id', 'district_id', 'address', 'comment',
        'admin_comment', 'processed_by', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'total'        => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function user()      { return $this->belongsTo(User::class); }
    public function region()    { return $this->belongsTo(Region::class); }
    public function city()      { return $this->belongsTo(City::class); }
    public function district()  { return $this->belongsTo(District::class); }
    public function processor() { return $this->belongsTo(User::class, 'processed_by'); }
    public function suborders() { return $this->hasMany(Suborder::class); }
    public function items()     { return $this->hasMany(OrderItem::class); }

    /**
     * Человекочитаемый номер для звонка покупателю: id с ведущими нулями.
     * Отдельной колонки нет намеренно — она потребовала бы генерации с
     * блокировкой, а id уникален сам по себе.
     */
    public function getNumberAttribute(): string
    {
        return str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /** Может ли покупатель ещё отменить заказ — только пока его не взяли в работу. */
    public function isCancelableByBuyer(): bool
    {
        return $this->status === 'pending';
    }
}
