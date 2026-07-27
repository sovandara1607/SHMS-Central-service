<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

/**
 * As of Wave 5, the Pharmacy domain (medicine, medicine_batch,
 * dispensing_record, dispensing_item) is fully owned here. This model
 * started as a Wave 2 read-only mirror for resolving medicine names on
 * prescription items — it's now the writable source used by
 * PharmacyApiController too.
 */
class Medicine extends Model
{
    use HasBusinessKey;

    protected $table = 'medicine';
    protected $primaryKey = 'medicine_id';
    public string $idPrefix = 'MED';
    public $timestamps = false;
    protected $guarded = [];

    public function batches()
    {
        return $this->hasMany(MedicineBatch::class, 'medicine_id', 'medicine_id');
    }
}
