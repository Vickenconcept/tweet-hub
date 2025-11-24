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
        Schema::create('business_auto_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_auto_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->date('post_date');
            $table->dateTime('scheduled_for')->nullable();
            $table->text('content')->nullable();
            $table->string('image_url')->nullable();
            $table->string('asset_code')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['business_auto_profile_id', 'post_date'], 'auto_profile_post_date_unique');
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_auto_posts');
    }
};

