<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Writes one audit-log document to MongoDB (audit_log_documents). Dispatched
 * onto this app's own queue by App\Console\Commands\RelayBusJobs after it
 * picks a 'log_audit_event' message off the shared Redis bus — the
 * Application Server never constructs this class directly.
 */
class LogAuditEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        private readonly string $action,
        private readonly string $entity,
        private readonly ?string $entityId,
        private readonly array $meta,
        private readonly ?string $actorId,
        private readonly ?string $actorRole,
        private readonly ?string $ip,
        private readonly string $at,
    ) {}

    public function handle(): void
    {
        DB::connection('mongodb')->table('audit_log_documents')->insert([
            'action'     => $this->action,
            'entity'     => $this->entity,
            'entity_id'  => $this->entityId,
            'actor_id'   => $this->actorId,
            'actor_role' => $this->actorRole,
            'meta'       => $this->meta,
            'ip'         => $this->ip,
            'at'         => $this->at,
        ]);
    }
}
