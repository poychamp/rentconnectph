<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->index();
            $table->foreignId('renter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('new')->index();
            $table->text('notes')->nullable();
            $table->timestamp('handed_off_at')->nullable();
            // handed_off_by uses nullOnDelete (CLAUDE.md audit-log carve-out) —
            // actor survives a hard-deleted admin so handoff history stays
            // interpretable.
            $table->foreignId('handed_off_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
