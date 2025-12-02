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
        Schema::create('auto_dms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('twitter_recipient_id');
            $table->string('source_type')->default('campaign'); // e.g. 'new_follower', 'mention', 'keyword', 'campaign'
            $table->string('campaign_name')->nullable();
            $table->text('original_context')->nullable(); // e.g. tweet text or reason we DM'd
            $table->text('dm_text');
            $table->timestamp('sent_at')->nullable();
            $table->string('status')->default('pending'); // pending, sent, failed, skipped
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'twitter_recipient_id', 'source_type'], 'auto_dms_user_recipient_source_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_dms');
    }
};


