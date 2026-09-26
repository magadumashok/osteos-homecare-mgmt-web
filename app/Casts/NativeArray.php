<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A list stored as a native MongoDB array.
 *
 * Laravel's `array` cast JSON-encodes the value into a string, which MongoDB
 * cannot query (`where('roles', 'admin')` never matches '["admin"]').
 * Values written that way are still read correctly; `homecare:repair-arrays`
 * converts them in the database.
 */
class NativeArray implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        return self::normalize($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        return [$key => self::normalize($value)];
    }

    public static function normalize(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [$value];
        }
        if ($value instanceof \Traversable) {
            return iterator_to_array($value);
        }

        return (array) $value;
    }
}
