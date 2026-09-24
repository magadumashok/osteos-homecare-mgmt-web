<?php

namespace App\Http\Controllers\Api\Legacy;

use App\Http\Controllers\Controller;
use App\Services\ReferenceDataService;
use Illuminate\Http\JsonResponse;

/** Legacy reference endpoints: bare JSON arrays, no envelope. */
class ReferenceDataController extends Controller
{
    public function __construct(private readonly ReferenceDataService $reference) {}

    public function countries(): JsonResponse
    {
        return response()->json($this->reference->countries());
    }

    public function nationalities(): JsonResponse
    {
        return response()->json($this->reference->nationalities());
    }

    public function regions(string $country): JsonResponse
    {
        return response()->json($this->reference->regions($country));
    }

    public function cities(string $region): JsonResponse
    {
        return response()->json($this->reference->cities($region));
    }

    public function allRegions(): JsonResponse
    {
        return response()->json($this->reference->allRegions());
    }

    public function allCities(): JsonResponse
    {
        return response()->json($this->reference->allCities());
    }
}
