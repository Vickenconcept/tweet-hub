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
        Schema::create('twitter_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('api_key')->nullable()->comment('Encrypted Twitter API Key');
            $table->text('api_secret')->nullable()->comment('Encrypted Twitter API Secret');
            $table->text('access_token')->nullable()->comment('Encrypted Twitter Access Token');
            $table->text('access_token_secret')->nullable()->comment('Encrypted Twitter Access Token Secret');
            $table->boolean('auto_comment_enabled')->default(false);
            $table->integer('daily_comment_limit')->default(20);
            $table->integer('comments_posted_today')->default(0);
            $table->timestamp('last_comment_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('twitter_accounts');
    }
};

