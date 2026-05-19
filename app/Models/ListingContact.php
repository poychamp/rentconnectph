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
        'is_show_name',
        'is_show_notes',
    ];

    protected $casts = [
        'is_show_name'  => 'boolean',
        'is_show_notes' => 'boolean',
    ];

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }
}
