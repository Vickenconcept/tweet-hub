<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WhatsAppQueueStatus extends Command
{
    protected $signature = 'whatsapp:queue-status';

    protected $description = 'Show WhatsApp queue configuration and pending/failed jobs';

    public function handle(): int
    {
        $connection = config('queue.default');

        $this->info('Queue connection: '.$connection);
        $this->line('Database: '.config('database.connections.'.config('database.default').'.database'));

        if ($connection === 'sync') {
            $this->warn('QUEUE_CONNECTION=sync — jobs run during the web request. queue:work will not process anything.');
            $this->line('Set QUEUE_CONNECTION=database in Forge Environment, then: php artisan config:clear');
        }

        if (! Schema::hasTable('jobs')) {
            $this->error('The jobs table is missing. Run: php artisan migrate');

            return self::FAILURE;
        }

        $pending = (int) DB::table('jobs')->count();
        $reserved = (int) DB::table('jobs')->whereNotNull('reserved_at')->count();
        $whatsappPending = (int) DB::table('jobs')
            ->where('payload', 'like', '%ProcessWhatsAppCommand%')
            ->count();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Pending jobs (all)', $pending],
                ['Reserved / in-flight', $reserved],
                ['Pending WhatsApp jobs', $whatsappPending],
            ]
        );

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

        if ($pending > 0 && $connection !== 'sync') {
            $this->newLine();
            $this->info('Process pending jobs with:');
            $this->line('  cd ~/tweet-hub-nozmzves.on-forge.com/current && php artisan queue:work database --verbose --once');
        }

        return self::SUCCESS;
    }
}
