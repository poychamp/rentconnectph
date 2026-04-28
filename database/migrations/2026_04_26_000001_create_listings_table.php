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
            $table->string('type', 32);
            $table->unsignedInteger('price_monthly');
            $table->unsignedTinyInteger('beds');
            $table->unsignedTinyInteger('baths');
            $table->unsignedSmallInteger('sqm');
            $table->string('barangay', 80);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('description')->nullable();
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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
