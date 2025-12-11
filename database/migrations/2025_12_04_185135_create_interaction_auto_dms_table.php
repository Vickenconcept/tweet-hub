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
        Schema::create('interaction_auto_dms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('tweet_id'); // The tweet being monitored
            $table->boolean('enabled')->default(true);
            $table->boolean('monitor_likes')->default(true);
            $table->boolean('monitor_retweets')->default(true);
            $table->boolean('monitor_replies')->default(true);
            $table->boolean('monitor_quotes')->default(true);
            $table->text('dm_template')->nullable(); // Override template for this tweet
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'tweet_id']);
            $table->index(['enabled', 'last_checked_at']);
        });

        // Add columns to users table for global interaction auto DM settings
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('interaction_auto_dm_enabled')->default(false)->after('auto_reply_keywords_enabled');
            $table->text('interaction_auto_dm_template')->nullable()->after('interaction_auto_dm_enabled');
            $table->integer('interaction_auto_dm_daily_limit')->default(50)->after('interaction_auto_dm_template');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interaction_auto_dms');
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['interaction_auto_dm_enabled', 'interaction_auto_dm_template', 'interaction_auto_dm_daily_limit']);
        });
    }
};
