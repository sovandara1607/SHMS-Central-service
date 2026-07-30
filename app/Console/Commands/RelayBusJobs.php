<?php

namespace App\Console\Commands;

use App\Jobs\GenerateLabReportDocumentJob;
use App\Jobs\LogAuditEventJob;
use App\Jobs\SyncMedicalRecordVersionJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * The only integration point with the Application Server (Database-final):
 * blocks on the shared, unprefixed 'central-service:jobs' Redis list (the
 * 'bus' connection — see config/database.php) and turns each raw JSON
 * message into one of this app's own queued jobs, which then get tries/
 * backoff/failed_jobs handling from the normal `queue:work` process.
 *
 * The contract is JSON over a list, not a shared PHP class, so the two
 * codebases can evolve independently. Run this alongside `queue:work`:
 *   php artisan bus:relay
 *   php artisan queue:work --tries=3
 */
class RelayBusJobs extends Command
{
    protected $signature = 'bus:relay';

    protected $description = 'Consume job messages from the shared Redis bus and dispatch them onto the internal queue';

    private const QUEUE_KEY = 'central-service:jobs';

    private const DISPATCH_MAP = [
        'log_audit_event' => LogAuditEventJob::class,
        'sync_medical_record_version' => SyncMedicalRecordVersionJob::class,
        'generate_lab_report_document' => GenerateLabReportDocumentJob::class,
    ];

    public function handle(): int
    {
        $this->info('Relaying jobs from bus list ['.self::QUEUE_KEY.']...');

        while (true) {
            $result = Redis::connection('bus')->blpop([self::QUEUE_KEY], 5);

            if ($result === null || $result === []) {
                continue;
            }

            [, $raw] = $result;
            $this->relay($raw);
        }
    }

    private function relay(string $raw): void
    {
        $message = json_decode($raw, associative: true);

        if (! is_array($message) || ! isset($message['type'], $message['payload']) || ! is_array($message['payload'])) {
            // Never log the raw payload — bus messages carry PHI (clinical
            // snapshots, patient identifiers). Log enough to correlate
            // repeated bad messages without writing patient data to disk.
            Log::warning('central-service.bus: malformed message, skipping', [
                'length' => strlen($raw),
                'preview_hash' => substr(hash('sha256', $raw), 0, 12),
            ]);

            return;
        }

        $jobClass = self::DISPATCH_MAP[$message['type']] ?? null;

        if ($jobClass === null) {
            Log::warning('central-service.bus: unknown message type, skipping', ['type' => $message['type']]);

            return;
        }

        // Named-argument unpacking: payload keys must match the job's
        // constructor parameter names exactly (see each Job class), not
        // rely on JSON key order.
        $jobClass::dispatch(...$message['payload']);
        $this->line("dispatched {$message['type']}");
    }
}
