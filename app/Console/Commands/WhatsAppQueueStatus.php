<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

class WhatsAppQueueStatus extends Command
{
    protected $signature = 'whatsapp:queue-status';

    protected $description = 'Show WhatsApp queue configuration and pending/failed jobs';

    public function handle(): int
    {
        $connection = config('queue.default');

        $this->info('Queue connection (QUEUE_CONNECTION): '.$connection);
        $this->line('Database: '.config('database.connections.'.config('database.default').'.database'));

        if ($connection === 'sync') {
            $this->warn('QUEUE_CONNECTION=sync — jobs run during the web request. queue:work will not process anything.');
            $this->line('Set QUEUE_CONNECTION=redis or database in Forge Environment, then: php artisan config:clear');
        }

        if ($connection === 'redis') {
            try {
                $redisPending = Queue::connection('redis')->size('default');
                $this->line('Pending jobs on redis [default]: '.$redisPending);
            } catch (\Throwable $e) {
                $this->error('Could not read redis queue: '.$e->getMessage());
            }
        }

        if (Schema::hasTable('jobs')) {
            $pending = (int) DB::table('jobs')->count();
            $whatsappPending = (int) DB::table('jobs')
                ->where('payload', 'like', '%ProcessWhatsAppCommand%')
                ->count();

            if ($pending > 0) {
                $this->warn("Pending jobs in database queue table: {$pending} ({$whatsappPending} WhatsApp)");
                if ($connection === 'redis') {
                    $this->line('These were likely queued before redis switch, or while jobs used onConnection(database).');
                    $this->line('Clear with: php artisan queue:work database --stop-when-empty');
                }
            }
        }

        if (Schema::hasTable('failed_jobs')) {
            $failed = (int) DB::table('failed_jobs')
                ->where('payload', 'like', '%ProcessWhatsAppCommand%')
                ->count();
            $this->line('Failed WhatsApp jobs: '.$failed);

            $recentFailed = DB::table('failed_jobs')
                ->where('payload', 'like', '%ProcessWhatsAppCommand%')
                ->orderByDesc('id')
                ->limit(3)
                ->get(['id', 'failed_at', 'exception']);

            foreach ($recentFailed as $row) {
                $this->newLine();
                $this->warn("Failed job #{$row->id} at {$row->failed_at}");
                $this->line(mb_substr((string) $row->exception, 0, 400).'...');
            }
        }

        $this->newLine();
        $this->info('Run worker for your active connection:');
        $this->line("  php artisan queue:work {$connection} --verbose --once");

        return self::SUCCESS;
    }
}
