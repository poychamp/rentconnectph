<?php

namespace App\Models;

use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ListingContact extends Model
{
    use HasUuid, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'notes',
    ];

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }
}
