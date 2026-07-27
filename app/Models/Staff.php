<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only mirror of Database-final's `staff` table (Identity domain —
 * stays owned/migrated there). Central Service only reads this to resolve
 * staff names (e.g. "assigned by"); never writes here.
 */
class Staff extends Model
{
    protected $table = 'staff';
    protected $primaryKey = 'staff_id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    public function fullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
