<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_command_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('zernio_event_id')->unique();
            $table->string('from_phone', 20)->nullable();
            $table->string('conversation_id')->nullable();
            $table->text('command')->nullable();
            $table->string('parsed_action')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('response_preview')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_command_logs');
    }
};
