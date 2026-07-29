<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class MedicalRecord extends Model
{
    use HasBusinessKey;

    protected $table = 'medical_record';
    protected $primaryKey = 'medical_record_id';
    public string $idPrefix = 'MR';
    public $timestamps = false;
    protected $guarded = [];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'patient_id');
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id', 'doctor_id');
    }

    public function adjustments()
    {
        return $this->hasMany(MedicalRecordAdjustment::class, 'medical_record_id', 'medical_record_id');
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class, 'medical_record_id', 'medical_record_id');
    }

    public function reports()
    {
        return $this->hasMany(MedicalReport::class, 'medical_record_id', 'medical_record_id');
    }

    public function procedures()
    {
        return $this->hasMany(MedicalProcedure::class, 'medical_record_id', 'medical_record_id');
    }

    public function vitalSigns()
    {
        return $this->hasMany(VitalSign::class, 'medical_record_id', 'medical_record_id');
    }
}
