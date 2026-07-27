<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasBusinessKey;

    protected $table = 'patient';
    protected $primaryKey = 'patient_id';
    public string $idPrefix = 'PAT';
    protected $guarded = [];

    public function insurance()
    {
        return $this->hasMany(PatientInsurance::class, 'patient_id', 'patient_id');
    }

    public function doctorAssignments()
    {
        return $this->hasMany(PatientDoctorAssignment::class, 'patient_id', 'patient_id');
    }

    public function nurseAssignments()
    {
        return $this->hasMany(PatientNurseAssignment::class, 'patient_id', 'patient_id');
    }

    public function adjustments()
    {
        return $this->hasMany(PatientAdjustment::class, 'patient_id', 'patient_id');
    }

    public function fullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
