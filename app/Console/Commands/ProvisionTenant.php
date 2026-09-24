<?php

namespace App\Console\Commands;

use App\Models\Homecare;
use App\Services\TenantService;
use Illuminate\Console\Command;

class ProvisionTenant extends Command
{
    protected $signature = 'homecare:provision
        {homecare_id? : Provision a single homecare center}
        {--all : Provision every registered homecare center}';

    protected $description = 'Create collections, indexes and default roles in homecare databases (safe to re-run)';

    public function handle(TenantService $tenants): int
    {
        $query = Homecare::query();

        if ($id = $this->argument('homecare_id')) {
            $query->where('homecare_id', $id);
        } elseif (! $this->option('all')) {
            $this->error('Pass a homecare_id or --all.');

            return self::INVALID;
        }

        $homecares = $query->get();

        if ($homecares->isEmpty()) {
            $this->warn('No matching homecare centers found.');

            return self::FAILURE;
        }

        foreach ($homecares as $homecare) {
            $tenants->provision($homecare);
            $this->info("{$homecare->homecare_id} → {$tenants->databaseName($homecare->homecare_id)}");
        }

        return self::SUCCESS;
    }
}
