<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * `staff_shift` belongs to the Wave 6 (Admin facilities) domain. No create/
 * edit UI exists anywhere in the app for it — it's seed data only, read here
 * to resolve shift details for the nurse-assignment dropdown and to back
 * GET /api/staff-shifts.
 */
class StaffShift extends Model
{
    protected $table = 'staff_shift';
    protected $primaryKey = 'shift_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];
}
