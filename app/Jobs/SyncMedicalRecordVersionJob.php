<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

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
        DB::connection('mongodb')->table('medical_record_versions')->insert([
            'medical_record_id' => $this->medicalRecordId,
            'version'    => $this->version,
            'type'       => $this->type,
            'reason'     => $this->reason,
            'snapshot'   => $this->snapshot,
            'created_by' => $this->actorStaffId,
            'created_at' => $this->createdAt,
        ]);
    }
}
