<?php

namespace App\Services;

use App\Models\City;
use App\Models\Country;
use App\Models\Nationality;
use App\Models\Region;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Countries, regions, cities and nationalities (master database).
 * Replaces the legacy Redis caching with the configured cache store.
 */
class ReferenceDataService
{
    private const TTL = 86400;

    /** Country names the legacy clients sent instead of codes. */
    private const COUNTRY_ALIASES = ['India' => 'IN', 'USA' => 'US', 'UAE' => 'AE'];

    public function countries(): Collection
    {
        return Cache::remember('ref:countries', self::TTL, fn () => Country::orderBy('label')->get());
    }

    public function nationalities(): Collection
    {
        return Cache::remember('ref:nationalities', self::TTL, fn () => Nationality::orderBy('name')->get());
    }

    public function regions(string $country): Collection
    {
        $code = self::COUNTRY_ALIASES[$country] ?? $country;

        return Cache::remember("ref:regions:{$code}", self::TTL, fn () => Region::where('country_code', $code)->orderBy('label')->get());
    }

    public function cities(string $region): Collection
    {
        return Cache::remember("ref:cities:{$region}", self::TTL, fn () => City::where('region_code', $region)->orderBy('label')->get());
    }

    public function allRegions(): Collection
    {
        return Region::get();
    }

    public function allCities(): Collection
    {
        return City::get();
    }
}
