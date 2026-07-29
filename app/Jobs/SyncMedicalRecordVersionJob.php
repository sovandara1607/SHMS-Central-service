<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use MongoDB\Operation\FindOneAndUpdate;

/**
 * Data Synchronization Engine: mirrors a medical_record create/adjustment
 * into a MongoDB version snapshot (medical_record_versions). Postgres (owned
 * by the Application Server) stays the immediately-consistent source of
 * truth; this Mongo history trail is eventually consistent.
 */
class SyncMedicalRecordVersionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        private readonly string $medicalRecordId,
        private readonly int $version,
        private readonly string $type,
        private readonly array $snapshot,
        private readonly string $actorStaffId,
        private readonly ?string $reason,
        private readonly string $createdAt,
    ) {}

    public function handle(): void
    {
        // $this->version was computed synchronously in Database-final via
        // count()+1 *before* this job was even queued, so under concurrent
        // adjustments to the same record it can race with another in-flight
        // job computing the identical number — by the time either actually
        // writes, the count they both read from is stale. An atomic
        // increment on a per-record counter document, done here at the
        // moment of the real write, can't collide regardless of how many
        // workers process jobs for the same record at once.
        $counter = DB::connection('mongodb')->getCollection('medical_record_version_counters')->findOneAndUpdate(
            ['_id' => $this->medicalRecordId],
            ['$inc' => ['seq' => 1]],
            ['upsert' => true, 'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER],
        );
        $version = is_array($counter) ? $counter['seq'] : $counter->seq;

        DB::connection('mongodb')->table('medical_record_versions')->insert([
            'medical_record_id' => $this->medicalRecordId,
            'version'    => $version,
            'type'       => $this->type,
            'reason'     => $this->reason,
            'snapshot'   => $this->snapshot,
            'created_by' => $this->actorStaffId,
            'created_at' => $this->createdAt,
        ]);
    }
}
