<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class LabTestResult extends Model
{
    use HasBusinessKey;

    protected $table = 'lab_test_result';
    protected $primaryKey = 'test_result_id';
    public string $idPrefix = 'LRS';
    public $timestamps = false;
    protected $guarded = [];
}
