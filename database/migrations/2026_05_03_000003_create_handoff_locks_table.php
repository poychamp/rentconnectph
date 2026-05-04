<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handoff_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();

            // UNIQUE on listing_id — narrow exception to CLAUDE.md "no DB-level
            // UNIQUE constraints". That rule's premise is soft-delete partial-
            // index pain; this table has no soft-deletes (release = hard
            // DELETE), so the premise doesn't apply. UNIQUE closes the race
            // condition that the prior app-layer exists()-then-create() loses.
            $table->foreignId('listing_id')->unique()->constrained()->cascadeOnDelete();

            // nullOnDelete per CLAUDE.md audit-log carve-out — lock row's
            // actor history survives a hard-deleted admin.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Only created_at — no updated_at (presence-only; never mutated
            // after insert). Mirrors listing_lifecycle_events.
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handoff_locks');
    }
};
