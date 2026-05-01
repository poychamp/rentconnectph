<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->string('title', 200);
            $table->string('type', 32)->nullable();
            $table->unsignedInteger('price_monthly')->nullable();
            $table->unsignedTinyInteger('beds')->nullable();
            $table->unsignedTinyInteger('baths')->nullable();
            $table->unsignedSmallInteger('sqm')->nullable();
            $table->string('barangay', 80)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('description')->nullable();
            $table->string('source_site', 32)->nullable();
            $table->string('source_url', 2000)->nullable();
            $table->string('contact_phone', 16);
            $table->string('prequal_status', 32)->nullable();
            $table->string('queue_status', 32)->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('visited_at')->nullable();
            $table->unsignedInteger('field_priority_order')->nullable();
            $table->boolean('is_field_priority')->default(false);
            $table->string('directions', 500)->nullable();
            $table->string('contact_type', 32)->nullable();
            $table->text('verification_notes')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('featured_order')->nullable();
            $table->unsignedBigInteger('display_image_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index('type');
            $table->index('barangay');
            $table->index(['is_featured', 'is_verified']);
            $table->index(['is_verified', 'verified_at']);
            $table->index('featured_order');
            $table->index('contact_phone');
            $table->index('prequal_status');
            $table->index('queue_status');
            $table->index('assigned_to');
            $table->index('field_priority_order');
            $table->index('is_field_priority');
            $table->index('contact_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
