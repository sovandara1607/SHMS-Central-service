<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class PatientDoctorAssignment extends Model
{
    use HasBusinessKey;

    protected $table = 'patient_doctor_assignment';
    protected $primaryKey = 'assignment_id';
    public string $idPrefix = 'PDA';
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

    public function assignedByStaff()
    {
        return $this->belongsTo(Staff::class, 'assigned_by', 'staff_id');
    }
}
