<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class Bill extends Model
{
    use HasBusinessKey;

    protected $table = 'bill';
    protected $primaryKey = 'bill_id';
    public string $idPrefix = 'BIL';
    public $timestamps = false;
    protected $guarded = [];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'patient_id');
    }

    public function items()
    {
        return $this->hasMany(BillItem::class, 'bill_id', 'bill_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'bill_id', 'bill_id');
    }

    public function paidAmount(): float
    {
        return (float) $this->payments()->sum('amount_paid');
    }

    public function recomputeTotal(): void
    {
        $this->total_amount = (float) $this->items()->sum('subtotal');
        $this->save();
    }
}
