<?php

namespace App\Models;

use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Amenity extends Model
{
    use HasUuid, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'sort_order',
    ];

    public function listings(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class);
    }
}
