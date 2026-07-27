<?php

namespace App\Models\Concerns;

/**
 * Shared behaviour for models whose primary key is a human-readable
 * VARCHAR business key (e.g. PAT0001, DOC0001) rather than an auto
 * increment integer. On create, a prefixed sequential id is generated
 * when one is not supplied. Ported verbatim from Database-final so both
 * apps generate colliding-free keys against the same shared table.
 */
trait HasBusinessKey
{
    public function initializeHasBusinessKey(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    protected static function bootHasBusinessKey(): void
    {
        static::creating(function ($model) {
            $key = $model->getKeyName();
            if (empty($model->{$key}) && property_exists($model, 'idPrefix') && $model->idPrefix) {
                $model->{$key} = static::nextBusinessKey($model->idPrefix, $model->getTable(), $key);
            }
        });
    }

    /** Generate the next prefixed id, e.g. PAT0001 → PAT0002. */
    public static function nextBusinessKey(string $prefix, string $table, string $key): string
    {
        $last = static::query()
            ->where($key, 'like', $prefix . '%')
            ->orderByRaw("length($key) desc")
            ->orderBy($key, 'desc')
            ->value($key);

        $n = 1;
        if ($last) {
            $n = ((int) preg_replace('/\D/', '', substr($last, strlen($prefix)))) + 1;
        }
        return $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
