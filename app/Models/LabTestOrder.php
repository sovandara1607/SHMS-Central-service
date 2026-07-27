<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class LabTestOrder extends Model
{
    use HasBusinessKey;

    protected $table = 'lab_test_order';
    protected $primaryKey = 'test_order_id';
    public string $idPrefix = 'LAB';
    public $timestamps = false;
    protected $guarded = [];
}
