<?php

namespace App\Observers;

use App\Models\Renter;

class RenterObserver
{
    public function updated(Renter $renter): void
    {
        // Propagate to child inquiries' Algolia documents only when the
        // attributes that flow into Inquiry::toSearchableArray actually
        // changed. Avoids a wasted reindex on is_qualified / notes flips.
        if ($renter->wasChanged(['name', 'phone'])) {
            $renter->inquiries->each->searchable();
        }
    }
}
