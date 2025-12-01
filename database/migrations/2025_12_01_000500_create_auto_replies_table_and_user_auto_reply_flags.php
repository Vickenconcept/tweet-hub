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
        // Track which tweets/mentions have been auto-replied to
        Schema::create('auto_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('tweet_id'); // X / Twitter tweet ID
            $table->string('source_type')->default('mention'); // mention | keyword
            $table->text('original_text')->nullable();
            $table->text('reply_text')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tweet_id', 'source_type'], 'auto_replies_unique_user_tweet_source');
        });

        // Per-user flags to turn auto-reply on/off for mentions & keyword search
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('auto_reply_mentions_enabled')->default(false)->after('monitored_keywords');
            $table->boolean('auto_reply_keywords_enabled')->default(false)->after('auto_reply_mentions_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['auto_reply_mentions_enabled', 'auto_reply_keywords_enabled']);
        });

        Schema::dropIfExists('auto_replies');
    }
};


