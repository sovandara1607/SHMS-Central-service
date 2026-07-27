<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessKey;
use Illuminate\Database\Eloquent\Model;

class Bed extends Model
{
    use HasBusinessKey;

    protected $table = 'bed';
    protected $primaryKey = 'bed_id';
    public string $idPrefix = 'BED';
    public $timestamps = false;
    protected $guarded = [];

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id', 'room_id');
    }
}
