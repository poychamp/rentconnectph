<?php

namespace App\Models;

use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Listing extends Model
{
    use HasUuid, HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'type',
        'price_monthly',
        'beds',
        'baths',
        'sqft',
        'barangay',
        'display_image_id',
        'is_verified',
        'verified_at',
        'is_featured',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_featured' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderBy('sort_order');
    }

    public function displayImage(): BelongsTo
    {
        return $this->belongsTo(ListingImage::class, 'display_image_id');
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeRecentlyVerified($query)
    {
        return $query->verified()->orderByDesc('verified_at');
    }
}
