<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class PatientAdjustment extends Model
{
    use HasBusinessKey;

    protected $table = 'patient_adjustment';
    protected $primaryKey = 'adjustment_id';
    public string $idPrefix = 'PADJ';
    public $timestamps = false;
    protected $guarded = [];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'patient_id');
    }

    public function adjustedByStaff()
    {
        return $this->belongsTo(Staff::class, 'adjusted_by', 'staff_id');
    }
}
