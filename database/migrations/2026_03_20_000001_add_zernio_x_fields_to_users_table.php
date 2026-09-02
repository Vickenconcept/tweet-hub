<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('zernio_profile_id')->nullable()->after('twitter_refresh_token');
            $table->string('zernio_twitter_account_id')->nullable()->after('zernio_profile_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['zernio_profile_id', 'zernio_twitter_account_id']);
        });
    }
};
