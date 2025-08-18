<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromoContent extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'media_type', 'file_path', 'is_active', 'display_order', 'starts_at', 'ends_at'];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function scopePublishable($q)
    {
        $now = now();

        return $q->where('is_active', true)
            ->where(function ($qq) use ($now) {
                $qq->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($qq) use ($now) {
                $qq->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->orderBy('display_order');
    }
}
