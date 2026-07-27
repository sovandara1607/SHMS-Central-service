<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class PrescriptionItem extends Model
{
    use HasBusinessKey;

    protected $table = 'prescription_item';
    protected $primaryKey = 'prescription_item_id';
    public string $idPrefix = 'PI';
    public $timestamps = false;
    protected $guarded = [];

    public function prescription()
    {
        return $this->belongsTo(Prescription::class, 'prescription_id', 'prescription_id');
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class, 'medicine_id', 'medicine_id');
    }
}
