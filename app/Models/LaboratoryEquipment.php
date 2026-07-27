<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class LaboratoryEquipment extends Model
{
    use HasBusinessKey;

    protected $table = 'laboratory_equipment';
    protected $primaryKey = 'equipment_id';
    public string $idPrefix = 'EQ';
    public $timestamps = false;
    protected $guarded = [];
}
