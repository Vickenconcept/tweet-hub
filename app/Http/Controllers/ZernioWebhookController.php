<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppCommand;
use App\Models\User;
use App\Services\ZernioService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ZernioWebhookController extends Controller
{
    public function inbox(Request $request, ZernioService $zernio): Response
    {
        $rawBody = $request->getContent();
        $signature = $request->header('X-Zernio-Signature') ?? $request->header('X-Late-Signature');

        if (! $zernio->verifyWebhookSignature($rawBody, $signature)) {
            Log::warning('Zernio webhook signature verification failed', [
                'has_signature' => ! empty($signature),
                'ip' => $request->ip(),
            ]);

            return response('Invalid signature', 401);
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            Log::warning('Zernio webhook invalid JSON payload', [
                'body_preview' => mb_substr($rawBody, 0, 500),
            ]);

            return response('Invalid payload', 400);
        }

        $event = $payload['event'] ?? $payload['type'] ?? null;

        Log::info('Zernio webhook received', [
            'event' => $event,
            'event_id' => $payload['id'] ?? $request->header('X-Zernio-Event-Id') ?? $request->header('X-Late-Event-Id'),
        ]);

        if ($event !== 'message.received') {
            Log::info('Zernio webhook ignored (unsupported event)', ['event' => $event]);

            return response('Ignored', 200);
        }

        $eventId = $payload['id']
            ?? $request->header('X-Zernio-Event-Id')
            ?? $request->header('X-Late-Event-Id')
            ?? uniqid('zernio_', true);

        $data = $payload['data'] ?? $payload;
        $message = $payload['message'] ?? data_get($data, 'message', []);
        $conversation = $payload['conversation'] ?? data_get($data, 'conversation', []);

        $conversationId = data_get($message, 'conversationId')
            ?? data_get($conversation, 'id')
            ?? data_get($conversation, '_id')
            ?? data_get($data, 'conversationId')
            ?? data_get($data, 'conversation._id')
            ?? data_get($data, 'conversation.id');

        $messageText = trim((string) (
            data_get($message, 'text')
            ?? data_get($message, 'body')
            ?? data_get($data, 'message.text')
            ?? data_get($data, 'message.body')
            ?? data_get($data, 'text')
            ?? data_get($data, 'body')
            ?? ''
        ));

        $fromPhone = (string) (
            data_get($message, 'sender.phoneNumber')
            ?? data_get($message, 'sender.phone')
            ?? data_get($payload, 'conversation.participantUsername')
            ?? data_get($conversation, 'participantUsername')
            ?? data_get($data, 'message.sender.phoneNumber')
            ?? data_get($data, 'message.sender.phone')
            ?? data_get($data, 'message.from')
            ?? data_get($data, 'message.senderPhone')
            ?? data_get($data, 'contact.phone')
            ?? data_get($data, 'from')
            ?? ''
        );

        if (! $conversationId || $messageText === '') {
            Log::info('Zernio webhook missing conversation or message', [
                'conversation_id' => $conversationId,
                'from_phone' => $fromPhone,
                'message_preview' => mb_substr($messageText, 0, 200),
                'payload_keys' => array_keys($payload),
            ]);

            return response('Missing fields', 200);
        }

        $userId = null;
        $normalizedPhone = ZernioService::normalizePhone($fromPhone);
        if ($normalizedPhone) {
            $user = User::where('whatsapp_phone', $normalizedPhone)->first();
            if ($user && ! $user->zernio_conversation_id) {
                $user->update(['zernio_conversation_id' => $conversationId]);
            }
            $userId = $user?->id;
        }

        ProcessWhatsAppCommand::dispatch(
            $eventId,
            $conversationId,
            $fromPhone,
            $messageText,
            $userId,
        );

        Log::info('Zernio webhook queued WhatsApp command', [
            'event_id' => $eventId,
            'conversation_id' => $conversationId,
            'from_phone' => $fromPhone,
            'user_id' => $userId,
            'message_preview' => mb_substr($messageText, 0, 120),
            'user_linked' => $userId !== null,
        ]);

        return response('OK', 200);
    }
}
