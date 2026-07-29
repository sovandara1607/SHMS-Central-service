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

    /**
     * Status only ever used to be recomputed inside pay() — so a bill that
     * reached 'paid' and then had a new item added afterward (raising
     * total_amount past what's actually been paid) stayed stuck showing
     * 'paid' forever, and pay() unconditionally 409s whenever status is
     * already 'paid', regardless of the real remaining balance. Called here
     * too so adding an item always leaves status matching the real balance.
     */
    public function recomputeStatus(): void
    {
        $paid = $this->paidAmount();
        $this->status = $paid >= (float) $this->total_amount ? 'paid' : ($paid > 0 ? 'partially_paid' : 'unpaid');
        $this->save();
    }
}
