<?php

namespace App\Services;

use App\Models\PatientVitalSign;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

class VitalSignService
{
    public const RANGES = ['all_time', 'today', 'this_week', 'this_month'];

    public function __construct(private readonly PatientService $patients) {}

    public function findOrFail(string $id): PatientVitalSign
    {
        return PatientVitalSign::where('_id', $id)->firstOrFail();
    }

    public function store(array $data): PatientVitalSign
    {
        return PatientVitalSign::create(array_filter($data, fn ($value) => $value !== null));
    }

    public function update(PatientVitalSign $vitals, array $data): PatientVitalSign
    {
        unset($data['_id'], $data['patient_id']);
        $vitals->update($data);

        return $vitals;
    }

    /** Readings in a range: all_time, today, this_week (last 7 days) or this_month. */
    public function inRange(string $patientId, string $range): Collection
    {
        $today = Date::today();

        [$from, $to] = match ($range) {
            'today' => [$today, $today],
            'this_week' => [$today->copy()->subDays(7), $today],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            default => [null, null],
        };

        $readings = PatientVitalSign::where('patient_id', $patientId)
            ->when($from, fn ($q) => $q->whereBetween('date', [$from->format('Y-m-d'), $to->format('Y-m-d')]))
            ->orderBy('date', 'desc')
            ->orderBy('time', 'desc')
            ->get();

        return $this->withPatientName($patientId, $readings);
    }

    public function onDate(string $patientId, string $date): Collection
    {
        $readings = PatientVitalSign::where('patient_id', $patientId)
            ->where('date', $date)
            ->orderBy('time', 'desc')
            ->get();

        return $this->withPatientName($patientId, $readings);
    }

    public function latest(string $patientId): ?PatientVitalSign
    {
        return PatientVitalSign::where('patient_id', $patientId)
            ->orderBy('date', 'desc')
            ->orderBy('time', 'desc')
            ->first();
    }

    /** Every row is the same patient, so look the name up once (not once per row). */
    private function withPatientName(string $patientId, Collection $readings): Collection
    {
        $name = $readings->isEmpty() ? null : $this->patients->find($patientId)?->patient_name;

        return $name === null ? $readings : $readings->each(fn ($reading) => $reading->patient_name = $name);
    }
}
