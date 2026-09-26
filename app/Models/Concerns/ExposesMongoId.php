<?php

namespace App\Models\Concerns;

/**
 * laravel-mongodb 5 renames `_id` to `id` in query results. The legacy
 * mobile app (and migrated data) use `_id`, so serialised models carry both.
 */
trait ExposesMongoId
{
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        if (array_key_exists('id', $attributes) && ! array_key_exists('_id', $attributes)) {
            $attributes = ['_id' => $attributes['id'], ...$attributes];
        }

        return $attributes;
    }
}
