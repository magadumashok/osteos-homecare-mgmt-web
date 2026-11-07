<?php

namespace App\Http\Middleware;

use App\Services\PatientAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware `assigned.patient`: the patient the request is about must
 * be assigned to the user (see PatientAccessService). Must run after
 * ResolveTenant. Refuses a request that names no patient.
 *
 * v1 routes name the patient in the URL. Legacy routes use several fields,
 * so every one present is checked: otherwise a request could pass on one
 * field and be answered from another.
 */
class EnsureAssignedPatient
{
    private const LEGACY_FIELDS = ['patient_id', 'id', 'pid', 'formProps.patient_id', 'formdata.patient_id'];

    public function __construct(private readonly PatientAccessService $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $this->access->seesAll($user)) {
            return $next($request);
        }

        $patientIds = $this->patientIds($request);
        $allowed = $user && $patientIds !== [];

        foreach ($patientIds as $patientId) {
            $allowed = $allowed && $this->access->canSee($user, $patientId);
        }

        if ($allowed) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'This patient is not assigned to you.',
        ], 403);
    }

    /** @return list<string> */
    private function patientIds(Request $request): array
    {
        if ($username = $request->route('username')) {
            return [(string) $username];
        }

        $values = [$request->route('id'), ...array_map(fn ($field) => $request->input($field), self::LEGACY_FIELDS)];

        return array_values(array_unique(array_map('strval', array_filter($values, fn ($v) => is_scalar($v) && $v !== ''))));
    }
}
