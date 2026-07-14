<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'phone', 'phone_verified_at', 'email', 'avatar', 'gender', 'birth_date',
        'region_id', 'city_id', 'district_id', 'role', 'locale', 'status', 'blocked_reason',
        'tariff_id', 'tariff_ends_at', 'note', 'fcm_token', 'password',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'birth_date'        => 'date',
            'phone_verified_at' => 'datetime',
            'tariff_ends_at'    => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function region()   { return $this->belongsTo(Region::class); }
    public function city()     { return $this->belongsTo(City::class); }
    public function district() { return $this->belongsTo(District::class); }
    public function tariff()   { return $this->belongsTo(Tariff::class); }
    public function listings()  { return $this->hasMany(Listing::class); }
    public function videos()    { return $this->hasMany(Video::class); }
    public function messages()  { return $this->hasMany(Message::class); }
    public function favorites() { return $this->hasMany(Favorite::class); }
    public function fcmTokens() { return $this->hasMany(FcmToken::class); }

    public function isAdmin()   { return $this->role === 'admin'; }
    public function isManager() { return $this->role === 'manager'; }
    public function isBlocked() { return $this->status === 'blocked'; }

    public function activeTariff()
    {
        if ($this->tariff_id && $this->tariff_ends_at && $this->tariff_ends_at->isFuture()) {
            return $this->tariff;
        }
        return Tariff::where('is_free', true)->first();
    }
}
