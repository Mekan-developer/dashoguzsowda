<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    protected $fillable = [
        'user_id', 'category_id', 'title', 'path', 'processed_path', 'preview_path',
        'duration_seconds', 'is_processed', 'tags', 'status',
        'rejection_reason_id', 'likes_count', 'views',
    ];

    protected $casts = [
        'tags'         => 'array',
        'is_processed' => 'boolean',
    ];

    public function user()            { return $this->belongsTo(User::class); }
    public function category()        { return $this->belongsTo(Category::class); }
    public function rejectionReason() { return $this->belongsTo(RejectionReason::class); }
    public function likes()           { return $this->hasMany(VideoLike::class); }
}
