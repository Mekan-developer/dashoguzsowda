<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = ['user_id', 'sender', 'admin_id', 'text', 'is_read'];

    // Без каста is_read уезжает наружу как 0/1: в пропах Inertia и — что хуже —
    // в payload NewMessageEvent, который при асинхронном броадкасте берёт
    // модель уже перечитанной из БД. Мобилке обещан bool, как в MessageResource.
    protected $casts = ['is_read' => 'boolean'];

    public function user()  { return $this->belongsTo(User::class); }
    public function admin() { return $this->belongsTo(User::class, 'admin_id'); }
}
