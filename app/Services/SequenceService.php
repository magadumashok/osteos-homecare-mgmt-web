<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use MongoDB\Collection;
use MongoDB\Laravel\Connection;
use MongoDB\Operation\FindOneAndUpdate;

/**
 * Atomic per-homecare sequence numbers, kept in the tenant's `counters`
 * collection.
 *
 * The legacy app numbered leads and patients with `count() + 1`, so two
 * requests at the same moment (or a deleted record) produced duplicates.
 * A counter that does not exist yet starts from the value `$seed`
 * returns, so migrated data carries on where it left off.
 */
class SequenceService
{
    /** @param  callable(): int  $seed  highest number already in use */
    public function next(string $name, callable $seed): int
    {
        $counters = $this->counters();

        if ($counters->countDocuments(['_id' => $name], ['limit' => 1]) === 0) {
            // $setOnInsert keeps a concurrent first request from resetting it.
            $counters->updateOne(
                ['_id' => $name],
                ['$setOnInsert' => ['seq' => max(0, (int) $seed())]],
                ['upsert' => true],
            );
        }

        $counter = $counters->findOneAndUpdate(
            ['_id' => $name],
            ['$inc' => ['seq' => 1]],
            ['upsert' => true, 'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER],
        );

        return (int) $counter['seq'];
    }

    private function counters(): Collection
    {
        /** @var Connection $connection */
        $connection = DB::connection('mongodb_tenant');

        return $connection->getDatabase()->selectCollection('counters');
    }
}
