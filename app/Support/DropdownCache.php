<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Short-TTL, fail-open cache for small, frequently-reused reference/lookup
 * data (medicine catalogs, shift dropdown sources) that would otherwise
 * re-run the same unpaginated query on every form render.
 *
 * Explicitly uses the 'redis' cache store rather than Cache::remember()'s
 * app-default store: this app's CACHE_STORE is 'array' (in-process, one
 * request only — see config/cache.php) since central-service has no other
 * cache usage today. A dropdown cache on the array store would silently
 * do nothing (a fresh array cache exists per request, so it would
 * "hit" zero times across requests) — config/cache.php does define a
 * 'redis' store (the 'cache' Redis connection, DB1, shared with
 * Database-final's own cache but key-prefixed separately), so that's
 * targeted directly here instead.
 *
 * Only for non-PHI reference/inventory data — never patient content.
 *
 * Round-trips the result through plain PHP arrays (via ->toArray(), which
 * correctly applies each model's casts/hidden/appends) rather than caching
 * Eloquent models directly: config/cache.php sets 'serializable_classes'
 * => false (a real Laravel security default, hardening against PHP Object
 * Injection via cache poisoning), which makes the redis cache store call
 * unserialize($value, ['allowed_classes' => false]) on every read — PHP's
 * unserialize() with allowed_classes=false refuses to reconstruct ANY
 * object, silently replacing it with __PHP_Incomplete_Class instead. This
 * only ever surfaces on a cache *hit* (a cache miss returns the
 * freshly-computed value directly, no unserialize() involved), which is
 * why it's easy to miss in quick manual testing: the first request always
 * works. Callers here only ever response()->json(...) the result, and a
 * plain array/stdClass JSON-encodes identically to the original Eloquent
 * model (Laravel's JSON encoding already goes through the same toArray()
 * internally), so returning stdClass instead of live models costs nothing.
 *
 * Catches \Throwable, not just \RedisException: a caching layer must never
 * be able to take a feature down, regardless of *why* the cache failed —
 * connection refused, wrong password, or (as found in local dev) the
 * phpredis extension not being loaded at all, which fails with a base
 * \Error ("Class Redis not found"), not \RedisException. Safe to retry
 * $compute() on any failure only because every current caller here is a
 * pure, idempotent read query — never wrap a closure with side effects.
 */
class DropdownCache
{
    private const TTL = 120;

    public static function remember(string $key, callable $compute)
    {
        try {
            $rows = Cache::store('redis')->remember(
                "dropdown:{$key}",
                self::TTL,
                fn () => collect($compute())->map(fn ($row) => is_array($row) ? $row : $row->toArray())->all()
            );

            return collect($rows)->map(fn (array $row) => (object) $row);
        } catch (\Throwable $e) {
            Log::warning('dropdown cache unavailable, computing uncached', ['error' => $e->getMessage(), 'key' => $key]);

            return $compute();
        }
    }
}
