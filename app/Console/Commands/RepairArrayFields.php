<?php

namespace App\Console\Commands;

use App\Casts\NativeArray;
use App\Models\Homecare;
use App\Services\TenantService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use MongoDB\Collection;

/**
 * Converts list fields that were saved as JSON strings (by Laravel's
 * `array` cast) into native arrays so MongoDB can query them: users.roles
 * in the master database and roles.permissions in every homecare database.
 * Only string values are touched, so it is safe to re-run.
 */
class RepairArrayFields extends Command
{
    protected $signature = 'homecare:repair-arrays {--dry-run : Report what would change without writing}';

    protected $description = 'Store users.roles and roles.permissions as native MongoDB arrays';

    public function handle(TenantService $tenants): int
    {
        $users = DB::connection('mongodb_master')->getDatabase()->selectCollection('users');
        $this->info('users.roles: '.$this->repair($users, 'roles'));

        foreach (Homecare::all() as $homecare) {
            $count = $tenants->run($homecare->homecare_id, function () {
                $roles = DB::connection('mongodb_tenant')->getDatabase()->selectCollection('roles');

                return $this->repair($roles, 'permissions');
            });
            $this->line("  {$homecare->homecare_id} roles.permissions: {$count}");
        }

        return self::SUCCESS;
    }

    private function repair(Collection $collection, string $field): int
    {
        $count = 0;

        // {$type: "string"} would also match arrays of strings; compare the field's own type.
        $filter = ['$expr' => ['$eq' => [['$type' => '$'.$field], 'string']]];

        foreach ($collection->find($filter, ['projection' => [$field => 1]]) as $document) {
            $count++;
            if (! $this->option('dry-run')) {
                $collection->updateOne(
                    ['_id' => $document['_id']],
                    ['$set' => [$field => NativeArray::normalize($document[$field])]],
                );
            }
        }

        return $count;
    }
}
