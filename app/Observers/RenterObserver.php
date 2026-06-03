<?php

namespace App\Observers;

use App\Models\Renter;

class RenterObserver
{
    public function updated(Renter $renter): void
    {
        // Algolia-only — the database driver doesn't carry its own index for
        // parents to cascade into; reindex calls are wasted work in dev/tests.
        if (config('scout.driver') !== 'algolia') return;

        // Propagate to child inquiries' Algolia documents only when the
        // attributes that flow into Inquiry::toSearchableArray actually
        // changed. Avoids a wasted reindex on is_qualified / notes flips.
        if ($renter->wasChanged(['name', 'phone'])) {
            $renter->inquiries->each->searchable();
        }
    }
}
