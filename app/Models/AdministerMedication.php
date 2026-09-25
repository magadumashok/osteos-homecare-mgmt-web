<?php

namespace App\Models;

/**
 * What was given of one medicine on one date. Each session (morning,
 * afternoon, evening, night) holds {nurse_id, nurse_name, given_date,
 * given_time, guardian_id}.
 */
class AdministerMedication extends TenantModel
{
    public const SESSIONS = ['morning', 'afternoon', 'evening', 'night'];

    protected $table = 'administer_medications';

    protected $fillable = ['patient_id', 'medicine_name', 'date', ...self::SESSIONS];
}
