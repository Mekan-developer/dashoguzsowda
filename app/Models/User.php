<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Только то, что пользователь вправе менять о себе сам.
     *
     * role / status / blocked_* / tariff_* / phone_verified_at сюда НЕ входят:
     * это привилегии и оплаченные лимиты. Пишутся явно через UserRepository
     * (block/unblock/assignTariff/markPhoneVerified/createWithRole), чтобы
     * случайный $user->update($request->all()) не смог выдать роль admin.
     */
    protected $fillable = [
        'name', 'phone', 'email', 'avatar', 'gender', 'birth_date',
        'region_id', 'city_id', 'district_id', 'locale', 'note', 'password',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'birth_date'        => 'date',
            'phone_verified_at' => 'datetime',
            'tariff_ends_at'    => 'datetime',
            'blocked_at'        => 'datetime',
            'onboarding_completed' => 'boolean',
            'password'          => 'hashed',
        ];
    }

    public function region()   { return $this->belongsTo(Region::class); }
    public function city()     { return $this->belongsTo(City::class); }
    public function district() { return $this->belongsTo(District::class); }
    public function tariff()   { return $this->belongsTo(Tariff::class); }
    public function store()     { return $this->hasOne(Store::class); }
    public function tariffRequests() { return $this->hasMany(TariffRequest::class); }
    public function listings()  { return $this->hasMany(Listing::class); }
    public function videos()    { return $this->hasMany(Video::class); }
    public function messages()  { return $this->hasMany(Message::class); }
    public function favorites() { return $this->hasMany(Favorite::class); }
    public function fcmTokens() { return $this->hasMany(FcmToken::class); }
    /** Отзывы, написанные этим пользователем */
    public function writtenReviews()  { return $this->hasMany(Review::class); }
    /** Отзывы о самом пользователе (reviews.target_user_id) */
    public function receivedReviews() { return $this->hasMany(Review::class, 'target_user_id'); }

    public function isAdmin()   { return $this->role === 'admin'; }
    public function isManager() { return $this->role === 'manager'; }
    public function isBlocked() { return $this->status === 'blocked'; }

    /**
     * Регистрация профиля завершена: есть имя, регион и город.
     *
     * По этому флагу мобильное приложение решает, куда вести после splash/OTP:
     * false → /register, true → /home (mobile_docs/BACKEND_API.md §6).
     * Считается на лету, без колонки-кэша, чтобы не рассинхронизироваться.
     */
    public function isProfileComplete(): bool
    {
        return trim((string) $this->name) !== ''
            && $this->region_id !== null
            && $this->city_id !== null;
    }

    /**
     * Бесплатный тариф выдаётся без `tariff_ends_at` — он бессрочен;
     * срок действия есть только у платного.
     */
    public function activeTariff()
    {
        if ($this->tariff_id && $this->tariff_ends_at && $this->tariff_ends_at->isFuture()) {
            return $this->tariff;
        }
        if ($this->tariff_id && ! $this->tariff_ends_at && $this->tariff?->is_free) {
            return $this->tariff;
        }
        return Tariff::where('is_free', true)->first();
    }
}
