<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class BillItem extends Model
{
    use HasBusinessKey;

    protected $table = 'bill_item';
    protected $primaryKey = 'bill_item_id';
    public string $idPrefix = 'BI';
    public $timestamps = false;
    protected $guarded = [];
}
