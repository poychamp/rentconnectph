<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_images', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('url', 500);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index(['listing_id', 'sort_order']);
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->foreign('display_image_id')
                ->references('id')
                ->on('listing_images')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropForeign(['display_image_id']);
        });

        Schema::dropIfExists('listing_images');
    }
};
