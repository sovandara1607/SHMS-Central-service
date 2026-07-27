<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only mirror of Database-final's `nurse` table (Identity domain —
 * stays owned/migrated there). Central Service only reads this to resolve
 * nurse names for patient-assignment display; never writes here.
 */
class Nurse extends Model
{
    protected $table = 'nurse';
    protected $primaryKey = 'nurse_id';
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
        return $this->staff ? $this->staff->fullName() : $this->nurse_id;
    }
}
