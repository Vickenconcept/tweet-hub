<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('auto_dms', function (Blueprint $table) {
            $table->string('tweet_id')->nullable()->after('campaign_name'); // The tweet that triggered the interaction
            $table->string('interaction_type')->nullable()->after('tweet_id'); // 'like', 'reply', 'quote', 'retweet'
            $table->string('twitter_event_id')->nullable()->after('interaction_type'); // Twitter API event ID
            $table->string('twitter_message_id')->nullable()->after('twitter_event_id'); // Twitter API message ID
            $table->string('recipient_username')->nullable()->after('twitter_recipient_id'); // Recipient username for display
            $table->string('recipient_name')->nullable()->after('recipient_username'); // Recipient name for display
            
            $table->index(['tweet_id', 'interaction_type']);
            $table->index('twitter_event_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auto_dms', function (Blueprint $table) {
            $table->dropIndex(['tweet_id', 'interaction_type']);
            $table->dropIndex(['twitter_event_id']);
            $table->dropColumn([
                'tweet_id',
                'interaction_type',
                'twitter_event_id',
                'twitter_message_id',
                'recipient_username',
                'recipient_name',
            ]);
        });
    }
};
