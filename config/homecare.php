<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenancy
    |--------------------------------------------------------------------------
    |
    | Each homecare center gets its own MongoDB database named
    | "<prefix><homecare_id>". Atlas limits database names to 38 bytes, so
    | longer names are shortened with a stable hash suffix (TenantService).
    |
    */

    'tenant' => [
        'database_prefix' => env('TENANT_DB_PREFIX', 'homecare_'),
        'max_database_name_length' => 38,
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy mobile API
    |--------------------------------------------------------------------------
    |
    | The old mobile app calls the unversioned /api/* routes and was issued
    | tokens that never expired. Tokens issued through the legacy login
    | endpoints get this lifetime (minutes) instead of the default JWT_TTL.
    |
    */

    'legacy' => [
        'token_ttl' => (int) env('JWT_LEGACY_TTL', 43200), // 30 days
    ],

    /*
    |--------------------------------------------------------------------------
    | Twilio WhatsApp
    |--------------------------------------------------------------------------
    |
    | Template (content) SIDs are the approved WhatsApp templates the old app
    | used. They are not secrets, but keep them overridable per environment.
    |
    */

    'twilio' => [
        // Appended to fees in messages ("250 AED").
        'currency' => env('HOMECARE_CURRENCY', 'Rs'),
        'sid' => env('TWILIO_AUTH_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
        'templates' => [
            'patient_registered' => env('TWILIO_TPL_PATIENT_REGISTERED', 'HX0042acbf8a6ac95bf82bfe337cc1aa10'),
            'staff_duty_with_package' => env('TWILIO_TPL_STAFF_DUTY_WITH_PACKAGE', 'HX6f9897d8aacccd963ce7318dc85a22f4'),
            'staff_duty_with_package_no_driver' => env('TWILIO_TPL_STAFF_DUTY_WITH_PACKAGE_NO_DRIVER', 'HXd66ab367652cfda8e7890afee28a84c0'),
            'patient_duty_with_package' => env('TWILIO_TPL_PATIENT_DUTY_WITH_PACKAGE', 'HX40072c58813176c6d64b7cbc42711d60'),
            'staff_duty' => env('TWILIO_TPL_STAFF_DUTY', 'HXeadddbab85790b02c8f571b357575147'),
            'staff_duty_no_driver'  => env('TWILIO_TPL_STAFF_DUTY_NO_DRIVER', 'HX51ae52dcf3ce5f2a2be024daf5e26c30'),
            'patient_duty' => env('TWILIO_TPL_PATIENT_DUTY', 'HX30980aa123e2785aa21c6e1f7572e444'),
            'patient_duty_rescheduled' => env('TWILIO_TPL_PATIENT_DUTY_RESCHEDULED', 'HX5fcd73ce4d1374210f6648a105e3740a'),
            'driver_duty' => env('TWILIO_TPL_DRIVER_DUTY', 'HX1d1f68f5c2e6cd111315a9cc917ddc33'),
            'driver_duty_rescheduled' => env('TWILIO_TPL_DRIVER_DUTY_RESCHEDULED', 'HX84d91dc968afd6604e8399dc85205f26'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Patient documents
    |--------------------------------------------------------------------------
    |
    | Uploads (documents, IDs, specimen images, signatures, audio notes) go
    | to this disk under "<homecare_id>/...". Stored URLs are built from
    | `url`, which the legacy app served through a CDN in front of the
    | "homecarecenters" bucket.
    |
    */

    // Without an S3 bucket (local development) files go to the public disk,
    // served from APP_URL/storage (run `php artisan storage:link` once).
    'documents' => [
        'disk' => env('DOCUMENTS_DISK', env('AWS_BUCKET') ? 's3' : 'public'),
        'url' => env('DOCUMENTS_URL', env('AWS_BUCKET') ? 'https://hccsdoc.osteos.io' : rtrim((string) env('APP_URL', 'http://localhost'), '/').'/storage'),
        'max_upload_kb' => (int) env('DOCUMENTS_MAX_UPLOAD_KB', 20480),
    ],

];
