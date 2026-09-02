<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('whatsapp_notify_post_published')->default(false)->after('zernio_conversation_id');
            $table->boolean('whatsapp_notify_post_failed')->default(false)->after('whatsapp_notify_post_published');
            $table->boolean('whatsapp_notify_new_mentions')->default(false)->after('whatsapp_notify_post_failed');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_notify_post_published',
                'whatsapp_notify_post_failed',
                'whatsapp_notify_new_mentions',
            ]);
        });
    }
};
