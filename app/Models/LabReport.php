<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Mirrors Database-final's lab_report table (same Postgres schema). Central
 * Service only ever updates report_file_path here after rendering the PDF —
 * row creation stays owned by the Application Server.
 */
class LabReport extends Model
{
    protected $table = 'lab_report';
    protected $primaryKey = 'lab_report_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];
}
