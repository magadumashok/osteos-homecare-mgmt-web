<?php

namespace App\Services;

use App\Models\City;
use App\Models\Country;
use App\Models\IcdCode;
use App\Models\Nationality;
use App\Models\Region;
use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use MongoDB\BSON\Regex;

/**
 * Countries, regions, cities, nationalities, service packages and ICD
 * codes (master database), and phone number formatting.
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

    public function servicePackages(): Collection
    {
        return Cache::remember('ref:service_packages', self::TTL, fn () => ServicePackage::get());
    }

    /**
     * ICD codes whose code or description contains the search text, as
     * "CODE-Description" strings (the legacy format).
     *
     * @return list<string>
     */
    public function searchIcdCodes(string $search, int $limit = 50): array
    {
        $search = trim($search);
        if ($search === '') {
            return [];
        }

        $pattern = new Regex(preg_quote($search), 'i');

        return IcdCode::where('Name', 'regex', $pattern)
            ->orWhere('Description', 'regex', $pattern)
            ->limit($limit)
            ->get(['Name', 'Description'])
            ->map(fn (IcdCode $code) => $code->Name.'-'.$code->Description)
            ->all();
    }

    /** International dialling prefix ("+971") for a country code or name. */
    public function phoneCode(?string $country): string
    {
        $country = trim((string) $country);
        if ($country === '') {
            return '';
        }

        return Cache::remember("ref:phone_code:{$country}", self::TTL, function () use ($country) {
            $code = self::COUNTRY_ALIASES[$country] ?? $country;

            return (string) Country::where('code', $code)->orWhere('name', $country)->value('phone_code');
        });
    }

    /** A local number with the country's dialling prefix, or null without a number. */
    public function internationalNumber(?string $country, ?string $mobile): ?string
    {
        $mobile = trim((string) $mobile);

        if ($mobile === '') {
            return null;
        }
        if (str_starts_with($mobile, '+')) {
            return $mobile;
        }

        return $this->phoneCode($country).$mobile;
    }

    public function userNumber(?User $user, string $field = 'mobile'): ?string
    {
        return $user ? $this->internationalNumber($user->phoneCountry(), $user->{$field}) : null;
    }
}
