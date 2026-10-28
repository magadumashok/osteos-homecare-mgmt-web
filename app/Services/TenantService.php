<?php

namespace App\Services;

use App\Models\Homecare;
use App\Models\Role;
use App\Support\Permissions;
use App\Tenancy\TenantSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MongoDB\Database;
use MongoDB\Driver\Exception\CommandException;
use MongoDB\Laravel\Connection;

/**
 * One MongoDB database per homecare center (like the EMR's per-clinic
 * databases). This service names, switches to and provisions them.
 *
 * Unlike the EMR, the tenant is never taken from a client-supplied header:
 * ResolveTenant derives it from the authenticated user's homecare_id.
 */
class TenantService
{
    private const CONNECTION = 'mongodb_tenant';

    private ?string $current = null;

    private ?Homecare $homecare = null;

    /**
     * Database name for a homecare_id. Deterministic, lowercase, limited
     * to the characters MongoDB allows, and within Atlas' 38-byte limit
     * (long names keep a readable prefix plus a stable hash).
     */
    public function databaseName(string $homecareId): string
    {
        $prefix = config('homecare.tenant.database_prefix');
        $max = config('homecare.tenant.max_database_name_length');

        $slug = strtolower(trim($homecareId));
        $slug = preg_replace('/[^a-z0-9_]+/', '_', $slug);
        $slug = trim($slug, '_');

        if ($slug === '') {
            throw new \InvalidArgumentException("Invalid homecare_id '{$homecareId}'.");
        }

        $name = $prefix.$slug;

        if (strlen($name) > $max) {
            $hash = substr(hash('sha1', $homecareId), 0, 8);
            $name = substr($name, 0, $max - 9).'_'.$hash;
        }

        return $name;
    }

    /** Point the tenant connection (and tenant models) at this homecare's database. */
    public function use(string $homecareId): void
    {
        $database = $this->databaseName($homecareId);

        if ($this->current === $homecareId && config('database.connections.'.self::CONNECTION.'.database') === $database) {
            return;
        }

        config(['database.connections.'.self::CONNECTION.'.database' => $database]);
        DB::purge(self::CONNECTION);

        $this->current = $homecareId;
        $this->homecare = null;
    }

    /** The homecare_id the tenant connection currently points at, if any. */
    public function current(): ?string
    {
        return $this->current;
    }

    /** The current homecare center's registry entry, loaded once per request. */
    public function homecare(): ?Homecare
    {
        if ($this->current === null) {
            return null;
        }

        return $this->homecare ??= Homecare::where('homecare_id', $this->current)->first();
    }

    /** Display name for messages: the center's name, else its homecare_id. */
    public function displayName(): string
    {
        return (string) ($this->homecare()?->name ?: $this->current);
    }

    /**
     * The center's local timezone, which duty dates and times are in:
     * HOMECARE_TIMEZONE, else its country's (first) zone, else the app's.
     */
    public function timezone(): string
    {
        if ($zone = config('homecare.attendance.timezone')) {
            return $zone;
        }

        $country = strtoupper(trim((string) $this->homecare()?->country));
        if (strlen($country) === 2) {
            try {
                $zones = \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, $country);
                if ($zones) {
                    return $zones[0];
                }
            } catch (\ValueError) {
                // Not an ISO country code: fall through.
            }
        }

        return config('app.timezone');
    }

    /** Run a callback against another homecare's database, then switch back. */
    public function run(string $homecareId, callable $callback): mixed
    {
        $previous = $this->current;
        $this->use($homecareId);

        try {
            return $callback();
        } finally {
            if ($previous !== null) {
                $this->use($previous);
            }
        }
    }

    /**
     * Create collections and indexes and seed default roles. Safe to run
     * repeatedly: existing collections, indexes and roles are left alone.
     */
    public function provision(Homecare $homecare): void
    {
        $this->run($homecare->homecare_id, function () use ($homecare) {
            $db = $this->mongo();

            $this->ensureCollections($db);
            $this->ensureIndexes($db);
            $this->seedDefaultRoles();

            Log::info('[Tenant] Provisioned database', ['homecare_id' => $homecare->homecare_id, 'database' => $db->getDatabaseName()]);
        });

        if ($homecare->database !== $this->databaseName($homecare->homecare_id)) {
            $homecare->database = $this->databaseName($homecare->homecare_id);
            $homecare->save();
        }
    }

    /** Create any missing indexes on the current tenant database. */
    public function ensureIndexes(?Database $db = null): void
    {
        $db ??= $this->mongo();

        foreach (TenantSchema::COLLECTIONS as $collection => $indexes) {
            foreach ($indexes as $index) {
                $db->selectCollection($collection)->createIndex($index[0], $index[1] ?? []);
            }
        }
    }

    private function ensureCollections(Database $db): void
    {
        $existing = iterator_to_array($db->listCollectionNames());

        foreach (TenantSchema::collections() as $collection) {
            if (in_array($collection, $existing, true)) {
                continue;
            }
            try {
                $db->createCollection($collection);
            } catch (CommandException $e) {
                // Created concurrently by another request: fine.
                if ($e->getCode() !== 48) {
                    throw $e;
                }
            }
        }
    }

    private function seedDefaultRoles(): void
    {
        foreach (Permissions::DEFAULT_ROLES as $name => $role) {
            Role::firstOrCreate(['name' => $name], [
                'description' => $role['description'],
                'permissions' => $role['permissions'],
                'system' => true,
            ]);
        }
    }

    private function mongo(): Database
    {
        /** @var Connection $connection */
        $connection = DB::connection(self::CONNECTION);

        return $connection->getDatabase();
    }
}
