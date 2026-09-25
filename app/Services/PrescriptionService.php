<?php

namespace App\Services;

use App\Models\MedicinePrescription;
use App\Models\TestPrescription;
use App\Models\User;
use Illuminate\Support\Collection;

/** Medicine and lab-test prescriptions written by doctors. */
class PrescriptionService
{
    public const TYPES = ['medicine' => MedicinePrescription::class, 'test' => TestPrescription::class];

    /** Patient fields printed on a prescription. */
    private const PATIENT_FIELDS = ['patient_name', 'id_no', 'mobile', 'date_of_birth', 'age', 'gender', 'nationality'];

    public function __construct(
        private readonly PatientService $patients,
        private readonly UserDirectoryService $users,
    ) {}

    public function create(string $type, array $data, User $doctor): MedicinePrescription|TestPrescription
    {
        return $this->model($type)::create([
            'patient_id' => $data['patient_id'],
            'date' => $data['date'] ?? now()->format('Y-m-d'),
            'prescription_data' => $data['prescription_data'] ?? [],
            'doctor_id' => $doctor->username,
        ]);
    }

    public function forPatient(string $type, string $patientId): Collection
    {
        return $this->model($type)::where('patient_id', $patientId)->orderBy('date', 'desc')->get();
    }

    /** A prescription with the patient and doctor details needed to print it. */
    public function details(string $type, string $id): MedicinePrescription|TestPrescription
    {
        $prescription = $this->model($type)::where('_id', $id)->firstOrFail();
        $patient = $this->patients->find($prescription->patient_id);
        $doctor = $this->users->find($prescription->doctor_id);

        foreach (self::PATIENT_FIELDS as $field) {
            $prescription->{$field} = $patient?->{$field};
        }
        $prescription->doctor_name = $doctor?->name;
        $prescription->doctor_signature = $doctor?->digitalsignature;

        return $prescription;
    }

    /** @return class-string<MedicinePrescription|TestPrescription> */
    private function model(string $type): string
    {
        return self::TYPES[$type] ?? throw new \InvalidArgumentException("Unknown prescription type '{$type}'.");
    }
}
