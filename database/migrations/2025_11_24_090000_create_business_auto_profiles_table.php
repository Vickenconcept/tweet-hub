<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('business_auto_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('keywords')->nullable();
            $table->string('tone')->default('Friendly');
            $table->string('posting_time')->default('09:00');
            $table->string('timezone')->default(config('app.timezone', 'UTC'));
            $table->boolean('include_images')->default(false);
            $table->string('image_style')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_auto_profiles');
    }
};

