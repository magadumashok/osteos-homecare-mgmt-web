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

    /** Dialling codes used when the country documents have no `phone_code`. */
    private const DIALLING_CODES = [
        'AE' => '+971', 'SA' => '+966', 'OM' => '+968', 'QA' => '+974', 'KW' => '+965', 'BH' => '+973',
        'IN' => '+91', 'PK' => '+92', 'BD' => '+880', 'LK' => '+94', 'NP' => '+977', 'PH' => '+63',
        'EG' => '+20', 'JO' => '+962', 'LB' => '+961', 'GH' => '+233', 'LR' => '+231', 'NG' => '+234',
        'KE' => '+254', 'ZA' => '+27', 'GB' => '+44', 'US' => '+1', 'CA' => '+1',
    ];

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

        $key = "ref:phone_code:{$country}";
        if (($cached = Cache::get($key)) !== null && $cached !== '') {
            return $cached;
        }

        $record = Country::where('code', self::COUNTRY_ALIASES[$country] ?? $country)
            ->orWhere('name', $country)
            ->orWhere('label', $country)
            ->first(['code', 'phone_code']);

        // Some country collections have no phone_code; fall back to the ISO code.
        $isoCode = strtoupper((string) ($record->code ?? self::COUNTRY_ALIASES[$country] ?? $country));
        $phoneCode = (string) ($record->phone_code ?? '') ?: (self::DIALLING_CODES[$isoCode] ?? '');

        if ($phoneCode !== '' && ! str_starts_with($phoneCode, '+')) {
            $phoneCode = '+'.$phoneCode;
        }

        // An unknown country is not cached, so adding it later takes effect at once.
        if ($phoneCode !== '') {
            Cache::put($key, $phoneCode, self::TTL);
        }

        return $phoneCode;
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
