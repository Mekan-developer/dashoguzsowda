<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    public const MAX_LEVEL = 3;

    protected $fillable = ['parent_id', 'name_ru', 'name_tk', 'slug', 'icon_path', 'image_path', 'level', 'order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected $appends = ['icon_url', 'image_url'];

    public function parent()   { return $this->belongsTo(Category::class, 'parent_id'); }
    public function children() { return $this->hasMany(Category::class, 'parent_id')->orderBy('order'); }
    public function listings() { return $this->hasMany(Listing::class); }
    public function videos()   { return $this->hasMany(Video::class); }
    public function stores()   { return $this->belongsToMany(Store::class); }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getIconUrlAttribute(): ?string
    {
        return $this->icon_path ? Storage::disk('public')->url($this->icon_path) : null;
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
