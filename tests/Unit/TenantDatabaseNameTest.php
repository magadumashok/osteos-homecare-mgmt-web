<?php

namespace Tests\Unit;

use App\Services\TenantService;
use Tests\TestCase;

class TenantDatabaseNameTest extends TestCase
{
    private TenantService $tenants;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenants = new TenantService;
    }

    public function test_uses_prefix_and_slug(): void
    {
        $this->assertSame('homecare_sunrise', $this->tenants->databaseName('sunrise'));
    }

    public function test_replaces_characters_mongodb_rejects(): void
    {
        $this->assertSame('homecare_care_plus_llc', $this->tenants->databaseName('Care-Plus.LLC'));
    }

    public function test_long_names_fit_atlas_limit_and_stay_unique(): void
    {
        $a = $this->tenants->databaseName('sunrise-home-healthcare-services-dubai-branch-one');
        $b = $this->tenants->databaseName('sunrise-home-healthcare-services-dubai-branch-two');

        $this->assertLessThanOrEqual(38, strlen($a));
        $this->assertLessThanOrEqual(38, strlen($b));
        $this->assertNotSame($a, $b);
        $this->assertSame($a, $this->tenants->databaseName('sunrise-home-healthcare-services-dubai-branch-one'));
    }

    public function test_rejects_empty_ids(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->tenants->databaseName(' .. ');
    }
}
