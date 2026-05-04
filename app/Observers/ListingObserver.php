<?php

namespace App\Observers;

use App\Models\Listing;

class ListingObserver
{
    public function updated(Listing $listing): void
    {
        // Propagate to child inquiries' Algolia documents only when the
        // attributes that flow into Inquiry::toSearchableArray actually
        // changed. Avoids a wasted reindex on is_featured / queue_status
        // / verification_notes / etc. flips.
        if ($listing->wasChanged(['title', 'contact_phone'])) {
            $listing->inquiries->each->searchable();
        }
    }
}
