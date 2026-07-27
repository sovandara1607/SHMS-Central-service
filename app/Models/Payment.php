<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasBusinessKey;

    protected $table = 'payment';
    protected $primaryKey = 'payment_id';
    public string $idPrefix = 'PAY';
    public $timestamps = false;
    protected $guarded = [];
}
