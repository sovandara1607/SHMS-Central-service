<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class DispensingItem extends Model
{
    use HasBusinessKey;

    protected $table = 'dispensing_item';
    protected $primaryKey = 'dispensing_item_id';
    public string $idPrefix = 'DIT';
    public $timestamps = false;
    protected $guarded = [];
}
