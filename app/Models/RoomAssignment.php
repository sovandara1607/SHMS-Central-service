<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class RoomAssignment extends Model
{
    use HasBusinessKey;

    protected $table = 'room_assignment';
    protected $primaryKey = 'room_assignment_id';
    public string $idPrefix = 'RA';
    public $timestamps = false;
    protected $guarded = [];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'patient_id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id', 'room_id');
    }

    public function bed()
    {
        return $this->belongsTo(Bed::class, 'bed_id', 'bed_id');
    }

    public function assignedByStaff()
    {
        return $this->belongsTo(Staff::class, 'assigned_by', 'staff_id');
    }
}
