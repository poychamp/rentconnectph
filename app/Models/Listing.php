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
        $array = [
            'uuid'          => $this->uuid,
            'title'         => $this->title,
            'description'   => $this->description,
            'type'          => $this->type,
            'barangay'      => $this->barangay,
            'price_monthly' => $this->price_monthly,
            'is_verified'   => $this->is_verified ? '1' : '0',
            'verified_at'   => $this->verified_at?->getTimestamp(),
        ];

        // Algolia-only: include relation-derived + computed labeled fields.
        // Scout's database driver builds WHERE-LIKE on these keys as real
        // columns; computed fields without a column would SQL-error. Algolia
        // stores the JSON as-is and tokenizes naturally — `specs` enables
        // natural-language queries like "1 bed", "2 baths", "30 sqm".
        if (config('scout.driver') === 'algolia') {
            $array['amenities'] = $this->amenities->pluck('name')->implode(' ');
            $array['specs']     = "{$this->beds} bed {$this->beds} beds {$this->baths} bath {$this->baths} baths {$this->sqm} sqm";
        }

        return $array;
    }

    protected $fillable = [
        'title',
        'type',
        'price_monthly',
        'beds',
        'baths',
        'sqm',
        'barangay',
        'latitude',
        'longitude',
        'description',
        'display_image_id',
        'is_verified',
        'verified_at',
        'is_featured',
        'featured_order',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_featured' => 'boolean',
        'featured_order' => 'integer',
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
