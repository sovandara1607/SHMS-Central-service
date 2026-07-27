<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class MedicalRecordAdjustment extends Model
{
    use HasBusinessKey;

    protected $table = 'medical_record_adjustment';
    protected $primaryKey = 'adjustment_id';
    public string $idPrefix = 'ADJ';
    public $timestamps = false;
    protected $guarded = [];
}
