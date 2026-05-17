<?php

namespace App\Observers;

use App\Models\Listing;

class ListingObserver
{
    public function updated(Listing $listing): void
    {
        // Algolia-only — the database driver doesn't carry its own index for
        // parents to cascade into; reindex calls are wasted work in dev/tests.
        if (config('scout.driver') !== 'algolia') return;

        // Propagate to child inquiries' Algolia documents only when the
        // attributes that flow into Inquiry::toSearchableArray actually
        // changed. Avoids a wasted reindex on is_featured / queue_status
        // / verification_notes / etc. flips. listing_contact_id changes
        // propagate the new contact's phone into child inquiry documents.
        if ($listing->wasChanged(['title', 'listing_contact_id'])) {
            $listing->inquiries->each->searchable();
        }
    }
}
