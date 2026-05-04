<?php

namespace App\Models;

use App\Concerns\HasUuid;
use App\Enums\InquiryStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Renter extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'phone',
        'is_qualified',
        'notes',
    ];

    protected $casts = [
        'is_qualified' => 'boolean',
    ];

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function deadInquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class)
            ->where('status', InquiryStatus::dead()->value);
    }

    protected function qualified(): Attribute
    {
        return Attribute::get(fn () => $this->inquiries()
            ->where('status', InquiryStatus::handedOff()->value)
            ->exists());
    }
}
