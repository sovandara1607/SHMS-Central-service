<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class StaffShift extends Model
{
    use HasBusinessKey;

    protected $table = 'staff_shift';
    protected $primaryKey = 'shift_id';
    public string $idPrefix = 'SH';
    public $timestamps = false;
    protected $guarded = [];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }
}
