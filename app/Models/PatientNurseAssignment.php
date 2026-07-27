<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class PatientNurseAssignment extends Model
{
    use HasBusinessKey;

    protected $table = 'patient_nurse_assignment';
    protected $primaryKey = 'assignment_id';
    public string $idPrefix = 'PNA';
    public $timestamps = false;
    protected $guarded = [];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'patient_id');
    }

    public function nurse()
    {
        return $this->belongsTo(Nurse::class, 'nurse_id', 'nurse_id');
    }

    public function shift()
    {
        return $this->belongsTo(StaffShift::class, 'shift_id', 'shift_id');
    }

    public function assignedByStaff()
    {
        return $this->belongsTo(Staff::class, 'assigned_by', 'staff_id');
    }
}
