<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->index();
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            // created_by uses nullOnDelete (CLAUDE.md audit-log carve-out) —
            // actor survives a hard-deleted admin so lead history stays
            // interpretable.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('pending')->index();
            $table->text('notes')->nullable();
            // State-companion timestamps for future state-transition PRDs
            // (PUT /send /finalize /lose). Land now to avoid stacked
            // extend_leads_for_<state> migrations later.
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
