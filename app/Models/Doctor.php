<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only mirror of Database-final's `doctor` table (Identity domain —
 * stays owned/migrated there). Central Service only reads this to resolve
 * doctor names for patient-assignment display; never writes here.
 */
class Doctor extends Model
{
    protected $table = 'doctor';
    protected $primaryKey = 'doctor_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    public function name(): string
    {
        return $this->staff ? $this->staff->fullName() : $this->doctor_id;
    }
}
