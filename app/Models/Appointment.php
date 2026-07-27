<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasBusinessKey;

    protected $table = 'appointment';
    protected $primaryKey = 'appointment_id';
    public string $idPrefix = 'APT';
    protected $guarded = [];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'patient_id');
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id', 'doctor_id');
    }

    public function bookedByStaff()
    {
        return $this->belongsTo(Staff::class, 'booked_by', 'staff_id');
    }
}
