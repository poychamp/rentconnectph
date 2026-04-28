<?php

namespace App\Models;

use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Listing extends Model
{
    use HasUuid, HasFactory, SoftDeletes, Searchable;

    public function toSearchableArray(): array
    {
        return [
            'uuid'          => $this->uuid,
            'title'         => $this->title,
            'type'          => $this->type,
            'barangay'      => $this->barangay,
            'price_monthly' => $this->price_monthly,
            'is_verified'   => $this->is_verified ? '1' : '0',
            'verified_at'   => $this->verified_at?->getTimestamp(),
        ];
    }

    protected $fillable = [
        'title',
        'type',
        'price_monthly',
        'beds',
        'baths',
        'sqft',
        'barangay',
        'latitude',
        'longitude',
        'description',
        'display_image_id',
        'is_verified',
        'verified_at',
        'is_featured',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_featured' => 'boolean',
        'verified_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderBy('sort_order');
    }

    public function displayImage(): BelongsTo
    {
        return $this->belongsTo(ListingImage::class, 'display_image_id');
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class)->orderBy('amenities.sort_order');
    }

    public function lifecycleEvents(): HasMany
    {
        return $this->hasMany(ListingLifecycleEvent::class)->orderByDesc('created_at');
    }

    public function latestLifecycleEvent(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ListingLifecycleEvent::class)->latestOfMany('created_at');
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeDeactivated($query)
    {
        return $query->onlyTrashed()->where('is_verified', true);
    }

    public function scopeRejected($query)
    {
        return $query->onlyTrashed()->where('is_verified', false);
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
