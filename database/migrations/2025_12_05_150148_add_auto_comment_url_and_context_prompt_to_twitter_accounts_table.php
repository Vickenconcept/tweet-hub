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
        Schema::table('twitter_accounts', function (Blueprint $table) {
            $table->string('auto_comment_url')->nullable()->after('auto_comment_enabled')->comment('URL to provide context for AI-generated comments');
            $table->text('auto_comment_context_prompt')->nullable()->after('auto_comment_url')->comment('Context prompt to guide AI comment generation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('twitter_accounts', function (Blueprint $table) {
            $table->dropColumn(['auto_comment_url', 'auto_comment_context_prompt']);
        });
    }
};
