<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasBusinessKey;

    protected $table = 'room';
    protected $primaryKey = 'room_id';
    public string $idPrefix = 'RM';
    public $timestamps = false;
    protected $guarded = [];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function beds()
    {
        return $this->hasMany(Bed::class, 'room_id', 'room_id');
    }

    public function activeAssignments()
    {
        return $this->hasMany(RoomAssignment::class, 'room_id', 'room_id')->where('status', 'active');
    }
}
