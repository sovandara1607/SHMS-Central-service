<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class PatientInsurance extends Model
{
    use HasBusinessKey;

    protected $table = 'patient_insurance';
    protected $primaryKey = 'insurance_id';
    public string $idPrefix = 'INS';
    public $timestamps = false;
    protected $guarded = [];
}
