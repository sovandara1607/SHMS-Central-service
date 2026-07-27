<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class MedicalReport extends Model
{
    use HasBusinessKey;

    protected $table = 'medical_report';
    protected $primaryKey = 'report_id';
    public string $idPrefix = 'REP';
    public $timestamps = false;
    protected $guarded = [];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'patient_id');
    }

    public function medicalRecord()
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id', 'medical_record_id');
    }

    public function generatedByStaff()
    {
        return $this->belongsTo(Staff::class, 'generated_by', 'staff_id');
    }
}
