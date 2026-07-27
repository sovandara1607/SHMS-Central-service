<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class MedicineBatch extends Model
{
    use HasBusinessKey;

    protected $table = 'medicine_batch';
    protected $primaryKey = 'batch_id';
    public string $idPrefix = 'BAT';
    public $timestamps = false;
    protected $guarded = [];
}
