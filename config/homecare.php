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
        'sid' => env('TWILIO_AUTH_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
        'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID', 'MG054c1d19925425983d571629fd0c581d'),
        'templates' => [
            'staff_duty_with_package' => env('TWILIO_TPL_STAFF_DUTY_WITH_PACKAGE', 'HX6f9897d8aacccd963ce7318dc85a22f4'),
            'patient_duty_with_package' => env('TWILIO_TPL_PATIENT_DUTY_WITH_PACKAGE', 'HX40072c58813176c6d64b7cbc42711d60'),
            'staff_duty' => env('TWILIO_TPL_STAFF_DUTY', 'HXeadddbab85790b02c8f571b357575147'),
            'patient_duty' => env('TWILIO_TPL_PATIENT_DUTY', 'HX30980aa123e2785aa21c6e1f7572e444'),
        ],
    ],

];
