<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->index();
            $table->string('name', 120)->nullable();
            $table->string('phone', 32)->index();
            $table->text('notes')->nullable();
            $table->boolean('is_show_name')->default(true);
            $table->boolean('is_show_notes')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_contacts');
    }
};
