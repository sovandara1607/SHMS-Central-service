<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasBusinessKey;

    protected $table = 'department';
    protected $primaryKey = 'department_id';
    public string $idPrefix = 'DEP';
    public $timestamps = false;
    protected $guarded = [];
}
