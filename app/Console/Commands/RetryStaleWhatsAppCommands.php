<?php

namespace App\Console\Commands;

use App\Jobs\ProcessWhatsAppCommand;
use App\Models\WhatsAppCommandLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryStaleWhatsAppCommands extends Command
{
    protected $signature = 'whatsapp:retry-stale {--minutes=3 : Retry commands queued/processing longer than this}';

    protected $description = 'Re-dispatch WhatsApp commands that were queued but never completed';

    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $cutoff = now()->subMinutes($minutes);

        $stale = WhatsAppCommandLog::query()
            ->whereIn('status', ['queued', 'processing'])
            ->where('updated_at', '<', $cutoff)
            ->orderBy('id')
            ->limit(20)
            ->get();

        if ($stale->isEmpty()) {
            $this->info('No stale WhatsApp commands found.');

            return self::SUCCESS;
        }

        $retried = 0;

        foreach ($stale as $log) {
            if ($log->zernio_event_id === null || $log->conversation_id === null) {
                continue;
            }

            $previousStatus = $log->status;

            ProcessWhatsAppCommand::dispatch(
                (string) $log->zernio_event_id,
                (string) $log->conversation_id,
                (string) $log->from_phone,
                (string) ($log->command ?? ''),
                $log->user_id,
            );

            $log->update(['status' => 'queued', 'error' => 'retry_stale']);
            $retried++;

            Log::warning('WhatsApp stale command re-dispatched', [
                'event_id' => $log->zernio_event_id,
                'command' => $log->command,
                'previous_status' => $previousStatus,
            ]);
        }

        $this->warn("Re-dispatched {$retried} stale WhatsApp command(s).");

        return self::SUCCESS;
    }
}
