<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class Laboratory extends Model
{
    use HasBusinessKey;

    protected $table = 'laboratory';
    protected $primaryKey = 'laboratory_id';
    public string $idPrefix = 'LABO';
    public $timestamps = false;
    protected $guarded = [];
}
