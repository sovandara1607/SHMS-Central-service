<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row hospital-wide configuration, read from the shared Postgres
 * table owned by the Application Server (Database-final). Central Service
 * never migrates or writes this table — read-only here.
 */
class HospitalSetting extends Model
{
    protected $table = 'hospital_settings';
    protected $guarded = [];

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }
}
