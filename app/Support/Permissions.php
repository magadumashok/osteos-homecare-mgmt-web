<?php

namespace App\Support;

/**
 * The permission catalogue, and the default roles seeded into every new
 * homecare database.
 *
 * Permissions are defined in code (not editable in the database) because
 * the server enforces them: a permission only means something if a route
 * checks it. Roles, and which permissions each role holds, stay editable
 * per homecare center.
 *
 * Naming follows the EMR: "<area>.<action>".
 */
final class Permissions
{
    /** Role that always holds every permission, whatever its stored list says. */
    public const SUPER_ROLE = 'admin';

    /** @var array<string, string> permission => description */
    public const CATALOGUE = [
        'staff.view' => 'View staff members',
        'staff.manage' => 'Add, edit and remove staff members',
        'roles.manage' => 'Manage roles and assign them to staff',

        'leads.view' => 'View patient leads',
        'leads.manage' => 'Create and update patient leads',

        'assessments.view' => 'View initial assessments',
        'assessments.manage' => 'Record and update initial assessments, consent and signatures',

        'patients.view' => 'View admitted patients and their records',
        'patients.manage' => 'Admit patients and edit patient details',
        'patients.walkin' => 'Register a walk-in patient and log the visit as own duty',

        'schedules.view' => 'View duty and driver schedules',
        'schedules.manage' => 'Create, reschedule and cancel duty and driver schedules',

        'attendance.view' => 'View staff and driver attendance',
        'attendance.record' => 'Sign in and out of own duties, record pickups and drops',

        'medications.view' => 'View medication schedules and administration',
        'medications.manage' => 'Create medication schedules',
        'medications.administer' => 'Record administered medication',

        'vitals.view' => 'View vital signs',
        'vitals.record' => 'Record vital signs',

        'notes.view' => 'View nursing and staff notes',
        'notes.write' => 'Write nursing and staff notes',

        'documents.view' => 'View patient documents and images',
        'documents.upload' => 'Upload patient documents, IDs and specimen images',
        'documents.delete' => 'Delete patient documents',

        'prescriptions.view' => 'View medicine and test prescriptions',
        'prescriptions.write' => 'Write medicine and test prescriptions',

        'discharge.view' => 'View discharged patients and summaries',
        'discharge.manage' => 'Discharge patients',

        'invoices.view' => 'View assessment and patient invoices',

        'video.call' => 'Start video calls',
    ];

    /**
     * Default roles. Role names match the legacy `roles` values stored on
     * users (admin, doctor, nurse, physiotherapist, transport) so migrated
     * users keep working without reassignment.
     *
     * @var array<string, array{description: string, permissions: list<string>}>
     */
    public const DEFAULT_ROLES = [
        'admin' => [
            'description' => 'Homecare administrator: full access',
            'permissions' => ['*'],
        ],
        'doctor' => [
            'description' => 'Doctor: assessments, prescriptions and clinical records',
            'permissions' => [
                'leads.view', 'assessments.view', 'assessments.manage',
                'patients.view', 'patients.walkin', 'schedules.view', 'attendance.record',
                'medications.view', 'medications.manage',
                'vitals.view', 'vitals.record', 'notes.view', 'notes.write',
                'documents.view', 'documents.upload',
                'prescriptions.view', 'prescriptions.write',
                'discharge.view', 'video.call',
            ],
        ],
        // Nurses and physiotherapists record assessments and nurses enter
        // medication schedules from the mobile app, as in the legacy app.
        'nurse' => [
            'description' => 'Nurse: assessments, duties, medication, vitals and notes',
            'permissions' => [
                'assessments.view', 'assessments.manage',
                'patients.view', 'patients.walkin', 'schedules.view', 'attendance.record',
                'medications.view', 'medications.manage', 'medications.administer',
                'vitals.view', 'vitals.record', 'notes.view', 'notes.write',
                'documents.view', 'documents.upload',
                'prescriptions.view', 'discharge.view', 'discharge.manage',
                'video.call',
            ],
        ],
        'physiotherapist' => [
            'description' => 'Physiotherapist: assessments, duties, vitals and notes',
            'permissions' => [
                'assessments.view', 'assessments.manage',
                'patients.view', 'patients.walkin', 'schedules.view', 'attendance.record',
                'vitals.view', 'vitals.record', 'notes.view', 'notes.write',
                'documents.view', 'documents.upload', 'video.call',
            ],
        ],
        'transport' => [
            'description' => 'Driver: own schedule, pickups and drops',
            'permissions' => ['schedules.view', 'attendance.record'],
        ],
    ];

    /**
     * Legacy permission names found on migrated roles, mapped to the
     * catalogue. The old app only knew these three.
     *
     * @var array<string, list<string>>
     */
    public const LEGACY_MAP = [
        'manage_users' => [
            'staff.view', 'staff.manage', 'leads.view', 'assessments.view',
            'patients.view', 'discharge.view', 'invoices.view', 'attendance.view',
        ],
        'manage_roles' => ['roles.manage'],
        'manage_permissions' => ['roles.manage'],
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::CATALOGUE);
    }

    public static function exists(string $permission): bool
    {
        return array_key_exists($permission, self::CATALOGUE);
    }

    /**
     * Expand a stored permission list: "*" becomes the full catalogue,
     * legacy names are translated, unknown names are dropped.
     *
     * @param  iterable<mixed>  $stored
     * @return list<string>
     */
    public static function normalize(iterable $stored): array
    {
        $result = [];

        foreach ($stored as $permission) {
            if (! is_string($permission)) {
                continue;
            }
            if ($permission === '*') {
                return self::all();
            }
            if (isset(self::LEGACY_MAP[$permission])) {
                array_push($result, ...self::LEGACY_MAP[$permission]);
            } elseif (self::exists($permission)) {
                $result[] = $permission;
            }
        }

        return array_values(array_unique($result));
    }
}
