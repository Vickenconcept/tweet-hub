<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('whatsapp_phone', 20)->nullable()->unique()->after('timezone');
            $table->timestamp('whatsapp_verified_at')->nullable()->after('whatsapp_phone');
            $table->boolean('whatsapp_bot_enabled')->default(false)->after('whatsapp_verified_at');
            $table->json('whatsapp_permissions')->nullable()->after('whatsapp_bot_enabled');
            $table->boolean('whatsapp_quick_mode')->default(false)->after('whatsapp_permissions');
            $table->string('whatsapp_verification_code', 6)->nullable()->after('whatsapp_quick_mode');
            $table->timestamp('whatsapp_verification_expires_at')->nullable()->after('whatsapp_verification_code');
            $table->string('zernio_conversation_id')->nullable()->after('whatsapp_verification_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_phone',
                'whatsapp_verified_at',
                'whatsapp_bot_enabled',
                'whatsapp_permissions',
                'whatsapp_quick_mode',
                'whatsapp_verification_code',
                'whatsapp_verification_expires_at',
                'zernio_conversation_id',
            ]);
        });
    }
};
