<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row hospital-wide configuration. Read/write here as part of the
 * BFF migration (see /api/hospital-settings) — Database-final's Settings
 * screen now goes through this API instead of querying Postgres directly.
 * Still never migrates: the table itself stays owned by Database-final.
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
