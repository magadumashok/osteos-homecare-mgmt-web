<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ReferenceDataService;
use App\Traits\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceDataController extends Controller
{
    use ResponseTrait;

    public function __construct(private readonly ReferenceDataService $reference) {}

    public function countries(): JsonResponse
    {
        return $this->response(true, '', 200, $this->reference->countries());
    }

    public function nationalities(): JsonResponse
    {
        return $this->response(true, '', 200, $this->reference->nationalities());
    }

    public function regions(string $country): JsonResponse
    {
        return $this->response(true, '', 200, $this->reference->regions($country));
    }

    public function cities(string $region): JsonResponse
    {
        return $this->response(true, '', 200, $this->reference->cities($region));
    }

    public function servicePackages(): JsonResponse
    {
        return $this->response(true, '', 200, $this->reference->servicePackages());
    }

    /** ?search= matches code or description. */
    public function icdCodes(Request $request): JsonResponse
    {
        $search = $request->validate(['search' => ['required', 'string', 'min:2', 'max:100']])['search'];

        return $this->response(true, '', 200, $this->reference->searchIcdCodes($search));
    }
}
