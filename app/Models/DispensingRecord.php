<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class DispensingRecord extends Model
{
    use HasBusinessKey;

    protected $table = 'dispensing_record';
    protected $primaryKey = 'dispensing_id';
    public string $idPrefix = 'DSP';
    public $timestamps = false;
    protected $guarded = [];
}
