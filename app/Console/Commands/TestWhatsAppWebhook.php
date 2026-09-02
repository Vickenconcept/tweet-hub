<?php

namespace App\Console\Commands;

use App\Http\Controllers\ZernioWebhookController;
use App\Services\ZernioService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class TestWhatsAppWebhook extends Command
{
    protected $signature = 'whatsapp:test-webhook
                            {message=help : Message text to simulate}
                            {--phone= : Sender phone (defaults to first linked user)}';

    protected $description = 'Simulate a Zernio message.received webhook locally (no HTTP tunnel needed)';

    public function handle(ZernioService $zernio): int
    {
        $phone = $this->option('phone')
            ?: \App\Models\User::whereNotNull('whatsapp_phone')->value('whatsapp_phone')
            ?: '+10000000000';

        $payload = [
            'id' => 'local-test-'.uniqid(),
            'event' => 'message.received',
            'message' => [
                'text' => $this->argument('message'),
                'conversationId' => 'local_conv_'.substr(md5($phone), 0, 12),
                'sender' => [
                    'phoneNumber' => $phone,
                    'name' => 'Local Test',
                ],
            ],
            'conversation' => [
                'id' => 'local_conv_'.substr(md5($phone), 0, 12),
                'participantUsername' => $phone,
            ],
        ];

        $rawBody = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $secret = config('services.zernio.webhook_secret');
        $signature = $secret
            ? hash_hmac('sha256', $rawBody, $secret)
            : null;

        $request = Request::create(
            '/api/webhooks/zernio/inbox',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_ZERNIO_SIGNATURE' => $signature,
            ],
            $rawBody,
        );

        $response = app(ZernioWebhookController::class)->inbox($request, $zernio);

        $this->info('Webhook test response: '.$response->getStatusCode().' '.$response->getContent());
        $this->line('Check storage/logs/laravel.log and run: php artisan queue:work');

        return self::SUCCESS;
    }
}
