<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

/**
 * Mirrors Database-final's lab_report table (same Postgres schema). As part
 * of the Wave 3 BFF migration, row creation now happens here too (from
 * LabApiController::enterResult), alongside the pre-existing report_file_path
 * update from the async PDF-generation job.
 */
class LabReport extends Model
{
    use HasBusinessKey;

    protected $table = 'lab_report';
    protected $primaryKey = 'lab_report_id';
    public string $idPrefix = 'LR';
    public $timestamps = false;
    protected $guarded = [];
}
