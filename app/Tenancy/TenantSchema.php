<?php

namespace App\Tenancy;

/**
 * Collections and indexes of a homecare center's database.
 *
 * Collection names match the legacy DynamoDB tables so the data migration
 * is a straight copy. Indexes cover the fields the legacy controllers
 * filter and sort on (every one of those queries was a full table scan
 * in DynamoDB).
 */
final class TenantSchema
{
    /**
     * collection => list of [keys, options]
     *
     * @var array<string, list<array{0: array<string, int>, 1?: array<string, mixed>}>>
     */
    public const COLLECTIONS = [
        // Per-homecare sequence numbers (SequenceService); _id is the sequence name.
        'counters' => [],
        'roles' => [
            [['name' => 1], ['unique' => true]],
        ],
        'patient_leads' => [
            [['lead_id' => 1]],
            [['mobile' => 1]],
            [['followup' => 1]],
        ],
        'patients' => [
            [['username' => 1], ['unique' => true]],
            [['discharged' => 1]],
        ],
        'patient_guardians' => [
            [['patient_id' => 1, 'guardian_id' => 1]],
            [['guardian_id' => 1]],
        ],
        'initial_assessment' => [
            [['assessment_id' => 1]],
        ],
        'staff_duty_schedule' => [
            [['staff_id' => 1, 'date' => 1]],
            [['staff_id' => 1, 'end_date' => 1]],
            [['patient_id' => 1, 'date' => 1]],
            [['date' => 1, 'start_time' => 1]],
            [['role' => 1]],
        ],
        'nurse_duty_schedule' => [
            [['staff_id' => 1, 'date' => 1]],
            [['patient_id' => 1, 'date' => 1]],
        ],
        'driver_schedule' => [
            [['driver_id' => 1, 'date' => 1]],
            [['staff_id' => 1, 'date' => 1]],
        ],
        'staff_short_visit' => [
            [['staff_id' => 1, 'date' => 1]],
            [['patient_id' => 1, 'date' => 1]],
        ],
        'medication_schedule' => [
            [['patient_id' => 1, 'date' => 1]],
        ],
        'administer_medications' => [
            [['patient_id' => 1, 'date' => 1, 'medicine_name' => 1]],
        ],
        'patient_vital_signs' => [
            [['patient_id' => 1, 'date' => 1, 'time' => 1]],
        ],
        'nursing_notes' => [
            [['patient_id' => 1, 'notes_type' => 1, 'date' => 1]],
        ],
        'staff_notes' => [
            [['patient_id' => 1, 'notes_type' => 1, 'date' => 1]],
        ],
        'medicine_prescriptions' => [
            [['patient_id' => 1, 'date' => 1]],
        ],
        'test_prescriptions' => [
            [['patient_id' => 1, 'date' => 1]],
        ],
        'discharged_patients' => [
            [['patient_id' => 1]],
        ],
        'feedbacks' => [],
    ];

    /** @return list<string> */
    public static function collections(): array
    {
        return array_keys(self::COLLECTIONS);
    }
}
