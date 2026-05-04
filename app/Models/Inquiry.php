<?php

namespace App\Models;

use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inquiry extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'renter_id',
        'listing_id',
        'status',
        'notes',
        'handed_off_at',
        'handed_off_by',
    ];

    protected $casts = [
        'handed_off_at' => 'datetime',
    ];

    public function renter(): BelongsTo
    {
        return $this->belongsTo(Renter::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function handedOffBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handed_off_by');
    }
}
