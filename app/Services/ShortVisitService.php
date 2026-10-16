<?php

namespace App\Services;

use App\Models\StaffShortVisit;
use App\Models\User;
use App\Support\TimeFormat;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ShortVisitService
{
    public function __construct(
        private readonly UserDirectoryService $users,
        private readonly PushNotificationService $push,
    ) {}

    /**
     * Schedule a one-off visit; the staff member's role is taken from their
     * account, and they get a push notification.
     */
    public function create(array $data): StaffShortVisit
    {
        $staff = $this->users->find($data['staff_id'] ?? null)
            ?? throw (new ModelNotFoundException)->setModel(User::class, [$data['staff_id'] ?? null]);

        $visit = StaffShortVisit::create([
            'staff_id' => $staff->username,
            'staff_role' => $staff->primaryRole(),
            'patient_name' => $data['patient_name'] ?? null,
            'patient_mobile' => $data['mobile'] ?? null,
            'patient_email' => $data['email'] ?? null,
            'patient_country' => $data['country'] ?? null,
            'patient_address' => $data['patient_address'] ?? null,
            'date' => $data['date'] ?? null,
            'time' => $data['time'] ?? null,
            'visit_purpose' => $data['visit_purpose'] ?? null,
            'visit_fees' => $data['visit_fees'] ?? null,
            'sign_in' => false, 'signin_time' => '',
            'sign_out' => false, 'signout_time' => '',
            'not_attended' => false, 'not_attended_reason' => '',
        ]);

        $this->push->notify(
            $staff,
            'New short visit',
            "{$visit->patient_name} on {$visit->date} at ".TimeFormat::to12Hour($visit->time).'.',
            ['type' => 'short_visit', 'visitId' => (string) $visit->getKey(), 'date' => $visit->date],
        );

        return $visit;
    }
}
