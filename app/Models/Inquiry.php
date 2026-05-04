<?php

namespace App\Models;

use App\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\Searchable;

class Inquiry extends Model
{
    use HasFactory, HasUuid, SoftDeletes, Searchable;

    protected $fillable = [
        'uuid',
        'renter_id',
        'listing_id',
        'status',
        'notes',
        'rejected_at',
        'rejected_by',
        'handed_off_at',
        'handed_off_by',
    ];

    protected $casts = [
        'rejected_at'   => 'datetime',
        'handed_off_at' => 'datetime',
    ];

    public function newScoutQuery(ScoutBuilder $builder)
    {
        // Scout's DatabaseEngine (used by tests + local per SCOUT_DRIVER=database)
        // calls this when present and uses it as the base query. Joining renters
        // + listings here lets the dotted keys in toSearchableArray (renters.name,
        // listings.title, etc.) resolve to qualified columns in the WHERE LIKE
        // clauses Scout builds. Algolia engine ignores newScoutQuery — it just
        // POSTs the toSearchableArray payload to the API; dotted keys become
        // nested object paths in the indexed document.
        return $this->newQuery()
            ->join('renters', 'inquiries.renter_id', '=', 'renters.id')
            ->join('listings', 'inquiries.listing_id', '=', 'listings.id')
            ->select('inquiries.*');
    }

    public function toSearchableArray(): array
    {
        if (config('scout.driver') === 'algolia') {
            // Flat top-level fields on the Algolia document — clean naming,
            // no nesting ambiguity. Configure searchableAttributes in
            // config/scout.php to mirror these keys. updated_at is a Unix
            // timestamp int so customRanking can sort by it numerically.
            return [
                'uuid'                  => $this->uuid,
                'renter_name'           => $this->renter?->name,
                'renter_phone'          => $this->renter?->phone,
                'listing_title'         => $this->listing?->title,
                'listing_contact_phone' => $this->listing?->contact_phone,
                'updated_at'            => $this->updated_at?->getTimestamp(),
            ];
        }

        // Database driver (tests + local): dotted keys ride newScoutQuery's
        // JOINs. Eloquent's qualifyColumn returns dotted strings as-is, so
        // Scout's database engine builds WHERE renters.name LIKE ? against
        // the joined renters/listings tables.
        return [
            'uuid'                   => $this->uuid,
            'renters.name'           => $this->renter?->name,
            'renters.phone'          => $this->renter?->phone,
            'listings.title'         => $this->listing?->title,
            'listings.contact_phone' => $this->listing?->contact_phone,
        ];
    }

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

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
